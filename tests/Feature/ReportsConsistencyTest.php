<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Reports consistency (Objective 2): granted-loan semantics, amount
 * semantics, member resolution, payment math, overdue filtering, numeric
 * Excel cells, and the web/PDF/Excel matrix for all four report types.
 */
class ReportsConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);
    }

    private function member(string $name = 'Test Borrower', string $email = 'borrower@example.com'): User
    {
        $user = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER, 'email' => $email]);
        CoopMember::factory()->create([
            'user_id'   => $user->id,
            'full_name' => $name,
            'email'     => $email,
            'status'    => CoopMember::STATUS_ACTIVE,
        ]);

        return $user;
    }

    private function loan(User $user, array $attrs): LoanApplication
    {
        $loan = LoanApplication::factory()->create($attrs + [
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
        ]);

        if (isset($attrs['approved_amount'])) {
            $loan->forceFill(['approved_amount' => $attrs['approved_amount']])->save();
        }

        return $loan;
    }

    /** @test */
    public function paid_loans_count_as_granted_everywhere(): void
    {
        $manager = $this->manager();
        $member  = $this->member();

        $paid = $this->loan($member, [
            'amount' => 5000, 'status' => 'paid',
            'remaining_balance' => 0, 'next_due_date' => null,
            'approved_amount' => 8000,
        ]);
        $this->loan($member, [
            'amount' => 10000, 'status' => 'approved',
            'remaining_balance' => 9000, 'next_due_date' => '2026-12-15',
            'approved_amount' => 12000,
        ]);

        $response = $this->actingAs($manager)->get('/reports');
        $response->assertOk();

        // KPI: granted = approved + paid, using approved_amount when set.
        $this->assertEqualsWithDelta(20000, (float) $response->viewData('totalApproved'), 0.01);

        // Approved Records includes the paid row, labelled.
        $recs = $response->viewData('approvedRecs');
        $paidRow = $recs->firstWhere('app_id', $paid->reference);
        $this->assertNotNull($paidRow, 'Paid loan must appear in Approved Records');
        $this->assertSame('Paid', $paidRow->status_label);
        $this->assertEqualsWithDelta(8000, (float) $paidRow->approved_amount, 0.01);

        // Borrower Performance: paid contributes approved amount, outstanding 0.
        $row = $response->viewData('borrowerPerf')->first();
        $this->assertEqualsWithDelta(20000, (float) $row->total_approved_amount, 0.01);
        $this->assertEqualsWithDelta(9000, (float) $row->outstanding, 0.01);

        // Loan Summary keeps the paid status group with the approved amount.
        $summary = $response->viewData('loanSummary');
        $this->assertEqualsWithDelta(8000, (float) ($summary['status_totals']['paid'] ?? 0), 0.01);
    }

    /** @test */
    public function loan_summary_amounts_use_approved_amount_for_granted_and_requested_otherwise(): void
    {
        $manager = $this->manager();
        $member  = $this->member();

        $this->loan($member, [
            'amount' => 5000, 'status' => 'approved',
            'remaining_balance' => 5000, 'next_due_date' => '2026-12-15',
            'approved_amount' => 7500,
        ]);
        $this->loan($member, ['amount' => 3000, 'status' => 'pending', 'remaining_balance' => 0]);

        $response = $this->actingAs($manager)->get('/reports');
        $summary  = $response->viewData('loanSummary');

        $this->assertEqualsWithDelta(7500, (float) $summary['status_totals']['approved'], 0.01);
        $this->assertEqualsWithDelta(3000, (float) $summary['status_totals']['pending'], 0.01);
        // Total row equals the sum of the status rows.
        $this->assertEqualsWithDelta(array_sum($summary['status_totals']), (float) $summary['total_amount'], 0.01);
    }

    /** @test */
    public function borrower_performance_resolves_members_not_tied_to_a_portal_user(): void
    {
        $manager = $this->manager();
        $coop    = CoopMember::factory()->create([
            'full_name' => 'Portal-less Borrower',
            'email'     => 'portal@example.com',
            'status'    => CoopMember::STATUS_ACTIVE,
            'user_id'   => null,
        ]);

        // Staff-encoded loan: no user_id, email matches the coop member.
        LoanApplication::factory()->create([
            'user_id' => null,
            'full_name' => 'Portal-less Borrower',
            'email' => 'Portal@Example.com ',
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'approved',
            'remaining_balance' => 4000,
            'next_due_date' => '2026-12-15',
        ]);

        // Truly unmatched loan: shows under the loan's own applicant name.
        LoanApplication::factory()->create([
            'user_id' => null,
            'full_name' => 'No Member Row',
            'email' => 'unknown@example.com',
            'loan_type' => 'regular',
            'amount' => 2000,
            'status' => 'approved',
            'remaining_balance' => 2000,
            'next_due_date' => '2026-12-15',
        ]);

        $response = $this->actingAs($manager)->get('/reports?report_type=borrower_performance');
        $names = $response->viewData('borrowerPerf')->pluck('member_name')->all();

        $this->assertContains('Portal-less Borrower', $names);
        $this->assertContains('No Member Row', $names);
        $this->assertNotContains('Unknown', $names);
    }

    /** @test */
    public function borrower_performance_totals_payments_across_multiple_loans(): void
    {
        $manager = $this->manager();
        $member  = $this->member();

        // Active loan: 2 payments (on-time + late).
        $active = $this->loan($member, [
            'amount' => 5000, 'status' => 'approved',
            'remaining_balance' => 4000, 'next_due_date' => '2026-03-15',
        ]);
        // Fully paid loan: 2 on-time payments anchored off created_at.
        $paid = $this->loan($member, [
            'amount' => 5000, 'status' => 'paid',
            'remaining_balance' => 0, 'next_due_date' => null,
            'created_at' => '2026-01-10 10:00:00',
        ]);

        Payment::create(['loan_application_id' => $active->id, 'user_id' => $member->id, 'amount_paid' => 500, 'payment_date' => '2026-01-10']);
        Payment::create(['loan_application_id' => $active->id, 'user_id' => $member->id, 'amount_paid' => 500, 'payment_date' => '2026-02-20']);
        Payment::create(['loan_application_id' => $paid->id,   'user_id' => $member->id, 'amount_paid' => 500, 'payment_date' => '2026-01-31']);
        Payment::create(['loan_application_id' => $paid->id,   'user_id' => $member->id, 'amount_paid' => 500, 'payment_date' => '2026-02-28']);

        $response = $this->actingAs($manager)->get('/reports?report_type=borrower_performance');
        $row = $response->viewData('borrowerPerf')->first();

        $this->assertEqualsWithDelta(2000, (float) $row->total_paid, 0.01, 'total_paid must be the real sum across loans');
        $this->assertSame(3, $row->on_time_count);
        $this->assertSame(1, $row->late_count);
    }

    /** @test */
    public function borrower_performance_date_filter_works_with_one_sided_range(): void
    {
        $manager = $this->manager();
        $member  = $this->member();

        // Loans created inside each window; payments span both sides of the
        // window end/start so the one-sided payment filter is observable.
        $febLoan = $this->loan($member, [
            'amount' => 5000, 'status' => 'approved',
            'remaining_balance' => 4000, 'next_due_date' => '2026-03-15',
            'created_at' => '2026-02-01 10:00:00',
        ]);
        Payment::create(['loan_application_id' => $febLoan->id, 'user_id' => $member->id, 'amount_paid' => 500, 'payment_date' => '2026-02-10']);
        Payment::create(['loan_application_id' => $febLoan->id, 'user_id' => $member->id, 'amount_paid' => 500, 'payment_date' => '2026-03-20']);

        $marLoan = $this->loan($member, [
            'amount' => 5000, 'status' => 'approved',
            'remaining_balance' => 4000, 'next_due_date' => '2026-04-15',
            'created_at' => '2026-03-01 10:00:00',
        ]);
        Payment::create(['loan_application_id' => $marLoan->id, 'user_id' => $member->id, 'amount_paid' => 500, 'payment_date' => '2026-03-10']);
        Payment::create(['loan_application_id' => $marLoan->id, 'user_id' => $member->id, 'amount_paid' => 500, 'payment_date' => '2026-04-20']);

        // end only: febLoan kept, only its Feb payment counts
        $endOnly = $this->actingAs($manager)
            ->get('/reports?report_type=borrower_performance&end_date=2026-02-28')
            ->viewData('borrowerPerf')->first();
        $this->assertEqualsWithDelta(500, (float) $endOnly->total_paid, 0.01);

        // start only: marLoan kept, both its payments count
        $startOnly = $this->actingAs($manager)
            ->get('/reports?report_type=borrower_performance&start_date=2026-03-01')
            ->viewData('borrowerPerf')->first();
        $this->assertEqualsWithDelta(1000, (float) $startOnly->total_paid, 0.01);
    }

    /** @test */
    public function overdue_report_filters_on_due_date_and_ignores_status(): void
    {
        $manager = $this->manager();
        $member  = $this->member();

        $overdue = $this->loan($member, [
            'amount' => 5000, 'status' => 'approved',
            'remaining_balance' => 4000,
            'next_due_date' => '2026-01-01',
            'created_at' => '2025-06-01 10:00:00',
        ]);
        $current = $this->loan($member, [
            'amount' => 5000, 'status' => 'approved',
            'remaining_balance' => 4000,
            'next_due_date' => '2026-12-31',
            'created_at' => '2026-01-10 10:00:00',
        ]);

        // Date window covers only the overdue loan's due date.
        $rows = $this->actingAs($manager)
            ->get('/reports?report_type=overdue_payments&start_date=2025-12-01&end_date=2026-01-31')
            ->viewData('overduePayments');
        $this->assertCount(1, $rows);
        $this->assertSame($overdue->reference, $rows->first()->app_id);

        // Status filter contradicts this report and must be ignored.
        $rows = $this->actingAs($manager)
            ->get('/reports?report_type=overdue_payments&status=pending')
            ->viewData('overduePayments');
        $this->assertCount(1, $rows);

        // Installment = monthly payment capped at remaining balance.
        $emi = (float) $overdue->monthly_payment;
        $this->assertEqualsWithDelta(min($emi, 4000), (float) $rows->first()->overdue_installment, 0.01);
        $this->assertEqualsWithDelta(4000, (float) $rows->first()->amount, 0.01);

        // Final installment smaller than the EMI stays as the remaining balance.
        $small = $this->loan($member, [
            'amount' => 5000, 'status' => 'approved',
            'remaining_balance' => 200,
            'next_due_date' => '2026-01-01',
        ]);
        $rows = $this->actingAs($manager)
            ->get('/reports?report_type=overdue_payments')
            ->viewData('overduePayments');
        $smallRow = $rows->firstWhere('app_id', $small->reference);
        $this->assertEqualsWithDelta(200, (float) $smallRow->overdue_installment, 0.01);
    }

    /** @test */
    public function overdue_report_shows_installment_and_balance_columns_on_web(): void
    {
        $manager = $this->manager();
        $member  = $this->member();

        $this->loan($member, [
            'amount' => 5000, 'status' => 'approved',
            'remaining_balance' => 4000,
            'next_due_date' => '2026-01-01',
        ]);

        $content = $this->actingAs($manager)
            ->get('/reports?report_type=overdue_payments')
            ->getContent();

        $this->assertStringContainsString('Overdue installment', $content);
        $this->assertStringContainsString('Remaining balance', $content);
        $this->assertStringContainsString('₱4,000.00', $content);
    }

    /** @test */
    public function excel_exports_write_real_numbers_not_formatted_strings(): void
    {
        $manager = $this->manager();
        $member  = $this->member();

        $paid = $this->loan($member, [
            'amount' => 5000, 'status' => 'paid',
            'remaining_balance' => 0, 'next_due_date' => null,
            'approved_amount' => 8000,
        ]);

        $response = $this->actingAs($manager)->get('/reports/export-excel?report_type=approved_records');
        $response->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        file_put_contents($tmp, $response->getContent());
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        unlink($tmp);

        $value = $sheet->getCell('E5')->getValue();
        $this->assertIsFloat($value, 'Approved Amount must be a real float, not a pre-formatted string');
        $this->assertEqualsWithDelta(8000, $value, 0.01);
        $this->assertSame('₱#,##0.00', $sheet->getStyle('E5')->getNumberFormat()->getFormatCode());
        $this->assertSame('Paid', $sheet->getCell('D5')->getValue());
    }

    /**
     * 2.7 — every report type renders on web, PDF and Excel with data
     * present and with an empty database.
     *
     * @dataProvider reportTypes
     */
    public function every_report_type_works_on_web_pdf_and_excel_with_and_without_data(string $type): void
    {
        $manager = $this->manager();
        $member  = $this->member();

        $this->loan($member, [
            'amount' => 5000, 'status' => 'approved',
            'remaining_balance' => 4000, 'next_due_date' => '2026-01-01',
            'approved_amount' => 4500,
        ]);
        $this->loan($member, [
            'amount' => 3000, 'status' => 'pending', 'remaining_balance' => 0,
        ]);

        $this->actingAs($manager)->get('/reports?report_type=' . $type)->assertOk();
        $this->actingAs($manager)->get('/reports/export-pdf?report_type=' . $type)->assertOk();
        $this->actingAs($manager)->get('/reports/export-excel?report_type=' . $type)->assertOk();
    }

    /**
     * @dataProvider reportTypes
     */
    public function every_report_type_renders_empty_without_error(string $type): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->get('/reports?report_type=' . $type)->assertOk();
        $this->actingAs($manager)->get('/reports/export-pdf?report_type=' . $type)->assertOk();
        $this->actingAs($manager)->get('/reports/export-excel?report_type=' . $type)->assertOk();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reportTypes(): array
    {
        return [
            'loan_summary'         => ['loan_summary'],
            'approved_records'     => ['approved_records'],
            'borrower_performance' => ['borrower_performance'],
            'overdue_payments'     => ['overdue_payments'],
        ];
    }
}
