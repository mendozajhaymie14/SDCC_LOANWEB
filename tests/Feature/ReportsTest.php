<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'status' => 'disbursed',
            'risk_level' => 'Low',
        ]);

        $response = $this->actingAs($manager)->get('/reports');
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('Reports & Analytics', $content);
        $this->assertStringContainsString('Loan Summary', $content);
        $this->assertStringContainsString('Approved Loan Records', $content);
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
     * @return array<string, array{0: string}>
     */
    public static function reportTypes(): array
    {
        return [
            'loan_summary'         => ['loan_summary'],
            'approved_records'     => ['approved_records'],
            'borrower_performance' => ['borrower_performance'],
        ];
    }
}
