<?php

namespace Tests\Feature;

use App\Models\AppNotificationLog;
use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders real pages to static HTML files under tests/tmp/ so the Playwright
 * static-page helper (tests/Browser/static-page.test.cjs) can load them in a
 * real browser WITHOUT a server or a login.
 *
 * Usage:
 *   vendor/bin/phpunit --filter StaticPageDumpTest
 *   node tests/Browser/static-page.test.cjs notif      # assertions for /admin/notifications-log
 *   node tests/Browser/static-page.test.cjs repayments # assertions for /repayments
 *   node tests/Browser/static-page.test.cjs all
 */
class StaticPageDumpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @mkdir(base_path('tests/tmp'), 0777, true);
    }

    private function manager(): User
    {
        return User::factory()->create([
            'usertype' => 'branch_manager',
            'email'    => 'manager' . uniqid() . '@example.com',
        ]);
    }

    /** Loan id 10, mirror id 8, app_id APP-0009 — ids deliberately different. */
    private function approvedLoanWithMirror(): LoanApplication
    {
        $member = CoopMember::factory()->create([
            'member_id' => 'SDCC-0001',
            'email'     => 'borrower@example.com',
            'user_id'   => null,
        ]);
        $user = User::factory()->create([
            'usertype' => 'member',
            'email'    => 'borrower@example.com',
        ]);
        $member->update(['user_id' => $user->id]);

        $loan = LoanApplication::factory()->create([
            'id'                 => 10,
            'user_id'            => $user->id,
            'email'              => 'borrower@example.com',
            'full_name'          => 'Zoro Test Borrower',
            'loan_type'          => 'regular',
            'amount'             => 5000,
            'status'             => 'approved',
            'term_months'        => 12,
            'remaining_balance'  => 4000,
            'next_due_date'      => now()->addDays(2),
        ]);
        Application::factory()->create([
            'id'                  => 8,
            'loan_application_id' => $loan->id,
            'app_id'              => 'APP-0009',
            'applicant'           => 'Zoro Test Borrower',
            'status'              => 'Approved',
            'remaining_balance'   => 4000,
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'term_months'         => 12,
        ]);

        return $loan;
    }

    /** @test */
    public function dump_notifications_log_page(): void
    {
        $manager = $this->manager();
        $loan    = $this->approvedLoanWithMirror();

        AppNotificationLog::create([
            'notification_type' => 'approved',
            'channel'           => 'email',
            'status'            => 'Sent',
            'recipient_email'   => 'borrower@example.com',
            'subject'           => 'Your loan APP-0009 has been approved',
            'sent_at'           => now(),
            'loan_application_id' => $loan->id,
            'user_id'           => $loan->user_id,
        ]);
        AppNotificationLog::create([
            'notification_type' => 'rejected',
            'channel'           => 'email',
            'status'            => 'Failed',
            'recipient_email'   => 'borrower@example.com',
            'subject'           => 'Your loan application was rejected',
            'error_message'     => 'SMTP timeout',
            'sent_at'           => now(),
            'loan_application_id' => $loan->id,
            'user_id'           => $loan->user_id,
        ]);
        AppNotificationLog::create([
            'notification_type' => 'membership_approved',
            'channel'           => 'email',
            'status'            => 'Sent',
            'recipient_email'   => 'other@example.com',
            'subject'           => 'Membership approved',
            'sent_at'           => now(),
        ]);

        $html = $this->actingAs($manager)->get(route('admin.notifications-log'))->getContent();
        file_put_contents(base_path('tests/tmp/notifications-log.html'), $html);

        $this->assertStringContainsString('Email Notification Outbox', $html);
        $this->assertStringContainsString('APP-0009', $html);
    }

    /** @test */
    public function dump_repayments_page(): void
    {
        $manager = $this->manager();
        $loan    = $this->approvedLoanWithMirror();

        Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'amount_paid'         => 1000,
            'remaining_balance_after' => 3000,
            'reference_no'        => 'REP-TEST-1',
            'payment_date'        => now(),
            'payment_method'      => 'Cash',
        ]);

        $html = $this->actingAs($manager)->get('/repayments')->getContent();
        file_put_contents(base_path('tests/tmp/repayments.html'), $html);

        $this->assertStringContainsString('Repayments', $html);
        $this->assertStringContainsString('APP-0009', $html);
    }
}
