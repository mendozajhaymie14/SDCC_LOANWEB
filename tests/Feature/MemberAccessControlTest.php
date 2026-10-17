<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\LoanApplication;
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
        $loan = LoanApplication::create([
            'user_id'      => $member->id,
            'reference'    => 'SDCC-2026-000001',
            'app_id'       => 'APP-0001',
            'full_name'    => 'Test Member',
            'email'        => 'member@example.com',
            'amount'       => 50000,
            'term_months'  => 12,
            'loan_type'    => 'educational',
            'status'       => 'pending',
            'documents'    => [],
        ]);

        $this->actingAs($member)
            ->post('/applications/' . $loan->app_id . '/approve')
            ->assertForbidden();
    }

    /** @test */
    public function a_member_gets_403_on_reports_route()
    {
        $member = User::factory()->create(['usertype' => 'member']);

        $this->actingAs($member)->get('/reports')->assertForbidden();
    }
}
