<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Task7ViewModalIdResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'usertype' => 'branch_manager',
            'email'    => 'task7-admin@example.com',
        ], $attributes));
    }

    private function makeMember(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'usertype' => 'member',
            'email'    => 'task7-member@example.com',
        ], $attributes));
    }

    private function coopMember(User $member): CoopMember
    {
        return CoopMember::factory()->create([
            'user_id'               => $member->id,
            'email'                 => $member->email,
            'share_capital'         => 30000,
            'is_migs'               => false,
            'monthly_income'        => 20000,
        ]);
    }

    /** @test */
    public function application_data_route_returns_200_when_loan_id_and_mirror_id_differ()
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember();
        $this->coopMember($member);

        // Loan id 100, mirror id 50 (mismatched ids), app_id APP-0050
        $loan = LoanApplication::factory()->create([
            'id'                 => 100,
            'user_id'            => $member->id,
            'email'              => $member->email,
            'full_name'          => 'Task7 Borrower',
            'loan_type'          => 'regular',
            'amount'             => 5000,
            'status'             => 'pending',
            'term_months'        => 12,
            'monthly_income'     => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'      => 30000,
            'remaining_balance'  => 0,
        ]);

        // Mirror applications.id = 50, loan_application_id = 100, app_id = APP-0050
        $app = Application::factory()->create([
            'id'                 => 50,
            'loan_application_id'=> $loan->id,
            'app_id'             => 'APP-0050',
            'applicant'          => $loan->full_name,
            'status'             => 'Pending',
            'recommended_amount' => 2500.00,
        ]);

        // Data route must resolve numeric applications.id -> 200
        $response = $this->actingAs($admin)
            ->get(route('admin.applications.data', $app->id));
        $response->assertSuccessful();
        $response->assertJsonFragment(['app_id' => 'APP-0050']);

        // Data route must also resolve the APP-XXXX code -> 200
        $response = $this->actingAs($admin)
            ->get(route('admin.applications.data', $app->app_id));
        $response->assertSuccessful();
        $response->assertJsonFragment(['app_id' => 'APP-0050']);

        // Data route must also resolve the loan_application_id -> 200
        $response = $this->actingAs($admin)
            ->get(route('admin.applications.data', $loan->id));
        $response->assertSuccessful();
        $response->assertJsonFragment(['app_id' => 'APP-0050']);

        // Applications page should show the correct app_id
        $appsPage = $this->actingAs($admin)->get(route('applications.index'))->getContent();
        $this->assertStringContainsString('APP-0050', $appsPage);

        // LoanApplication::appId() shared helper returns the mirror's app_id
        $this->assertEquals('APP-0050', $loan->appId());
    }

    /** @test */
    public function shared_app_id_helper_consistent_across_surfaces()
    {
        $loan = LoanApplication::factory()->create([
            'id'                 => 200,
            'loan_type'          => 'regular',
            'amount'             => 5000,
            'status'             => 'pending',
            'monthly_income'     => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'      => 30000,
            'remaining_balance'  => 0,
        ]);

        $app = Application::factory()->create([
            'id'                 => 150,
            'loan_application_id'=> $loan->id,
            'app_id'             => 'APP-0150',
            'applicant'          => 'Helper Borrower',
            'status'             => 'Pending',
            'recommended_amount' => 2500.00,
        ]);

        // Shared helper returns the mirror's app_id, not derived from loan id
        $this->assertEquals('APP-0150', $loan->appId());

        // Without a mirror, falls back to APP-{loan id}
        $loanWithoutMirror = LoanApplication::factory()->create([
            'id'                 => 300,
            'loan_type'          => 'regular',
            'amount'             => 5000,
            'status'             => 'pending',
            'monthly_income'     => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'      => 30000,
            'remaining_balance'  => 0,
        ]);
        $this->assertEquals('APP-0300', $loanWithoutMirror->appId());
    }

    /** @test */
    public function notifications_log_loan_link_uses_mirror_app_id_not_loan_id()
    {
        $admin = $this->makeAdmin();
        $member = $this->makeMember();
        $this->coopMember($member);

        // Loan id 400, mirror id 250 (mismatched), app_id APP-0250
        $loan = LoanApplication::factory()->create([
            'id'                 => 400,
            'user_id'            => $member->id,
            'email'              => $member->email,
            'full_name'          => 'Notif Borrower',
            'loan_type'          => 'regular',
            'amount'             => 5000,
            'status'             => 'pending',
            'term_months'        => 12,
            'monthly_income'     => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital'      => 30000,
            'remaining_balance'  => 0,
        ]);

        $app = Application::factory()->create([
            'id'                 => 250,
            'loan_application_id'=> $loan->id,
            'app_id'             => 'APP-0250',
            'applicant'          => $loan->full_name,
            'status'             => 'Pending',
            'recommended_amount' => 2500.00,
        ]);

        // Approve through real route to generate an outbox row
        $this->actingAs($admin)
            ->post(route('applications.approve', $app->id), [
                'approved_amount' => 10000,
                'reason'          => 'Good history',
            ]);

        $outbox = $this->actingAs($admin)->get(route('admin.notifications-log'))->getContent();

        // Outbox loan link should show APP-0250 (mirror's app_id), not APP-0400 (loan id derived)
        $this->assertStringContainsString('APP-0250', $outbox);
        $this->assertStringNotContainsString('APP-0400', $outbox);
    }
}
