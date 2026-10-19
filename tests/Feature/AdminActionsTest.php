<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['usertype' => 'admin', 'email' => 'admin@example.com']);
    }

    private function createPendingTestApplication(): Application
    {
        $member = User::factory()->create(['usertype' => 'member', 'email' => 'mem@example.com']);
        CoopMember::factory()->create([
            'user_id' => $member->id,
            'share_capital' => 30000,
        ]);

        $loanApp = LoanApplication::factory()->create([
            'user_id' => $member->id,
            'full_name' => 'John Smith',
            'email' => $member->email,
            'loan_type' => 'regular',
            'amount' => 5000,
            'status' => 'pending',
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'term_months' => 12,
        ]);

        return Application::factory()->create([
            'loan_application_id' => $loanApp->id,
            'app_id' => 'APP-' . str_pad($loanApp->id, 4, '0', STR_PAD_LEFT),
            'applicant' => 'John Smith',
            'status' => 'pending',
            'recommended_amount' => 2500.00,
            'risk_level' => 'Medium',
            'risk_score' => 60,
        ]);
    }

    /** @test */
    public function view_data_endpoint_sends_numeric_id()
    {
        $admin = $this->makeAdmin();
        $app = $this->createPendingTestApplication();

        $response = $this->actingAs($admin)
            ->get(route('admin.applications.data', $app->id));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertSee($app->app_id);
        $response->assertJsonStructure([
            'application' => ['app_id', 'status', 'ai_score'],
        ]);
    }

    /** @test */
    public function approve_endpoint_with_numeric_id_succeeds_for_pending_app()
    {
        $admin = $this->makeAdmin();
        $app = $this->createPendingTestApplication();

        $token = csrf_token();
        $csrf = [
            'X-CSRF-TOKEN' => $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $response = $this->actingAs($admin)
            ->post(route('applications.approve', $app->id), [
                '_token' => $token,
                'approved_amount' => 10000,
                'reason' => 'Exceptional payment history',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertSee(route('applications.approve', $app->id));
    }

    /** @test */
    public function reject_endpoint_with_numeric_id_succeeds_for_pending_app()
    {
        $admin = $this->makeAdmin();
        $app = $this->createPendingTestApplication();

        $token = csrf_token();
        $response = $this->actingAs($admin)
            ->post(route('applications.reject', $app->id), [
                '_token' => $token,
                'reason' => 'Insufficient documentation',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function delete_endpoint_with_numeric_id_succeeds_for_pending_app()
    {
        $admin = $this->makeAdmin();
        $app = $this->createPendingTestApplication();

        $response = $this->actingAs($admin)
            ->delete(route('applications.destroy', $app->id));

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing($app);
    }

    /** @test */
    public function scan_endpoint_with_numeric_id_returns_success_for_pending_app()
    {
        $admin = $this->makeAdmin();
        $app = $this->createPendingTestApplication();

        $response = $this->actingAs($admin)
            ->get(route('admin.applications.scan', $app->id));

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function non_numeric_id_gets_correctly_rejected()
    {
        $admin = $this->makeAdmin();
        $app = $this->createPendingTestApplication();

        // app_id format like "APP-0014" should NOT match ->whereNumber route
        $this->expectException(\InvalidArgumentException::class);
        $this->actingAs($admin)
            ->get(route('admin.applications.show', 'APP-' . str_pad($app->id, 4, '0', STR_PAD_LEFT)));
    }
}
