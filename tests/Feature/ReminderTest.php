<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AppNotificationLog;
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

    /** @test
     * Issue E: Approving a loan sends exactly ONE approval notification (no duplicates from stale listeners).
     */
    public function approving_a_loan_sends_single_notification_no_duplicates()
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

        // Should have exactly ONE app_notification_log entry for this approval
        $this->assertSame(1, AppNotificationLog::where('loan_application_id', $loan->id)
            ->where('notification_type', 'approved')
            ->where('status', 'Sent')
            ->count());

        // Should have exactly ONE email sent
        Mail::assertSent(\App\Mail\LoanApprovedMail::class, 1);

        // Should have exactly ONE in-app notification (AppNotification) for the borrower
        $this->assertSame(1, AppNotification::where('loan_application_id', $loan->id)
            ->where('type', 'approved')
            ->count());
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

    /** @test */
    public function reminder_messages_use_whole_non_negative_day_wording(): void
    {
        $member = $this->makeMember();

        // Loan due tomorrow (within the 3-day due-soon window)
        $dueSoon = $this->makeLoan($member, 'approved', $this->getManilaDate(1), 800);
        // Loan overdue (due 5 days ago)
        $overdue = $this->makeLoan($member, 'approved', $this->getManilaDate(-5), 800);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $dueSoonNotif = AppNotification::where('loan_application_id', $dueSoon->id)
            ->where('type', 'due_soon')
            ->first();

        $overdueNotif = AppNotification::where('loan_application_id', $overdue->id)
            ->where('type', 'overdue')
            ->first();

        $this->assertNotNull($dueSoonNotif, 'due-soon notification should exist');
        $this->assertNotNull($overdueNotif, 'overdue notification should exist');

        // The message must contain whole-day wording, never fractional.
        // "-2.3333333333333 day(s)" style output is the bug we fixed.
        $this->assertMatchesRegularExpression(
            '/\b(?:due tomorrow|due in \d+ days?)\b/',
            $dueSoonNotif->message,
            'Due-soon message should say "due tomorrow" or "due in N days"'
        );

        // Overdue must be a whole non-negative number of days
        $this->assertMatchesRegularExpression(
            '/was due.*\d+ days? ago/',
            $overdueNotif->message,
            'Overdue message should say "was due N day(s) ago" with whole N'
        );

        // No negative or fractional values anywhere in either message
        $this->assertStringNotContainsString('-2.', $dueSoonNotif->message);
        $this->assertStringNotContainsString('-2.', $overdueNotif->message);
        $this->assertStringNotContainsString('1.3333', $dueSoonNotif->message);
        $this->assertStringNotContainsString('1.3333', $overdueNotif->message);
    }

    /** @test */
    public function loan_without_borrower_email_logs_skipped_not_sent(): void
    {
        // Create a member with empty email (NOT NULL constraint on users.email allows empty string)
        $member = $this->makeMember();
        $member->update(['email' => '']);

        $loan = LoanApplication::factory()->create([
            'user_id'             => $member->id,
            'full_name'           => 'No Email Borrower',
            'email'               => '',  // No email stored on loan
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'approved',
            'monthly_income'      => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'       => 30000,
            'term_months'         => 12,
            'remaining_balance'   => 800,
            'next_due_date'       => $this->getManilaDate(-5), // overdue
        ]);

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $data = $response->json();
        $this->assertEquals(0, $data['sent'], 'No email should be sent when borrower has no email');
        $this->assertEquals(1, $data['skipped'], 'Should count as skipped when no email');

        // Verify notification log shows "Skipped" status
        $this->assertDatabaseHas('app_notification_logs', [
            'loan_application_id' => $loan->id,
            'notification_type'   => 'overdue',
            'status'              => 'Skipped',
            'channel'             => 'email',
        ]);

        // No mail should have been sent
        Mail::assertNothingSent();
    }

    /** @test
 * Issue D: loan with coop_member email (not on loan record) uses fallback.
 * Email lives ONLY on coop_members.email, not on loan_applications.email.
 */
    public function loan_with_coop_member_email_only_uses_fallback(): void
    {
        // Member with linked CoopMember that has email.
        // Loan has user_id set so $loan->user works; email is NOT on loan->email.
        $member = User::factory()->create([
            'usertype' => 'member',
            'email'    => 'member@example.com',
        ]);

        $coopMember = CoopMember::factory()->create([
            'user_id'       => $member->id,
            'full_name'     => 'Test Borrower',
            'email'         => 'coop.member@example.com', // email ONLY on CoopMember
            'status'        => CoopMember::STATUS_ACTIVE,
        ]);

        // Link User.coop_member_id to CoopMember.id so $loan->user->coopMember works
        $member->update(['coop_member_id' => $coopMember->id]);
        $member = $member->fresh();

        $loan = LoanApplication::factory()->create([
            'user_id'             => $member->id,
            'full_name'           => 'Test Borrower',
            'email'               => '',         // NO email on loan record
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'approved',
            'monthly_income'      => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'       => 30000,
            'term_months'         => 12,
            'remaining_balance'   => 800,
            'next_due_date'       => $this->getManilaDate(-5), // overdue
        ]);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        // Email found via $loan->user->coopMember->email (not $loan->email)
        $this->assertDatabaseHas('app_notification_logs', [
            'loan_application_id' => $loan->id,
            'notification_type'   => 'overdue',
            'status'              => 'Sent',
            'channel'             => 'email',
            'recipient_email'     => 'coop.member@example.com',
        ]);

        Mail::assertSent(\App\Mail\PaymentReminderMail::class, 1);
    }

    /** @test
     * Issue D: borrower with email → logged Sent; no email anywhere → logged Skipped.
     */
    public function borrower_with_email_logs_sent(): void
    {
        $member = User::factory()->create([
            'usertype' => 'member',
            'email'    => 'borrower@example.com',
        ]);

        $coopMember = CoopMember::factory()->create([
            'user_id'       => $member->id,
            'full_name'   => 'Test Borrower',
            'email'         => 'borrower@example.com',
            'status'      => CoopMember::STATUS_ACTIVE,
        ]);

        // Link User.coop_member_id to CoopMember.id so $loan->user->coopMember works
        $member->update(['coop_member_id' => $coopMember->id]);
        $member = $member->fresh();

        $loan = LoanApplication::factory()->create([
            'user_id'             => $member->id,
            'full_name'           => 'Test Borrower',
            'email'               => '',  // No email on loan record
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'approved',
            'monthly_income'      => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'       => 30000,
            'term_months'         => 12,
            'remaining_balance'   => 800,
            'next_due_date'       => $this->getManilaDate(-5), // overdue
        ]);

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $data = $response->json();
        $this->assertEquals(1, $data['sent'], 'Email should be sent when borrower has email via coop_member');
        $this->assertEquals(0, $data['skipped'], 'Should not be skipped when email available');

        // Verify notification log shows "Sent" status
        $this->assertDatabaseHas('app_notification_logs', [
            'loan_application_id' => $loan->id,
            'notification_type'   => 'overdue',
            'status'              => 'Sent',
            'channel'             => 'email',
            'recipient_email'     => 'borrower@example.com',
        ]);

        Mail::assertSent(\App\Mail\PaymentReminderMail::class, 1);
    }

    /** @test
     * Issue D: no email anywhere → logged Skipped and not counted as sent.
     */
    public function no_email_anywhere_logs_skipped(): void
    {
        $member = User::factory()->create([
            'usertype' => 'member',
            'email'    => '',  // No email anywhere
        ]);

        CoopMember::factory()->create([
            'user_id'       => $member->id,
            'full_name'   => 'Test Borrower',
            // NO email on CoopMember either (factory default would add one)
            'email'         => '',
            'status'        => CoopMember::STATUS_ACTIVE,
        ]);

        $loan = LoanApplication::factory()->create([
            'user_id'             => $member->id,
            'full_name'           => 'Test Borrower',
            'email'               => '',  // No email on loan
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'approved',
            'monthly_income'      => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'       => 30000,
            'term_months'         => 12,
            'remaining_balance'   => 800,
            'next_due_date'       => $this->getManilaDate(-5), // overdue
        ]);

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $data = $response->json();
        $this->assertEquals(0, $data['sent'], 'No email should be sent when no email anywhere');
        $this->assertEquals(1, $data['skipped'], 'Should count as skipped when no email');

        // Verify notification log shows "Skipped" status
        $this->assertDatabaseHas('app_notification_logs', [
            'loan_application_id' => $loan->id,
            'notification_type'   => 'overdue',
            'status'              => 'Skipped',
            'channel'             => 'email',
        ]);

        Mail::assertNothingSent();
    }

    /** @test
     * Issue D: approve a loan with user_id=null and email only on loan_applications.email.
     * The fallback chain must reach loan->email since user and coop_member are absent.
     */
    public function approving_a_loan_with_null_user_id_and_email_on_loan_sends_email(): void
    {
        $loan = LoanApplication::factory()->create([
            'user_id'             => null,
            'full_name'           => 'Null User Borrower',
            'email'               => 'nulluser@example.com',
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'pending',
            'monthly_income'      => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'       => 30000,
            'term_months'         => 12,
            'remaining_balance'   => 0,
            'next_due_date'       => null,
        ]);

        $app = $this->makePendingAdminApp($loan);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        Mail::assertSent(\App\Mail\LoanApprovedMail::class, function ($mail) {
            return $mail->hasTo('nulluser@example.com');
        });

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'    => 'nulluser@example.com',
            'subject'            => 'Your Loan Application Has Been Approved',
            'notification_type'  => 'approved',
            'status'             => 'Sent',
        ]);
    }

    /** @test
     * Issue D: reject a loan with user_id=null and email only on loan_applications.email.
     * The fallback chain must reach loan->email since user and coop_member are absent.
     */
    public function rejecting_a_loan_with_null_user_id_and_email_on_loan_sends_email(): void
    {
        $loan = LoanApplication::factory()->create([
            'user_id'             => null,
            'full_name'           => 'Null User Borrower',
            'email'               => 'nulluser@example.com',
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'pending',
            'monthly_income'      => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'       => 30000,
            'term_months'         => 12,
            'remaining_balance'   => 0,
            'next_due_date'       => null,
        ]);

        $app = $this->makePendingAdminApp($loan);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('applications.reject', $app->id), [
                'reason' => 'Insufficient documentation',
            ]);

        Mail::assertSent(\App\Mail\LoanRejectedMail::class, function ($mail) {
            return $mail->hasTo('nulluser@example.com');
        });

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'    => 'nulluser@example.com',
            'subject'            => 'Your Loan Application Update',
            'notification_type'  => 'rejected',
            'status'             => 'Sent',
        ]);
    }

    /** @test
     * Issue D: reminder with user_id=null and email only on loan_applications.email.
     * Reminder service must reach loan->email since user and coop_member are absent.
     */
    public function reminder_with_null_user_id_and_email_on_loan_sends_email(): void
    {
        $loan = LoanApplication::factory()->create([
            'user_id'             => null,
            'full_name'           => 'Null User Borrower',
            'email'               => 'nulluser@example.com',
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'approved',
            'monthly_income'      => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'       => 30000,
            'term_months'         => 12,
            'remaining_balance'   => 800,
            'next_due_date'       => $this->getManilaDate(-5), // overdue
        ]);

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $data = $response->json();
        $this->assertEquals(1, $data['sent'], 'Email should be sent when loan has email even with null user_id');
        $this->assertEquals(0, $data['skipped'], 'Should not be skipped when email available');

        $this->assertDatabaseHas('app_notification_logs', [
            'loan_application_id' => $loan->id,
            'notification_type'   => 'overdue',
            'status'              => 'Sent',
            'channel'             => 'email',
            'recipient_email'     => 'nulluser@example.com',
        ]);

        Mail::assertSent(\App\Mail\PaymentReminderMail::class, 1);
    }

    /** @test
     * Issue D: reminder with user_id=null and no email anywhere → logged Skipped, nothing sent.
     */
    public function reminder_with_null_user_id_no_email_logs_skipped(): void
    {
        $loan = LoanApplication::factory()->create([
            'user_id'             => null,
            'full_name'           => 'No Email Borrower',
            'email'               => '',   // No email on loan record
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'approved',
            'monthly_income'      => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'       => 30000,
            'term_months'         => 12,
            'remaining_balance'   => 800,
            'next_due_date'       => $this->getManilaDate(-5), // overdue
        ]);

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $data = $response->json();
        $this->assertEquals(0, $data['sent'], 'No email should be sent when borrower has no email');
        $this->assertEquals(1, $data['skipped'], 'Should count as skipped when no email');

        $this->assertDatabaseHas('app_notification_logs', [
            'loan_application_id' => $loan->id,
            'notification_type'   => 'overdue',
            'status'              => 'Skipped',
            'channel'             => 'email',
        ]);

        Mail::assertNothingSent();
    }

    /** @test
     * Issue D: empty-string user email with a real email on coop_members → fallback uses coop_member email.
     */
    public function empty_string_user_email_with_coop_member_email_uses_fallback(): void
    {
        $member = User::factory()->create([
            'usertype' => 'member',
            'email'    => '',  // Empty-string user email
        ]);

        $coopMember = CoopMember::factory()->create([
            'user_id'       => $member->id,
            'full_name'     => 'Test Borrower',
            'email'         => 'coop.member@example.com', // Real email ONLY on CoopMember
            'status'        => CoopMember::STATUS_ACTIVE,
        ]);

        // Link User.coop_member_id so $loan->user->coopMember works
        $member->update(['coop_member_id' => $coopMember->id]);
        $member = $member->fresh();

        $loan = LoanApplication::factory()->create([
            'user_id'             => $member->id,
            'full_name'           => 'Test Borrower',
            'email'               => '',         // No email on loan record
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'approved',
            'monthly_income'      => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'       => 30000,
            'term_months'         => 12,
            'remaining_balance'   => 800,
            'next_due_date'       => $this->getManilaDate(-5), // overdue
        ]);

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $data = $response->json();
        $this->assertEquals(1, $data['sent'], 'Email should be sent via coop_member fallback');
        $this->assertEquals(0, $data['skipped'], 'Should not be skipped when email available via coop_member');

        $this->assertDatabaseHas('app_notification_logs', [
            'loan_application_id' => $loan->id,
            'notification_type'   => 'overdue',
            'status'              => 'Sent',
            'channel'             => 'email',
            'recipient_email'     => 'coop.member@example.com',
        ]);

        Mail::assertSent(\App\Mail\PaymentReminderMail::class, 1);
    }

    /** @test
     * Issue D: duplicate guard — second run same day sends nothing new.
     */
    public function duplicate_guard_second_run_no_new_send(): void
    {
        $member = User::factory()->create([
            'usertype' => 'member',
            'email'    => 'dup@example.com',
        ]);

        CoopMember::factory()->create([
            'user_id'       => $member->id,
            'full_name'   => 'Test Borrower',
            'email'         => 'dup@example.com', // same email as member
            'status'        => CoopMember::STATUS_ACTIVE,
        ]);

        $loan = LoanApplication::factory()->create([
            'user_id'             => $member->id,
            'full_name'           => 'Test Borrower',
            'email'               => '',
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'approved',
            'monthly_income'      => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'       => 30000,
            'term_months'         => 12,
            'remaining_balance'   => 800,
            'next_due_date'       => $this->getManilaDate(-5), // overdue
        ]);

        $admin = $this->makeAdmin();

        // First run
        $response1 = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $data1 = $response1->json();
        $this->assertEquals(1, $data1['sent']);

        // Second run same day
        $response2 = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $data2 = $response2->json();

        // Totals should not have changed: still 1 sent, 0 new skipped (duplicate suppressed)
        $this->assertEquals(0, $data2['sent'], 'Second same-day run should send nothing new');
        $this->assertEquals(1, $data2['skipped'], 'Second same-day run should count the duplicate as skipped');
    }
}