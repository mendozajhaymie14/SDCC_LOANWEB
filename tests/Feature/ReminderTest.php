<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'usertype' => 'branch_manager',
            'email'    => 'admin@example.com',
        ]);
    }

    private function makeMember(): User
    {
        return User::factory()->create([
            'usertype' => 'member',
            'email'    => 'member@example.com',
        ]);
    }

    /**
     * Get today's date in Asia/Manila timezone to match PaymentReminderService logic.
     */
    private function getManilaToday(): string
    {
        return Carbon::today('Asia/Manila')->toDateString();
    }

    /**
     * Get a date N days from today in Asia/Manila timezone.
     */
    private function getManilaDate(int $daysOffset): string
    {
        return Carbon::today('Asia/Manila')->addDays($daysOffset)->toDateString();
    }

    private function makeLoan(User $member, string $status = 'approved', $dueDate = null, float $balance = 1000): LoanApplication
    {
        CoopMember::factory()->create([
            'user_id'         => $member->id,
            'share_capital'   => 30000,
            'is_migs'         => false,
        ]);

        return LoanApplication::factory()->create([
            'user_id'             => $member->id,
            'full_name'           => 'Test Borrower',
            'email'               => $member->email,
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => $status,
            'monthly_income'      => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'       => 30000,
            'term_months'         => 12,
            'remaining_balance'   => $balance,
            'next_due_date'       => $dueDate,
        ]);
    }

    /** @test */
    public function due_soon_loan_creates_single_notification()
    {
        $member = $this->makeMember();
        // Loan due in 2 days (within the 3-day window)
        $loan   = $this->makeLoan($member, 'approved', $this->getManilaDate(2), 800);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $this->assertDatabaseHas('app_notifications', [
            'user_id'            => $member->id,
            'loan_application_id'=> $loan->id,
            'type'               => 'due_soon',
        ]);

        // Only one notification for this loan
        $this->assertEquals(1, AppNotification::where('loan_application_id', $loan->id)->count());
    }

    /** @test */
    public function due_today_loan_creates_single_notification()
    {
        $member = $this->makeMember();
        // Loan due today (in Manila timezone)
        $loan   = $this->makeLoan($member, 'approved', $this->getManilaToday(), 800);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $this->assertDatabaseHas('app_notifications', [
            'user_id'            => $member->id,
            'loan_application_id'=> $loan->id,
            'type'               => 'due_today',
        ]);

        // Only one notification for this loan
        $this->assertEquals(1, AppNotification::where('loan_application_id', $loan->id)->count());
    }

    /** @test */
    public function overdue_loan_creates_single_notification_and_email()
    {
        $member = $this->makeMember();
        // Loan overdue (due 10 days ago in Manila timezone)
        $loan   = $this->makeLoan($member, 'approved', $this->getManilaDate(-10), 800);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $this->assertDatabaseHas('app_notifications', [
            'user_id'            => $member->id,
            'loan_application_id'=> $loan->id,
            'type'               => 'overdue',
        ]);

        // Only one notification for this loan
        $this->assertEquals(1, AppNotification::where('loan_application_id', $loan->id)->count());

        // Verify mail was sent with correct subject (build() sets subject from title)
        Mail::assertSent(\App\Mail\PaymentReminderMail::class, function ($mail) use ($member, $loan) {
            // In test mode, build() isn't called so subject is not set.
            // The title 'Overdue Payment Reminder' is passed via the message param.
            // Verify the mailable contains the correct loan and was sent to the member.
            return $mail->hasTo($member->email)
                && $mail->loan->id === $loan->id;
        });
    }

    /** @test */
    public function paid_loan_creates_no_notification_and_no_email()
    {
        $member = $this->makeMember();
        $loan   = $this->makeLoan($member, 'paid', $this->getManilaDate(-10), 0);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $this->assertDatabaseMissing('app_notifications', [
            'loan_application_id' => $loan->id,
        ]);

        Mail::assertNothingSent();
    }

    /** @test */
    public function running_reminders_twice_in_one_day_does_not_duplicate()
    {
        $member = $this->makeMember();
        $loan   = $this->makeLoan($member, 'approved', $this->getManilaDate(-10), 800);

        $admin = $this->makeAdmin();

        // First run
        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        // Second run same day (both in same Manila day)
        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        // Should still have only 1 notification
        $this->assertEquals(1, AppNotification::where('loan_application_id', $loan->id)->count());

        // Should have only 1 email sent
        Mail::assertSent(\App\Mail\PaymentReminderMail::class, 1);
    }

    /** @test */
    public function member_cannot_call_run_reminders_route()
    {
        $member = $this->makeMember();

        $this->actingAs($member)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertForbidden();
    }

    // ─── Approval / Rejection email tests (capstone: email only) ───

    private function makePendingLoan(User $member): LoanApplication
    {
        CoopMember::factory()->create([
            'user_id'       => $member->id,
            'share_capital' => 30000,
            'is_migs'       => false,
        ]);

        return LoanApplication::factory()->create([
            'user_id'                       => $member->id,
            'full_name'                     => 'Test Borrower',
            'email'                         => $member->email,
            'loan_type'                     => 'regular',
            'amount'                        => 5000,
            'status'                        => 'pending',
            'monthly_income'                => 20000,
            'existing_monthly_obligations'  => 0,
            'share_capital'                 => 30000,
            'term_months'                   => 12,
            'remaining_balance'             => 0,
            'next_due_date'                 => null,
        ]);
    }

    private function makePendingAdminApp(LoanApplication $loanApp): \App\Models\Application
    {
        return \App\Models\Application::factory()->create([
            'loan_application_id' => $loanApp->id,
            'app_id'              => 'APP-' . str_pad($loanApp->id, 4, '0', STR_PAD_LEFT),
            'applicant'           => $loanApp->full_name,
            'status'              => 'pending',
            'recommended_amount'  => 2500.00,
            'risk_level'          => 'Medium',
            'risk_score'          => 60,
        ]);
    }

    /** @test */
    public function approving_a_loan_sends_email_to_borrower()
    {
        $member = $this->makeMember();
        $loan   = $this->makePendingLoan($member);
        $app    = $this->makePendingAdminApp($loan);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        Mail::assertSent(\App\Mail\LoanApprovedMail::class, function ($mail) use ($member) {
            return $mail->hasTo($member->email);
        });

        // Also logged in the notification outbox
        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'    => $member->email,
            'subject'            => 'Your Loan Application Has Been Approved',
            'notification_type'  => 'approved',
            'status'             => 'Sent',
        ]);
    }

    /** @test */
    public function rejecting_a_loan_sends_email_to_borrower()
    {
        $member = $this->makeMember();
        $loan   = $this->makePendingLoan($member);
        $app    = $this->makePendingAdminApp($loan);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('applications.reject', $app->id), [
                'reason' => 'Insufficient documentation',
            ]);

        Mail::assertSent(\App\Mail\LoanRejectedMail::class, function ($mail) use ($member) {
            return $mail->hasTo($member->email);
        });

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'    => $member->email,
            'subject'            => 'Your Loan Application Update',
            'notification_type'  => 'rejected',
            'status'             => 'Sent',
        ]);
    }

    /** @test */
    public function approved_loan_then_payment_received_sends_payment_email()
    {
        $member = $this->makeMember();
        $loan   = $this->makePendingLoan($member);
        $app    = $this->makePendingAdminApp($loan);

        $admin = $this->makeAdmin();

        // Approve first
        $this->actingAs($admin)
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        Mail::assertSent(\App\Mail\LoanApprovedMail::class);

        // Record a repayment
        $this->actingAs($admin)
            ->post(route('repayments.store'), [
                'application_id'  => $app->id,
                'amount'          => 1000,
                'payment_method'  => 'Cash',
                'payment_date'    => now()->format('Y-m-d'),
            ]);

        // PaymentReceived event dispatches NotifyMemberOfPayment — verify it
        // sent the payment notification email (existing behavior, confirmed here).
        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'   => $member->email,
            'notification_type' => 'payment_received',
            'status'            => 'Sent',
        ]);
    }
}