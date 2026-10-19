<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberAccessControlTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_member_gets_403_on_admin_users_route()
    {
        $member = User::factory()->create(['usertype' => 'member']);

        $this->actingAs($member)->get('/admin/users')->assertForbidden();
    }

    /** @test */
    public function a_member_gets_403_on_admin_backup_route()
    {
        $member = User::factory()->create(['usertype' => 'member']);

        $this->actingAs($member)->get('/admin/backup')->assertForbidden();
    }

    /** @test */
    public function a_member_gets_403_on_applications_approve_route()
    {
        $member = User::factory()->create(['usertype' => 'member']);
        $application = Application::create([
            'app_id'     => 'APP-0001',
            'applicant'  => 'Test Member',
            'loan_type'  => 'educational',
            'amount'     => 50000,
            'status'     => 'Pending',
            'ai_score'   => null,
        ]);

        $this->actingAs($member)
            ->post('/applications/' . $application->app_id . '/approve')
            ->assertForbidden();
    }

    /** @test */
    public function a_member_gets_403_on_reports_route()
    {
        $member = User::factory()->create(['usertype' => 'member']);

        $this->actingAs($member)->get('/reports')->assertForbidden();
    }

    /** @test */
    public function a_user_usertype_denied_on_admin_routes()
    {
        // Real members are stored as usertype='user', not 'member'.
        // Every usertype except 'admin' must be denied on admin-only routes.
        $user = User::factory()->create(['usertype' => 'user']);

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/applications')->assertForbidden();
        $this->actingAs($user)->get('/admin/backup')->assertForbidden();
    }
}
