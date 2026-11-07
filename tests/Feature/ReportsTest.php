<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Reports module (Objective 3): page rendering, role gating, and exports.
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Roles allowed to open the reports page (branch manager owns VIEW_REPORTS).
     */
    public static function staffRoles(): array
    {
        return [
            'admin'               => [Permissions::ROLE_ADMIN],
            'branch_manager'      => [Permissions::ROLE_BRANCH_MANAGER],
            'loan_officer'        => [Permissions::ROLE_LOAN_OFFICER],
            'credit_investigator' => [Permissions::ROLE_CREDIT_INVESTIGATOR],
        ];
    }

    /**
     * Only branch_manager may open the reports page; every other role gets 403.
     *
     * @dataProvider staffRoles
     */
    public function test_reports_page_is_branch_manager_only(string $role): void
    {
        $user = User::factory()->create(['usertype' => $role]);

        $response = $this->actingAs($user)->get('/reports');

        if ($role === Permissions::ROLE_BRANCH_MANAGER) {
            $response->assertOk();
            $response->assertViewHas('filters');
            $response->assertViewHas('loanSummary');
            $response->assertViewHas('approvedRecs');
            $response->assertViewHas('borrowerPerf');
        } else {
            $response->assertForbidden();
        }
    }

    /**
     * Get a date N days from today in Asia/Manila timezone.
     */
    private function getManilaDate(int $daysOffset): string
    {
        return Carbon::today('Asia/Manila')->addDays($daysOffset)->toDateString();
    }

    /**
     * Member role cannot open the reports page.
     */
    public function test_member_cannot_open_reports_page(): void
    {
        $user = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER]);

        $this->actingAs($user)->get('/reports')->assertForbidden();
    }

    /**
     * The rendered page contains the report-type selector and the three report
     * section headings when data exists.
     */
    public function test_reports_page_renders_report_sections(): void
    {
        $manager = User::factory()->create(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);

        $member = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER]);
        CoopMember::factory()->create(['user_id' => $member->id, 'full_name' => 'Test Borrower']);

        LoanApplication::factory()->create([
            'user_id' => $member->id,
            'full_name' => 'Test Borrower',
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'risk_level' => 'Low',
        ]);

        $response = $this->actingAs($manager)->get('/reports');
        $response->assertOk();

        $content = $response->getContent();
        // The blade renders Reports & Analytics; assert against the decoded label
        $this->assertStringContainsString('Reports & Analytics', html_entity_decode($content));
        $this->assertStringContainsString('Loan Summary', $content);
        $this->assertStringContainsString('Approved Records', $content);
        $this->assertStringContainsString('Borrower Performance', $content);
    }

    /**
     * PDF export returns a downloadable response for each report type.
     *
     * @dataProvider reportTypes
     */
    public function test_export_pdf_returns_download(string $type): void
    {
        $manager = User::factory()->create(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);

        $response = $this->actingAs($manager)->get('/reports/export-pdf', [
            'report_type' => $type,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * Excel export returns a downloadable response for each report type.
     *
     * @dataProvider reportTypes
     */
    public function test_export_excel_returns_download(string $type): void
    {
        $manager = User::factory()->create(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);

        $response = $this->actingAs($manager)->get('/reports/export-excel', [
            'report_type' => $type,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
    }

    /**
     * Non-branch-manager roles cannot export.
     *
     * @dataProvider staffRoles
     */
    public function test_export_is_branch_manager_only(string $role): void
    {
        $user = User::factory()->create(['usertype' => $role]);

        if ($role === Permissions::ROLE_BRANCH_MANAGER) {
            // branch manager access is covered by test_export_*_returns_download
            $this->assertTrue(Permissions::has($user, Permissions::VIEW_REPORTS));
            return;
        }

        $this->actingAs($user)->get('/reports/export-pdf')->assertForbidden();
        $this->actingAs($user)->get('/reports/export-excel')->assertForbidden();
    }

    /**
     * Issue A: early payment (before due date) counts as on-time;
     * late payment (within 30 days) counts as late, not overdue.
     */
    /** @test */
    public function borrower_performance_early_and_late_not_overdue(): void
    {
        $manager = User::factory()->create(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);
        $memberUser = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER]);
        CoopMember::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'status' => CoopMember::STATUS_ACTIVE,
        ]);

        // Loan: next payment due 2026-03-15 → payments 1 & 2 due on 2026-01-15 & 2026-02-15
        $loan = LoanApplication::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'risk_level' => 'Low',
            'term_months' => 12,
            'next_due_date' => '2026-03-15',
            'remaining_balance' => 4000,
        ]);

        Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'user_id' => $memberUser->id,
            'amount_paid' => 500,
            'payment_date' => '2026-01-10', // paid 5 days before Jan 15 due → on-time
        ]);

        Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'user_id' => $memberUser->id,
            'amount_paid' => 500,
            'payment_date' => '2026-02-20', // paid 5 days after Feb 15 due → late, not overdue
        ]);

        $response = $this->actingAs($manager)->get('/reports?report_type=borrower_performance');
        $response->assertOk();
        $borrowerPerf = $response->viewData('borrowerPerf');
        $row = $borrowerPerf->first();
        $this->assertNotNull($row);
        $this->assertSame(1, $row->on_time_count);
        $this->assertSame(1, $row->late_count);
        $this->assertSame(0, $row->overdue_count);
        $this->assertSame('Good', $row->performance_label);
    }

    /** @test */
    public function borrower_performance_overdue_when_more_than_30_days_late(): void
    {
        $manager = User::factory()->create(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);
        $memberUser = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER]);
        CoopMember::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'status' => CoopMember::STATUS_ACTIVE,
        ]);

        $loan = LoanApplication::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'risk_level' => 'Low',
            'term_months' => 12,
            'next_due_date' => '2026-03-15',
            'remaining_balance' => 4000,
        ]);

        // Payment 1 due 2026-01-15; paid 2026-03-25 → 69 days late → overdue
        Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'user_id' => $memberUser->id,
            'amount_paid' => 500,
            'payment_date' => '2026-03-25', // paid 69 days after Jan 15 due → overdue
        ]);

        $response = $this->actingAs($manager)->get('/reports?report_type=borrower_performance');
        $response->assertOk();
        $borrowerPerf = $response->viewData('borrowerPerf');
        $row = $borrowerPerf->first();
        $this->assertNotNull($row);
        $this->assertSame(0, $row->on_time_count);
        $this->assertSame(1, $row->late_count);
        $this->assertSame(1, $row->overdue_count);
        $this->assertSame('Poor', $row->performance_label);
    }

    /** @test
     * Issue A: loan with 3 recorded payments where payment 1 was 10 days late.
     * Anchors due dates backwards from next_due_date; verifies late counting.
     */
    public function borrower_performance_three_payments_first_payment_ten_days_late(): void
    {
        $manager = User::factory()->create(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);
        $memberUser = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER]);
        CoopMember::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'status' => CoopMember::STATUS_ACTIVE,
        ]);

        // Loan with 3 recorded payments; next_due_date anchors the schedule backward.
        // N=3, next_due_date='2026-04-15' → due dates: 2026-01-15, 2026-02-15, 2026-03-15.
        $loan = LoanApplication::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'risk_level' => 'Low',
            'term_months' => 12,
            'next_due_date' => '2026-04-15',
            'remaining_balance' => 4000,
        ]);

        // Payment 1: due 2026-01-15, paid 2026-01-25 → 10 days late (late, not on-time)
        Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'user_id' => $memberUser->id,
            'amount_paid' => 500,
            'payment_date' => '2026-01-25',
        ]);

        // Payment 2: due 2026-02-15, paid 2026-02-15 → on-time
        Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'user_id' => $memberUser->id,
            'amount_paid' => 500,
            'payment_date' => '2026-02-15',
        ]);

        // Payment 3: due 2026-03-15, paid 2026-03-15 → on-time
        Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'user_id' => $memberUser->id,
            'amount_paid' => 500,
            'payment_date' => '2026-03-15',
        ]);

        $response = $this->actingAs($manager)->get('/reports?report_type=borrower_performance');
        $response->assertOk();
        $borrowerPerf = $response->viewData('borrowerPerf');
        $row = $borrowerPerf->first();
        $this->assertNotNull($row);
        // 2 on-time, 1 late (payment 1), 0 overdue
        $this->assertSame(2, $row->on_time_count);
        $this->assertSame(1, $row->late_count);
        $this->assertSame(0, $row->overdue_count);
        $this->assertSame('Good', $row->performance_label);
    }

    /** @test */
    public function borrower_performance_uses_real_payment_service_next_due_date(): void
    {
        $manager = User::factory()->create(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);
        $memberUser = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER]);
        CoopMember::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'status' => CoopMember::STATUS_ACTIVE,
        ]);

        // Loan: NO next_due_date set by hand. created_at anchors PaymentService's
        // first-due-date fallback (created_at->endOfMonth = 2025-12-31).
        // EMI for 5000/12 is ~444, so each 500 payment covers one installment.
        // After 3 payments: installmentsCovered = 3 → next_due_date = Mar 31, 2026
        // Due dates: Dec 31, Jan 31, Feb 28 → next unpaid: Mar 31
        // Payment 1: due Dec 31, paid Jan 11 → 11 days late
        // Payment 2: due Jan 31, paid Jan 30 → 1 day early (on-time)
        // Payment 3: due Feb 28, paid Feb 25 → 3 days early (on-time)
        // Expected: on_time = 2, late = 1, overdue = 0
        $loan = LoanApplication::factory()->create([
            'user_id'             => $memberUser->id,
            'full_name'           => 'Test Borrower',
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'approved',
            'risk_level'          => 'Low',
            'term_months'         => 12,
            'created_at'          => '2025-12-10 10:00:00',
            'remaining_balance'   => 4000,
        ]);

        // Record 3 payments through the real PaymentService
        $paymentService = app(\App\Services\PaymentService::class);

        // Record all 3 payments, then recompute loan state once.
        // PaymentService computes next_due_date from the full payment set:
        // totalPaid = 1500, EMI = 444.24, installmentsCovered = 3
        // firstDueDate = created_at->endOfMonth = 2025-12-31
        // next_due_date = 2025-12-31 + 3 months = 2026-03-31
        Payment::create([
            'loan_application_id' => $loan->id,
            'user_id'             => $memberUser->id,
            'amount_paid'         => 500,
            'payment_date'        => '2026-01-11', // late vs Dec 31 due
        ]);

        Payment::create([
            'loan_application_id' => $loan->id,
            'user_id'             => $memberUser->id,
            'amount_paid'         => 500,
            'payment_date'        => '2026-01-30', // on-time vs Jan 31 due
        ]);

        Payment::create([
            'loan_application_id' => $loan->id,
            'user_id'             => $memberUser->id,
            'amount_paid'         => 500,
            'payment_date'        => '2026-02-25', // early vs Feb 28 due
        ]);

        $paymentService->recomputeLoanState($loan);

        // Call the report logic
        $response = $this->actingAs($manager)->get('/reports?report_type=borrower_performance');
        $response->assertOk();
        $borrowerPerf = $response->viewData('borrowerPerf');
        $row = $borrowerPerf->first();

        $this->assertNotNull($row);
        $this->assertSame(2, $row->on_time_count, 'Payment 2 and 3 should be on-time');
        $this->assertSame(1, $row->late_count, 'Payment 1 should be late');
        $this->assertSame(0, $row->overdue_count);
        $this->assertSame('Good', $row->performance_label);
    }

    /** @test */
    public function end_date_filter_includes_record_created_on_end_date(): void
    {
        $manager = User::factory()->create(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);
        $memberUser = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER]);
        CoopMember::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'status' => CoopMember::STATUS_ACTIVE,
        ]);

        $loan = LoanApplication::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'risk_level' => 'Low',
        ]);

        DB::table('loan_applications')->where('id', $loan->id)->update(['created_at' => '2026-01-15 10:00:00']);

        $response = $this->actingAs($manager)->get('/reports?report_type=loan_summary&start_date=2026-01-01&end_date=2026-01-15');
        $response->assertOk();
        $loanSummary = $response->viewData('loanSummary');
        $this->assertSame(1, $loanSummary['total_count'], 'Loan created on end date should be included');
    }

    /** @test */
    public function borrower_performance_only_counts_approved_for_approved_amount_and_outstanding(): void
    {
        $manager = User::factory()->create(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);
        $memberUser = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER]);
        CoopMember::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'status' => CoopMember::STATUS_ACTIVE,
        ]);

        // Approved loan: amount 5000, remaining_balance 1000
        $approvedLoan = LoanApplication::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'risk_level' => 'Low',
            'term_months' => 12,
            'next_due_date' => '2026-02-15',
            'remaining_balance' => 1000,
        ]);

        // Pending loan: amount 3000, remaining_balance 3000 (NOT counted in approved amount)
        $pendingLoan = LoanApplication::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'loan_type' => 'regular',
            'amount' => 3000,
            'status' => 'pending',
            'risk_level' => 'Low',
            'term_months' => 12,
            'next_due_date' => '2026-02-15',
            'remaining_balance' => 3000,
        ]);

        // Rejected loan: amount 2000, remaining_balance 0 (NOT counted in approved amount)
        $rejectedLoan = LoanApplication::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'loan_type' => 'regular',
            'amount' => 2000,
            'status' => 'rejected',
            'risk_level' => 'Low',
            'term_months' => 12,
            'next_due_date' => '2026-02-15',
            'remaining_balance' => 0,
        ]);

        $response = $this->actingAs($manager)->get('/reports?report_type=borrower_performance');
        $response->assertOk();
        $borrowerPerf = $response->viewData('borrowerPerf');
        $row = $borrowerPerf->first();
        $this->assertNotNull($row);
        // total_approved_amount only includes approved loan (5000)
        $this->assertStringContainsString('5,000', $row->total_approved_amount);
        // outstanding sums remaining_balance of APPROVED loans only (1000)
        $this->assertStringContainsString('1,000', $row->outstanding);
    }

    /**
     * Issue G (a): /reports with date filter + status filter + risk_level filter and a combination.
     */
    /**
 * Issue G (a): /reports with date filter + status filter + risk_level filter and a combination.
 * Uses explicit created_at dates to make date filters work with SQLite in-memory.
 */
    /** @test */
    public function reports_filters_with_date_and_status_and_risk_level(): void
    {
        $manager = User::factory()->create(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);
        $memberUser = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER]);
        CoopMember::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'status' => CoopMember::STATUS_ACTIVE,
        ]);

        // Loan approved, created 2026-01-10, risk Low, amount 5000
        $loanApproved = LoanApplication::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'risk_level' => 'Low',
            'term_months' => 12,
            'next_due_date' => '2026-02-15',
            'remaining_balance' => 1000,
        ]);
        DB::table('loan_applications')->where('id', $loanApproved->id)->update(['created_at' => '2026-01-10 10:00:00']);

        // Loan pending, created 2026-01-12, risk Medium, amount 3000
        $loanPending = LoanApplication::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'loan_type' => 'regular',
            'amount' => 3000,
            'status' => 'pending',
            'risk_level' => 'Medium',
            'term_months' => 12,
            'next_due_date' => '2026-02-15',
            'remaining_balance' => 3000,
        ]);
        DB::table('loan_applications')->where('id', $loanPending->id)->update(['created_at' => '2026-01-12 10:00:00']);

        // Loan rejected, created 2026-01-15, risk High, amount 2000
        $loanRejected = LoanApplication::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'loan_type' => 'regular',
            'amount' => 2000,
            'status' => 'rejected',
            'risk_level' => 'High',
            'term_months' => 12,
            'next_due_date' => '2026-02-15',
            'remaining_balance' => 0,
        ]);
        DB::table('loan_applications')->where('id', $loanRejected->id)->update(['created_at' => '2026-01-15 10:00:00']);

        // Issue A: early payment (before due date) counts as on-time
        Payment::factory()->create([
            'loan_application_id' => $loanApproved->id,
            'user_id' => $memberUser->id,
            'amount_paid' => 500,
            'payment_date' => '2026-01-10',
        ]);

        // Payment 2: due 2026-02-15, paid on time
        Payment::factory()->create([
            'loan_application_id' => $loanApproved->id,
            'user_id' => $memberUser->id,
            'amount_paid' => 500,
            'payment_date' => '2026-02-15',
        ]);

        // Filter: approved status, Low risk, created in January 2026
        $response = $this->actingAs($manager)->get('/reports?report_type=borrower_performance&status=approved&risk_level=Low&start_date=2026-01-01&end_date=2026-01-31');
        $response->assertOk();
        $borrowerPerf = $response->viewData('borrowerPerf');
        $row = $borrowerPerf->first();
        $this->assertNotNull($row);
        // Only the approved+Low loan from Jan 10 should appear (pending/rejected excluded, Medium/High excluded)
        $this->assertEquals(1, $row->total_loans, 'Only 1 approved+Low loan created on 2026-01-10 should appear in date range');
        // total_approved_amount should only count the approved loan
        $this->assertStringContainsString('5,000', $row->total_approved_amount);
    }

    /**
     * Issue G (b): /reports export PDF with report type specified.
     */
    /** @test */
    public function reports_export_pdf_basic(): void
    {
        $manager = User::factory()->create(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);

        $memberUser = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER]);
        CoopMember::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'status' => CoopMember::STATUS_ACTIVE,
        ]);

        // Loan with risk Low
        $loanLow = LoanApplication::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'risk_level' => 'Low',
            'term_months' => 12,
            'next_due_date' => '2026-02-15',
            'remaining_balance' => 1000,
        ]);

        $response = $this->actingAs($manager)->get('/reports/export-pdf?report_type=borrower_performance');
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * Issue G (b): /reports export Excel with report type specified.
     */
    /** @test */
    public function reports_export_excel_basic(): void
    {
        $manager = User::factory()->create(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);

        $memberUser = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER]);
        CoopMember::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'status' => CoopMember::STATUS_ACTIVE,
        ]);

        // Loan with risk Low
        $loanLow = LoanApplication::factory()->create([
            'user_id' => $memberUser->id,
            'full_name' => 'Test Borrower',
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'risk_level' => 'Low',
            'term_months' => 12,
            'next_due_date' => '2026-02-15',
            'remaining_balance' => 1000,
        ]);

        $response = $this->actingAs($manager)->get('/reports/export-excel?report_type=borrower_performance');
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
    }

    /**
     * Issue G (c): run reminders:send-due on the in-memory test database and assert the notifications and logs.
     */
    /** @test */
    public function reminders_send_due_command_asserts_notifications_and_logs(): void
    {
        Mail::fake();

        $member = User::factory()->create([
            'usertype' => 'member',
            'email' => 'member@example.com',
        ]);

        CoopMember::factory()->create([
            'user_id' => $member->id,
            'share_capital' => 30000,
            'is_migs' => false,
        ]);

        $loan = LoanApplication::factory()->create([
            'user_id' => $member->id,
            'full_name' => 'Test Borrower',
            'email' => 'member@example.com',
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'term_months' => 12,
            'remaining_balance' => 800,
            'next_due_date' => $this->getManilaDate(-5),
        ]);

        $admin = User::factory()->create([
            'usertype' => 'branch_manager',
            'email' => 'admin@example.com',
        ]);

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('reminders.run'));

        // Verify notification was created
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $member->id,
            'loan_application_id' => $loan->id,
            'type' => 'overdue',
        ]);

        // Verify notification log shows Sent status
        $this->assertDatabaseHas('app_notification_logs', [
            'loan_application_id' => $loan->id,
            'notification_type' => 'overdue',
            'status' => 'Sent',
            'channel' => 'email',
        ]);

        // Verify email was sent
        Mail::assertSent(\App\Mail\PaymentReminderMail::class, 1);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reportTypes(): array
    {
        return [
            'loan_summary'      => ['loan_summary'],
            'approved_records'   => ['approved_records'],
            'borrower_performance' => ['borrower_performance'],
            'overdue_payments'   => ['overdue_payments'],
        ];
    }
}