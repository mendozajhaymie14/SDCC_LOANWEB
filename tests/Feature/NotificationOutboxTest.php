<?php

namespace Tests\Feature;

use App\Models\AppNotificationLog;
use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\MemberApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Email notification outbox coverage (TASK 3, items 3.1–3.8).
 *
 * For each notification type, assert:
 *  (a) the email is sent to the right recipient with the right subject,
 *  (b) exactly ONE app_notification_logs row is written with the correct
 *      type / status / channel / user_id / loan_application_id,
 *  (c) a failing mail transport writes a Failed row with an error message
 *      and does NOT break the action,
 *  (d) the row shows on the outbox page under its type filter.
 */
class NotificationOutboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
    }

    private function manager(): User
    {
        static $i = 0;
        $i++;

        return User::factory()->create([
            'usertype' => 'branch_manager',
            'email'    => 'staff' . $i . '@example.com',
        ]);
    }

    private function member(string $email = 'borrower@example.com'): User
    {
        return User::factory()->create([
            'usertype' => 'member',
            'email'    => $email,
        ]);
    }

    private function coopMember(User $member): CoopMember
    {
        return CoopMember::factory()->create([
            'user_id'       => $member->id,
            'email'         => $member->email,
            'share_capital' => 30000,
            'is_migs'       => false,
            'monthly_income' => 20000,
        ]);
    }

    private function pendingLoan(User $member): LoanApplication
    {
        return LoanApplication::factory()->create([
            'user_id'      => $member->id,
            'full_name'    => 'Test Borrower',
            'email'        => $member->email,
            'loan_type'    => 'regular',
            'amount'       => 5000,
            'status'       => 'pending',
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'term_months'  => 12,
            'remaining_balance' => 0,
            'next_due_date' => null,
        ]);
    }

    private function pendingAdminApp(LoanApplication $loan): Application
    {
        return Application::factory()->create([
            'loan_application_id' => $loan->id,
            'app_id'              => 'APP-' . str_pad($loan->id, 4, '0', STR_PAD_LEFT),
            'applicant'           => $loan->full_name,
            'status'              => 'Pending',
            'recommended_amount'  => 2500.00,
            'risk_level'          => 'Medium',
            'risk_score'          => 60,
        ]);
    }

    private function approvedLoan(User $member, string $dueDate, float $balance = 800): LoanApplication
    {
        return LoanApplication::factory()->create([
            'user_id'      => $member->id,
            'full_name'    => 'Test Borrower',
            'email'        => $member->email,
            'loan_type'    => 'regular',
            'amount'       => 5000,
            'status'       => 'approved',
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'term_months'  => 12,
            'remaining_balance' => $balance,
            'next_due_date' => $dueDate,
        ]);
    }

    /** Portal-less loan: no portal user, email stored on the loan row itself. */
    private function portalLessLoan(string $email, float $balance = 800): LoanApplication
    {
        return LoanApplication::factory()->create([
            'user_id'                       => null,
            'full_name'                     => 'Portal Less Borrower',
            'email'                         => $email,
            'loan_type'                     => 'regular',
            'amount'                        => 5000,
            'status'                        => 'approved',
            'monthly_income'                => 20000,
            'existing_monthly_obligations'  => 0,
            'share_capital'                 => 30000,
            'term_months'                   => 12,
            'remaining_balance'             => $balance,
            'next_due_date'                 => $this->manilaDate(5),
        ]);
    }

    private function approvedAdminApp(LoanApplication $loan, float $balance = 10000): Application
    {
        return Application::factory()->create([
            'loan_application_id' => $loan->id,
            'app_id'              => 'APP-' . str_pad($loan->id, 4, '0', STR_PAD_LEFT),
            'applicant'           => $loan->full_name,
            'status'              => 'Approved',
            'remaining_balance'   => $balance,
        ]);
    }

    private function manilaDate(int $offset): string
    {
        return Carbon::today('Asia/Manila')->addDays($offset)->toDateString();
    }

    /**
     * Replace the mailer with one whose transport always throws, so the
     * Failed-log path is exercised end to end.
     */
    private function makeMailerThrow(): void
    {
        $transport = new class implements \Symfony\Component\Mailer\Transport\TransportInterface {
            public function send(\Symfony\Component\Mime\RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): ?\Symfony\Component\Mailer\SentMessage
            {
                throw new \RuntimeException('mail transport down');
            }

            public function __debugInfo(): array
            {
                return [];
            }

            public function __toString(): string
            {
                return 'throwing-transport';
            }
        };

        $mailer = new \Illuminate\Mail\Mailer(
            'throwing',
            app(\Illuminate\Contracts\View\Factory::class),
            $transport,
            app(\Illuminate\Contracts\Events\Dispatcher::class)
        );

        Mail::swap($mailer);
    }

    // ─── 3.1 Approved / Rejected loan ────────────────────────────────────

    /** @test */
    public function approved_loan_sends_one_email_and_one_log_row(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->pendingLoan($member);
        $app  = $this->pendingAdminApp($loan);

        $this->actingAs($this->manager())
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        Mail::assertSent(\App\Mail\LoanApprovedMail::class, fn ($mail) => $mail->hasTo($member->email));

        $this->assertSame(1, AppNotificationLog::where('loan_application_id', $loan->id)
            ->where('notification_type', 'approved')
            ->where('status', 'Sent')
            ->where('channel', 'email')
            ->count());

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'    => $member->email,
            'subject'            => 'Your Loan Application Has Been Approved',
            'notification_type'  => 'approved',
            'status'             => 'Sent',
            'channel'            => 'email',
            'user_id'            => $member->id,
            'loan_application_id' => $loan->id,
        ]);

        // Row shows on the outbox page under its type filter.
        $this->actingAs($this->manager())
            ->get(route('admin.notifications-log', ['type' => 'approved']))
            ->assertSee('Your Loan Application Has Been Approved');
    }

    /** @test */
    public function rejected_loan_sends_one_email_and_one_log_row(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->pendingLoan($member);
        $app  = $this->pendingAdminApp($loan);

        $this->actingAs($this->manager())
            ->post(route('applications.reject', $app->id), [
                'reason' => 'Insufficient documentation',
            ]);

        Mail::assertSent(\App\Mail\LoanRejectedMail::class, fn ($mail) => $mail->hasTo($member->email));

        $this->assertSame(1, AppNotificationLog::where('loan_application_id', $loan->id)
            ->where('notification_type', 'rejected')
            ->where('status', 'Sent')
            ->where('channel', 'email')
            ->count());

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'    => $member->email,
            'subject'            => 'Your Loan Application Update',
            'notification_type'  => 'rejected',
            'status'             => 'Sent',
            'channel'            => 'email',
            'user_id'            => $member->id,
            'loan_application_id' => $loan->id,
        ]);

        $this->actingAs($this->manager())
            ->get(route('admin.notifications-log', ['type' => 'rejected']))
            ->assertSee('Your Loan Application Update');
    }

    /** @test */
    public function re_approving_an_approved_application_is_blocked_without_duplicate_log(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->pendingLoan($member);
        $app  = $this->pendingAdminApp($loan);

        $this->actingAs($this->manager())
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        // Second attempt: guard returns 409 and writes nothing.
        $this->actingAs($this->manager())
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ])
            ->assertStatus(409);

        $this->assertSame(1, AppNotificationLog::where('loan_application_id', $loan->id)
            ->where('notification_type', 'approved')
            ->count());
    }

    /** @test */
    public function re_rejecting_a_rejected_application_is_blocked_without_duplicate_log(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->pendingLoan($member);
        $app  = $this->pendingAdminApp($loan);

        $this->actingAs($this->manager())
            ->post(route('applications.reject', $app->id), [
                'reason' => 'Insufficient documentation',
            ]);

        $this->actingAs($this->manager())
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('applications.reject', $app->id), [
                'reason' => 'Insufficient documentation',
            ])
            ->assertStatus(409);

        $this->assertSame(1, AppNotificationLog::where('loan_application_id', $loan->id)
            ->where('notification_type', 'rejected')
            ->count());
    }

    // ─── 3.2 Reminders (due_soon / due_today / overdue) ─────────────────

    /** @test */
    public function due_soon_reminder_sends_one_email_and_one_log_row(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->approvedLoan($member, $this->manilaDate(2));

        $this->actingAs($this->manager())
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        Mail::assertSent(\App\Mail\PaymentReminderMail::class, fn ($mail) => $mail->hasTo($member->email));

        $this->assertSame(1, AppNotificationLog::where('loan_application_id', $loan->id)
            ->where('notification_type', 'due_soon')
            ->where('status', 'Sent')
            ->where('channel', 'email')
            ->count());

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'     => $member->email,
            'notification_type'   => 'due_soon',
            'status'              => 'Sent',
            'channel'             => 'email',
            'user_id'             => $member->id,
            'loan_application_id' => $loan->id,
        ]);

        $this->actingAs($this->manager())
            ->get(route('admin.notifications-log', ['type' => 'due_soon']))
            ->assertSee('Upcoming Payment Due Soon');
    }

    /** @test */
    public function due_today_reminder_sends_one_email_and_one_log_row(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->approvedLoan($member, $this->manilaDate(0));

        $this->actingAs($this->manager())
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        Mail::assertSent(\App\Mail\PaymentReminderMail::class, fn ($mail) => $mail->hasTo($member->email));

        $this->assertSame(1, AppNotificationLog::where('loan_application_id', $loan->id)
            ->where('notification_type', 'due_today')
            ->where('status', 'Sent')
            ->where('channel', 'email')
            ->count());

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'     => $member->email,
            'notification_type'   => 'due_today',
            'status'              => 'Sent',
            'channel'             => 'email',
            'user_id'             => $member->id,
            'loan_application_id' => $loan->id,
        ]);

        $this->actingAs($this->manager())
            ->get(route('admin.notifications-log', ['type' => 'due_today']))
            ->assertSee('Payment Due Today');
    }

    /** @test */
    public function overdue_reminder_sends_one_email_and_one_log_row(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->approvedLoan($member, $this->manilaDate(-10));

        $this->actingAs($this->manager())
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        Mail::assertSent(\App\Mail\PaymentReminderMail::class, fn ($mail) => $mail->hasTo($member->email));

        $this->assertSame(1, AppNotificationLog::where('loan_application_id', $loan->id)
            ->where('notification_type', 'overdue')
            ->where('status', 'Sent')
            ->where('channel', 'email')
            ->count());

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'     => $member->email,
            'notification_type'   => 'overdue',
            'status'              => 'Sent',
            'channel'             => 'email',
            'user_id'             => $member->id,
            'loan_application_id' => $loan->id,
        ]);

        $this->actingAs($this->manager())
            ->get(route('admin.notifications-log', ['type' => 'overdue']))
            ->assertSee('Overdue Payment Reminder');
    }

    /** @test */
    public function running_reminders_twice_writes_no_duplicate_log_rows(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->approvedLoan($member, $this->manilaDate(-10));

        $admin = $this->manager();

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $this->assertSame(1, AppNotificationLog::where('loan_application_id', $loan->id)
            ->where('notification_type', 'overdue')
            ->where('status', 'Sent')
            ->count());

        Mail::assertSent(\App\Mail\PaymentReminderMail::class, 1);
    }

    /** @test */
    public function loan_without_email_logs_skipped_with_reason(): void
    {
        $member = $this->member();
        $member->update(['email' => '']);
        $this->coopMember($member);

        $loan = LoanApplication::factory()->create([
            'user_id'      => $member->id,
            'full_name'    => 'No Email Borrower',
            'email'        => '',
            'loan_type'    => 'regular',
            'amount'       => 5000,
            'status'       => 'approved',
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'term_months'  => 12,
            'remaining_balance' => 800,
            'next_due_date' => $this->manilaDate(-10),
        ]);

        $this->actingAs($this->manager())
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $this->assertDatabaseHas('app_notification_logs', [
            'loan_application_id' => $loan->id,
            'notification_type'   => 'overdue',
            'status'              => 'Skipped',
            'channel'             => 'email',
        ]);

        $row = AppNotificationLog::where('loan_application_id', $loan->id)
            ->where('notification_type', 'overdue')
            ->where('status', 'Skipped')
            ->first();

        $this->assertStringContainsString('No email address on file', (string) $row->error_message);

        Mail::assertNothingSent();
    }

    // ─── 3.3 Payment Received ────────────────────────────────────────────

    /** @test */
    public function payment_received_sends_one_email_and_one_log_row(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->pendingLoan($member);
        $app  = $this->pendingAdminApp($loan);

        $this->actingAs($this->manager())
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        $this->actingAs($this->manager())
            ->post(route('repayments.store'), [
                'application_id' => $app->id,
                'amount'         => 1000,
                'payment_method' => 'Cash',
                'payment_date'   => now()->format('Y-m-d'),
                'proof'          => UploadedFile::fake()->image('proof.jpg'),
            ]);

        Mail::assertSent(\App\Mail\PaymentReceivedMail::class, fn ($mail) => $mail->hasTo($member->email));

        $this->assertSame(1, AppNotificationLog::where('loan_application_id', $loan->id)
            ->where('notification_type', 'payment_received')
            ->where('status', 'Sent')
            ->where('channel', 'email')
            ->count());

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'     => $member->email,
            'subject'             => 'Payment Received',
            'notification_type'   => 'payment_received',
            'status'              => 'Sent',
            'channel'             => 'email',
            'user_id'             => $member->id,
            'loan_application_id' => $loan->id,
        ]);

        $this->actingAs($this->manager())
            ->get(route('admin.notifications-log', ['type' => 'payment_received']))
            ->assertSee('Payment Received');
    }

    /** @test */
    public function payment_on_unapproved_loan_writes_no_log_row(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->pendingLoan($member);
        $app  = $this->pendingAdminApp($loan);

        $this->actingAs($this->manager())
            ->post(route('repayments.store'), [
                'application_id' => $app->id,
                'amount'         => 1000,
                'payment_method' => 'Cash',
                'payment_date'   => now()->format('Y-m-d'),
                'proof'          => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $this->assertSame(0, AppNotificationLog::where('loan_application_id', $loan->id)->count());
        Mail::assertNothingSent();
    }

    // ─── Portal-less loan: email resolved from the loan row, not the portal user ───

    /** @test */
    public function portal_less_loan_payment_email_is_sent_to_loan_email(): void
    {
        $email = 'portalless@example.com';
        $loan  = $this->portalLessLoan($email);
        $app   = $this->approvedAdminApp($loan);

        $this->actingAs($this->manager())
            ->post(route('repayments.store'), [
                'application_id' => $app->id,
                'amount'         => 1000,
                'payment_method' => 'Cash',
                'payment_date'   => now()->format('Y-m-d'),
                'proof'          => UploadedFile::fake()->image('proof.jpg'),
            ]);

        Mail::assertSent(\App\Mail\PaymentReceivedMail::class, fn ($mail) => $mail->hasTo($email));

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'     => $email,
            'subject'             => 'Payment Received',
            'notification_type'   => 'payment_received',
            'status'              => 'Sent',
            'channel'             => 'email',
            'user_id'             => null,
            'loan_application_id' => $loan->id,
        ]);
    }

    /** @test */
    public function payment_with_no_resolvable_email_logs_skipped_with_reason(): void
    {
        $loan = $this->portalLessLoan('');
        $app  = $this->approvedAdminApp($loan);

        $this->actingAs($this->manager())
            ->post(route('repayments.store'), [
                'application_id' => $app->id,
                'amount'         => 1000,
                'payment_method' => 'Cash',
                'payment_date'   => now()->format('Y-m-d'),
                'proof'          => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $this->assertDatabaseHas('app_notification_logs', [
            'notification_type'   => 'payment_received',
            'status'              => 'Skipped',
            'channel'             => 'email',
            'loan_application_id' => $loan->id,
        ]);

        $row = AppNotificationLog::where('notification_type', 'payment_received')
            ->where('status', 'Skipped')
            ->where('loan_application_id', $loan->id)
            ->first();

        $this->assertStringContainsString('no email on file', (string) $row->error_message);

        Mail::assertNothingSent();
    }

    /** @test */
    public function portal_less_payment_writes_exactly_one_outbox_row_and_no_in_app_notification(): void
    {
        $loan = $this->portalLessLoan('portalless@example.com');
        $app  = $this->approvedAdminApp($loan);

        $this->actingAs($this->manager())
            ->post(route('repayments.store'), [
                'application_id' => $app->id,
                'amount'         => 1000,
                'payment_method' => 'Cash',
                'payment_date'   => now()->format('Y-m-d'),
                'proof'          => UploadedFile::fake()->image('proof.jpg'),
            ]);

        // Exactly ONE outbox row for this loan
        $this->assertSame(1, AppNotificationLog::where('loan_application_id', $loan->id)
            ->where('notification_type', 'payment_received')
            ->count());

        // Portal-less loan has no portal user → no in-app notification row
        $this->assertSame(0, \App\Models\AppNotification::where('loan_application_id', $loan->id)->count());
    }

    /** @test */
    public function portal_member_payment_writes_one_outbox_row_and_one_in_app_notification(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->pendingLoan($member);
        $app  = $this->pendingAdminApp($loan);

        $this->actingAs($this->manager())
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        $this->actingAs($this->manager())
            ->post(route('repayments.store'), [
                'application_id' => $app->id,
                'amount'         => 1000,
                'payment_method' => 'Cash',
                'payment_date'   => now()->format('Y-m-d'),
                'proof'          => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $this->assertSame(1, AppNotificationLog::where('loan_application_id', $loan->id)
            ->where('notification_type', 'payment_received')
            ->count());

        $this->assertSame(1, \App\Models\AppNotification::where('loan_application_id', $loan->id)
            ->where('type', 'payment_received')
            ->count());
    }

    // ─── 3.4 Membership approved / rejected ──────────────────────────────

    /** @test */
    public function membership_approved_sends_one_email_and_one_log_row(): void
    {
        $applicant = User::factory()->create([
            'usertype' => 'user',
            'email'    => 'applicant@example.com',
        ]);

        $application = MemberApplication::create([
            'id_front_path' => 'front-test.jpg',
            'id_back_path'  => 'back-test.jpg',
            'surname'        => 'Doe',
            'first_name'     => 'Jane',
            'email'          => 'applicant@example.com',
            'contact_number' => '09171234567',
            'status'         => 'pending',
            'user_id'        => $applicant->id,
        ]);

        $this->actingAs($this->manager())
            ->post(route('memberships.approve', $application->id));

        Mail::assertSent(\App\Mail\MemberApprovedMail::class, fn ($mail) => $mail->hasTo('applicant@example.com'));

        $this->assertSame(1, AppNotificationLog::where('notification_type', 'membership_approved')
            ->where('status', 'Sent')
            ->count());

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'    => 'applicant@example.com',
            'subject'            => 'Your SDCC membership is approved - here is your Member ID',
            'notification_type'  => 'membership_approved',
            'status'             => 'Sent',
            'channel'            => 'email',
            'user_id'            => $applicant->id,
        ]);

        $this->actingAs($this->manager())
            ->get(route('admin.notifications-log', ['type' => 'membership_approved']))
            ->assertSee('Your SDCC membership is approved');
    }

    /** @test */
    public function membership_rejected_sends_one_email_and_one_log_row(): void
    {
        $applicant = User::factory()->create([
            'usertype' => 'user',
            'email'    => 'applicant@example.com',
        ]);

        $application = MemberApplication::create([
            'id_front_path' => 'front-test.jpg',
            'id_back_path'  => 'back-test.jpg',
            'surname'        => 'Doe',
            'first_name'     => 'Jane',
            'email'          => 'applicant@example.com',
            'contact_number' => '09171234567',
            'status'         => 'pending',
            'user_id'        => $applicant->id,
        ]);

        $this->actingAs($this->manager())
            ->post(route('memberships.reject', $application->id), [
                'rejection_reason' => 'Incomplete supporting documents',
            ]);

        Mail::assertSent(\App\Mail\MemberRejectedMail::class, fn ($mail) => $mail->hasTo('applicant@example.com'));

        $this->assertSame(1, AppNotificationLog::where('notification_type', 'membership_rejected')
            ->where('status', 'Sent')
            ->count());

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'    => 'applicant@example.com',
            'subject'            => 'Your SDCC membership application was not approved',
            'notification_type'  => 'membership_rejected',
            'status'             => 'Sent',
            'channel'            => 'email',
            'user_id'            => $applicant->id,
        ]);

        $this->actingAs($this->manager())
            ->get(route('admin.notifications-log', ['type' => 'membership_rejected']))
            ->assertSee('Your SDCC membership application was not approved');
    }

    // ─── Failing transport writes Failed rows without breaking actions ───

    /** @test */
    public function failing_transport_on_approval_writes_failed_log_and_does_not_break_action(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->pendingLoan($member);
        $app  = $this->pendingAdminApp($loan);

        $this->makeMailerThrow();

        $this->actingAs($this->manager())
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        $this->assertDatabaseHas('app_notification_logs', [
            'notification_type'  => 'approved',
            'status'             => 'Failed',
            'channel'            => 'email',
            'loan_application_id' => $loan->id,
        ]);

        $row = AppNotificationLog::where('notification_type', 'approved')
            ->where('status', 'Failed')
            ->first();

        $this->assertStringContainsString('mail transport down', (string) $row->error_message);
        $this->assertSame($member->email, $row->recipient_email);
    }

    /** @test */
    public function failing_transport_on_reminder_writes_failed_log_and_run_still_reports(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->approvedLoan($member, $this->manilaDate(-10));

        $this->makeMailerThrow();

        $this->actingAs($this->manager())
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'))
            ->assertSuccessful();

        $this->assertDatabaseHas('app_notification_logs', [
            'notification_type'   => 'overdue',
            'status'              => 'Failed',
            'channel'             => 'email',
            'loan_application_id' => $loan->id,
        ]);

        $row = AppNotificationLog::where('notification_type', 'overdue')
            ->where('status', 'Failed')
            ->first();

        $this->assertStringContainsString('mail transport down', (string) $row->error_message);
    }

    /** @test */
    public function failing_transport_on_payment_received_writes_failed_log(): void
    {
        $member = $this->member();
        $this->coopMember($member);
        $loan = $this->pendingLoan($member);
        $app  = $this->pendingAdminApp($loan);

        $this->actingAs($this->manager())
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        $this->makeMailerThrow();

        $this->actingAs($this->manager())
            ->post(route('repayments.store'), [
                'application_id' => $app->id,
                'amount'         => 1000,
                'payment_method' => 'Cash',
                'payment_date'   => now()->format('Y-m-d'),
                'proof'          => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $this->assertDatabaseHas('app_notification_logs', [
            'notification_type'   => 'payment_received',
            'status'              => 'Failed',
            'channel'             => 'email',
            'loan_application_id' => $loan->id,
        ]);

        $row = AppNotificationLog::where('notification_type', 'payment_received')
            ->where('status', 'Failed')
            ->first();

        $this->assertStringContainsString('mail transport down', (string) $row->error_message);

        // Mail failure must not break recording — the payment is still saved.
        $this->assertDatabaseHas('payments', [
            'loan_application_id' => $loan->id,
            'amount_paid'         => 1000,
        ]);
    }

    /** @test */
    public function failing_transport_on_membership_approval_writes_failed_log(): void
    {
        $applicant = User::factory()->create([
            'usertype' => 'user',
            'email'    => 'applicant@example.com',
        ]);

        $application = MemberApplication::create([
            'id_front_path' => 'front-test.jpg',
            'id_back_path'  => 'back-test.jpg',
            'surname'        => 'Doe',
            'first_name'     => 'Jane',
            'email'          => 'applicant@example.com',
            'contact_number' => '09171234567',
            'status'         => 'pending',
            'user_id'        => $applicant->id,
        ]);

        $this->makeMailerThrow();

        $this->actingAs($this->manager())
            ->post(route('memberships.approve', $application->id));

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'   => 'applicant@example.com',
            'notification_type' => 'membership_approved',
            'status'            => 'Failed',
        ]);

        $row = AppNotificationLog::where('notification_type', 'membership_approved')
            ->where('status', 'Failed')
            ->first();

        $this->assertStringContainsString('mail transport down', (string) $row->error_message);
    }

    /** @test */
    public function failing_transport_on_membership_rejection_writes_failed_log(): void
    {
        $applicant = User::factory()->create([
            'usertype' => 'user',
            'email'    => 'applicant@example.com',
        ]);

        $application = MemberApplication::create([
            'id_front_path' => 'front-test.jpg',
            'id_back_path'  => 'back-test.jpg',
            'surname'        => 'Doe',
            'first_name'     => 'Jane',
            'email'          => 'applicant@example.com',
            'contact_number' => '09171234567',
            'status'         => 'pending',
            'user_id'        => $applicant->id,
        ]);

        $this->makeMailerThrow();

        $this->actingAs($this->manager())
            ->post(route('memberships.reject', $application->id), [
                'rejection_reason' => 'Incomplete supporting documents',
            ]);

        $this->assertDatabaseHas('app_notification_logs', [
            'recipient_email'   => 'applicant@example.com',
            'notification_type' => 'membership_rejected',
            'status'            => 'Failed',
        ]);
    }

    // ─── 3.5 / 3.6 Type dropdown contains only real types ────────────────

    /** @test */
    public function type_dropdown_lists_only_types_that_can_really_appear(): void
    {
        $html = $this->actingAs($this->manager())
            ->get(route('admin.notifications-log'))
            ->getContent();

        foreach (['approved', 'rejected', 'due_soon', 'due_today', 'overdue', 'payment_received', 'membership_approved', 'membership_rejected'] as $type) {
            $this->assertStringContainsString('value="' . $type . '"', $html);
        }

        // Welcome is in-app only (no email is sent), so it must not appear.
        $this->assertStringNotContainsString('value="welcome"', $html);
        $this->assertStringNotContainsString('value="reminder_failed"', $html);
        $this->assertStringNotContainsString('value="reminder_summary"', $html);
        $this->assertStringNotContainsString('value="system"', $html);
    }

    // ─── 3.7 / 3.8 Page: stats, search grouping, Skipped status ──────────

    /** @test */
    public function stats_cards_use_full_filtered_counts_and_show_skipped(): void
    {
        $manager = $this->manager();

        $now = now();
        foreach (range(1, 30) as $i) {
            AppNotificationLog::create([
                'recipient_email'   => "user{$i}@example.com",
                'subject'           => "Test {$i}",
                'notification_type' => 'approved',
                'channel'           => 'email',
                'status'            => 'Sent',
                'sent_at'           => $now,
            ]);
        }
        foreach (range(1, 10) as $i) {
            AppNotificationLog::create([
                'recipient_email'   => "skip{$i}@example.com",
                'subject'           => "Test Skip {$i}",
                'notification_type' => 'overdue',
                'channel'           => 'email',
                'status'            => 'Skipped',
                'error_message'     => 'No email address on file',
            ]);
        }
        foreach (range(1, 7) as $i) {
            AppNotificationLog::create([
                'recipient_email'   => "fail{$i}@example.com",
                'subject'           => "Test Fail {$i}",
                'notification_type' => 'due_soon',
                'channel'           => 'email',
                'status'            => 'Failed',
                'error_message'     => 'boom',
            ]);
        }

        $response = $this->actingAs($manager)->get(route('admin.notifications-log'));
        $content  = $response->getContent();

        $response->assertOk();

        // Total 47, Sent 30, Failed 7, Skipped 10, Today 47, error rate ~18.9%.
        $this->assertStringContainsString('>47<', $content);
        $this->assertStringContainsString('>30<', $content);
        $this->assertStringContainsString('>7<', $content);
        $this->assertStringContainsString('>10<', $content);
        $this->assertStringContainsString('18.9%', $content);
    }

    /** @test */
    public function search_and_status_filters_are_grouped_not_leaking(): void
    {
        $manager = $this->manager();

        AppNotificationLog::create([
            'recipient_email'   => 'a@example.com',
            'subject'           => 'Approved loan subject',
            'notification_type' => 'approved',
            'channel'           => 'email',
            'status'            => 'Sent',
        ]);
        AppNotificationLog::create([
            'recipient_email'   => 'b@example.com',
            'subject'           => 'Failed reminder subject',
            'notification_type' => 'overdue',
            'channel'           => 'email',
            'status'            => 'Failed',
        ]);

        // Search "Approved" + status "Failed" must yield zero rows, not the
        // union of both filters.
        $response = $this->actingAs($manager)
            ->get(route('admin.notifications-log', ['search' => 'Approved', 'status' => 'Failed']));

        $response->assertOk();
        $this->assertStringNotContainsString('Approved loan subject', $response->getContent());
        $this->assertStringContainsString('No notification logs found.', $response->getContent());
    }

    /** @test */
    public function status_filter_includes_skipped_and_renders_reason(): void
    {
        $manager = $this->manager();

        AppNotificationLog::create([
            'notification_type' => 'overdue',
            'status'            => 'Skipped',
            'recipient_email'   => 'no-email@example.com',
            'subject'           => 'Overdue Payment Reminder',
            'channel'           => 'email',
            'error_message'     => 'No email address on file',
        ]);
        AppNotificationLog::create([
            'notification_type' => 'overdue',
            'status'            => 'Sent',
            'recipient_email'   => 'has-email@example.com',
            'subject'           => 'Overdue Payment Reminder',
            'channel'           => 'email',
        ]);

        $response = $this->actingAs($manager)
            ->get(route('admin.notifications-log', ['status' => 'Skipped']));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('No email address on file', $content);
        $this->assertStringNotContainsString('has-email@example.com', $content);

        // Skipped badge class renders with the neutral pill.
        $this->assertStringContainsString('nl-pill-skipped', $content);
    }

    /** @test */
    public function page_shows_sent_and_failed_rows_for_each_type_filter(): void
    {
        $manager = $this->manager();

        AppNotificationLog::create([
            'notification_type' => 'approved',
            'status'            => 'Sent',
            'subject'           => 'Approved subject here',
            'channel'           => 'email',
            'recipient_email'   => 'approved@example.com',
        ]);
        AppNotificationLog::create([
            'notification_type' => 'rejected',
            'status'            => 'Failed',
            'subject'           => 'Rejected subject here',
            'channel'           => 'email',
            'recipient_email'   => 'rejected@example.com',
            'error_message'     => 'boom',
        ]);

        $this->actingAs($manager)
            ->get(route('admin.notifications-log', ['type' => 'approved']))
            ->assertSee('Approved subject here');

        $this->actingAs($manager)
            ->get(route('admin.notifications-log', ['type' => 'rejected']))
            ->assertSee('Rejected subject here');
    }

    // ─── A.4 / D: type labels, member/loan refs, modal data ───────────────

    /** @test */
    public function outbox_page_shows_type_labels_and_member_loan_refs(): void
    {
        $manager = $this->manager();
        $member  = $this->member();
        $coop    = $this->coopMember($member);
        $loan    = $this->pendingLoan($member);
        $app     = $this->pendingAdminApp($loan);

        $this->actingAs($manager)
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        $response = $this->actingAs($manager)
            ->get(route('admin.notifications-log', ['type' => 'approved']));

        $response->assertOk();
        $content  = $response->getContent();

        // Type label from the map, not the raw snake_case key
        $this->assertStringContainsString('Loan Approved', $content);
        $this->assertStringNotContainsString('>approved<', $content);

        // Member ref shows the member id and name from the eager-loaded user
        $this->assertStringContainsString($coop->member_id, $content);
        $this->assertStringContainsString($member->name, $content);

        // Loan ref shows the application's APP-xxxx code
        $this->assertStringContainsString($app->app_id, $content);

        // New column headers: Date, Member, Recipient, Subject, Loan, Type, Status
        $this->assertStringContainsString('<th>Date</th>', $content);
        $this->assertStringContainsString('<th>Member</th>', $content);
        $this->assertStringContainsString('<th>Recipient</th>', $content);
        $this->assertStringContainsString('<th>Subject</th>', $content);
        $this->assertStringContainsString('<th>Loan</th>', $content);
        $this->assertStringContainsString('<th>Type</th>', $content);
        $this->assertStringContainsString('<th>Status</th>', $content);
        // Old headers removed (Channel filter label stays, only the column is gone)
        $this->assertStringNotContainsString('<th>Member Ref</th>', $content);
        $this->assertStringNotContainsString('<th>Loan Ref</th>', $content);
        $this->assertStringNotContainsString('<th>Channel</th>', $content);
        $this->assertStringNotContainsString('<th>Sent At</th>', $content);
        $this->assertStringNotContainsString('<th>Error</th>', $content);
    }

    /** @test */
    public function outbox_page_returns_modal_data_for_sent_failed_and_skipped_rows(): void
    {
        $manager = $this->manager();
        $member  = $this->member();
        $coop    = $this->coopMember($member);
        $loan    = $this->pendingLoan($member);
        $app     = $this->pendingAdminApp($loan);

        // Sent row (approval flow)
        $this->actingAs($manager)
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        // Failed row
        AppNotificationLog::create([
            'recipient_email'     => $member->email,
            'subject'             => 'Payment Received - Thank You',
            'notification_type'   => 'payment_received',
            'channel'             => 'email',
            'status'              => 'Failed',
            'error_message'       => 'mail transport down',
            'user_id'             => $member->id,
            'loan_application_id' => $loan->id,
        ]);

        // Skipped row
        AppNotificationLog::create([
            'recipient_email'     => null,
            'subject'             => 'Payment Received - Thank You',
            'notification_type'   => 'payment_received',
            'channel'             => 'email',
            'status'              => 'Skipped',
            'error_message'       => 'no email on file',
            'user_id'             => null,
            'loan_application_id' => $loan->id,
        ]);

        $response = $this->actingAs($manager)->get(route('admin.notifications-log'));
        $content  = $response->getContent();

        $response->assertOk();

        // Title format is "<Type label> email"; no raw db id in a title
        $this->assertStringNotContainsString('Notification #', $content);
        $this->assertStringContainsString('Loan Approved', $content);
        $this->assertStringContainsString('Payment Received', $content);

        // Status pills for all three statuses
        $this->assertStringContainsString('nl-pill-sent', $content);
        $this->assertStringContainsString('nl-pill-failed', $content);
        $this->assertStringContainsString('nl-pill-skipped', $content);

        // Loan link + member name + member id present as modal data
        $this->assertStringContainsString($app->app_id, $content);
        $this->assertStringContainsString($member->name, $content);
        $this->assertStringContainsString($coop->member_id, $content);

        // Error text present only for the Failed and Skipped rows
        $this->assertStringContainsString('mail transport down', $content);
        $this->assertStringContainsString('no email on file', $content);

        // New column headers present (old 9-column layout removed)
        $this->assertStringContainsString('<th>Date</th>', $content);
        $this->assertStringContainsString('<th>Member</th>', $content);
        $this->assertStringContainsString('<th>Recipient</th>', $content);
        $this->assertStringContainsString('<th>Subject</th>', $content);
        $this->assertStringContainsString('<th>Loan</th>', $content);
        $this->assertStringContainsString('<th>Type</th>', $content);
        $this->assertStringContainsString('<th>Status</th>', $content);
        $this->assertStringNotContainsString('<th>Member Ref</th>', $content);
        $this->assertStringNotContainsString('<th>Loan Ref</th>', $content);
        $this->assertStringNotContainsString('<th>Channel</th>', $content);
        $this->assertStringNotContainsString('<th>Sent At</th>', $content);
        $this->assertStringNotContainsString('<th>Error</th>', $content);
    }
}
