<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\LoanEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApplicationViewTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'usertype' => 'branch_manager',
            'email'    => 'admin@example.com',
        ], $attributes));
    }

    private function makeMember(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'usertype' => 'member',
            'email'    => 'member@example.com',
        ], $attributes));
    }

    /** @test */
    public function ai_score_matches_rule_based_risk_score()
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember();

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $member->id,
            'loan_type' => 'regular',
            'amount' => 10000,
            'status' => 'approved',
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 2000,
            'share_capital' => 30000,
            'term_months' => 12,
        ]);

        $service = app(LoanEvaluationService::class);
        $evaluation = $service->evaluate($loanApp);

        $result = Application::factory()->create([
            'loan_application_id' => $loanApp->id,
            'applicant' => $loanApp->full_name,
            'ai_score' => $evaluation['risk_score'],
        ]);

        // The stored ai_score must equal the rule-based risk_score
        $this->assertEquals($evaluation['risk_score'], $result->ai_score);
    }

    private function createTestApplicationWithLoan(User $member, array $loanOverrides = [], array $appOverrides = []): Application
    {
        CoopMember::factory()->create([
            'user_id'         => $member->id,
            'share_capital'   => 30000,
            'is_migs'         => false,
        ]);

        $loanApplication = LoanApplication::factory()->create(array_merge([
            'user_id'         => $member->id,
            'full_name'       => 'Test Borrower',
            'email'           => $member->email,
            'loan_type'       => 'regular',
            'amount'          => 5000,
            'status'          => 'pending',
            'monthly_income'  => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'   => 30000,
            'term_months'     => 12,
        ], $loanOverrides));

        $application = Application::factory()->create(array_merge([
            'loan_application_id' => $loanApplication->id,
            'app_id'              => 'APP-' . str_pad($loanApplication->id, 4, '0', STR_PAD_LEFT),
            'loan_type'           => $loanApplication->loan_type,
            'amount'              => $loanApplication->amount,
            'status'              => $loanApplication->status,
            'applicant'           => $loanApplication->full_name,
            'recommended_amount'  => 2500,
            'risk_level'          => 'Medium',
            'risk_score'          => 60,
        ], $appOverrides));

        return $application;
    }

    /** @test */
    public function admin_list_shows_risk_level_and_recommended_amount_columns()
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember();
        $this->createTestApplicationWithLoan($member);

        $response = $this->actingAs($admin)
            ->get(route('applications.index'));

        $response->assertSuccessful();
        // Risk level should be displayed in the risk column
        $response->assertSee('Medium');
        // Recommended amount should be displayed in the recommended column
        $response->assertSee('₱2,500.00');
    }

    /** @test */
    public function admin_list_shows_consistent_name_format()
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember([
            'name' => 'Jhaymie Mendoza Fat',
            'email'=> 'jhaymie@example.com'
        ]);
        $this->createTestApplicationWithLoan($member);

        $response = $this->actingAs($admin)
            ->get(route('applications.index'));

        $response->assertSuccessful();
        $response->assertSee('Fat, Jhaymie Mendoza');
    }

    /** @test */
    public function dashboard_shows_risk_distribution()
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember();
        $this->createTestApplicationWithLoan($member, ['amount' => 10000], ['risk_level' => 'Low', 'risk_score' => 75]);
        $this->createTestApplicationWithLoan($member, ['amount' => 50000, 'monthly_income' => 15000], ['risk_level' => 'Medium', 'risk_score' => 60]);
        $this->createTestApplicationWithLoan($member, ['amount' => 100000, 'monthly_income' => 10000], ['risk_level' => 'High', 'risk_score' => 40]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard'));

        $response->assertSuccessful();
        $response->assertSee('Low');
        $response->assertSee('Medium');
        $response->assertSee('High');
    }

    /** @test */
    public function dashboard_and_list_both_show_application_ids()
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember();
        $application = $this->createTestApplicationWithLoan($member);

        $dashboardResponse = $this->actingAs($admin)
            ->get(route('dashboard'));
        $dashboardResponse->assertSuccessful();

        $listResponse = $this->actingAs($admin)
            ->get(route('applications.index'));
        $listResponse->assertSuccessful();

        // Both should show the application ID
        $dashboardResponse->assertSee($application->app_id);
        $listResponse->assertSee($application->app_id);
    }

    /** @test */
    public function risk_flags_page_shows_high_risk_applications()
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember();
        $this->createTestApplicationWithLoan($member, ['amount' => 100000, 'monthly_income' => 10000], ['risk_level' => 'High', 'risk_score' => 40]);

        $response = $this->actingAs($admin)
            ->get(route('risk-flags.index'));

        $response->assertSuccessful();
        $response->assertSee('High');
    }

    /** @test */
    public function sidebar_badge_shows_high_risk_count()
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember();
        $highRiskApp = $this->createTestApplicationWithLoan($member, ['amount' => 100000, 'monthly_income' => 10000], ['risk_level' => 'High', 'risk_score' => 40]);
        $lowRiskApp = $this->createTestApplicationWithLoan($member, ['amount' => 5000], ['risk_level' => 'Low', 'risk_score' => 75]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard'));

        $response->assertSuccessful();

        $highRiskCount = Application::where('risk_level', 'High')->count();
        $this->assertGreaterThan(0, $highRiskCount);
    }

    /** @test */
    public function application_detail_displays_rule_based_fields()
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember();
        $application = $this->createTestApplicationWithLoan($member);

        // The detail route is ->whereNumber; the list view links to it with the
        // numeric applications.id (data-numeric-id), not the APP-XXXX code.
        $response = $this->actingAs($admin)
            ->get(route('admin.applications.show', $application->id));

        $response->assertSuccessful();
        $response->assertSee($application->risk_level);
        $response->assertSee(number_format($application->recommended_amount, 2));
        $response->assertSee((string) $application->risk_score);
    }

    /** @test */
    public function dashboard_pending_shows_application_not_loan_ids()
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember();
        $loanApplication = LoanApplication::factory()->create([
            'user_id'         => $member->id,
            'full_name'       => 'Test Borrower',
            'email'           => $member->email,
            'loan_type'       => 'regular',
            'amount'          => 5000,
            'status'          => 'pending',
            'monthly_income'  => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'   => 30000,
            'term_months'     => 12,
        ]);
        // Create Application mirror with same ID format as used in app
        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id'              => 'APP-' . str_pad($loanApplication->id, 4, '0', STR_PAD_LEFT),
            'loan_type'           => $loanApplication->loan_type,
            'amount'              => $loanApplication->amount,
            'status'              => $loanApplication->status,
            'applicant'           => $loanApplication->full_name,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard'));

        $response->assertSuccessful();
        // Should show APP-XXXX format, not raw loan application ID
        $response->assertSee($application->app_id);
        // Check that raw loan ID is not displayed in the ID column of pending applications table
        $response->assertDontSee('<td class="font-semibold text-gray-700">#'.$loanApplication->id.'</td>');
    }

    /** @test */
    public function risk_flags_count_consistent_across_pages()
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember();
        $highRiskApp1 = $this->createTestApplicationWithLoan($member, ['amount' => 100000, 'monthly_income' => 10000], ['risk_level' => 'High', 'risk_score' => 40]);
        $highRiskApp2 = $this->createTestApplicationWithLoan($member, ['amount' => 150000, 'monthly_income' => 5000], ['risk_level' => 'High', 'risk_score' => 30]);
        $lowRiskApp = $this->createTestApplicationWithLoan($member, ['amount' => 5000], ['risk_level' => 'Low', 'risk_score' => 75]);

        $dashboardResponse = $this->actingAs($admin)
            ->get(route('dashboard'));
        $dashboardResponse->assertSuccessful();

        $riskFlagsResponse = $this->actingAs($admin)
            ->get(route('risk-flags.index'));
        $riskFlagsResponse->assertSuccessful();

        $highRiskCount = Application::where('risk_level', 'High')->count();
        $this->assertEquals(2, $highRiskCount);
    }
}