<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for member portal button visibility and access control.
 *
 * These tests verify that the dashboard "Become a member" and "Apply for a
 * loan" buttons respect the new permission-gated conditions, and that
 * redirects work correctly for every role.
 */
class MemberPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function member_role_user_does_not_see_become_a_member_on_dashboard(): void
    {
        $member = User::factory()->asMember()->create();

        $this->actingAs($member);

        $response = $this->get(route('user.dashboard'));

        // The dashboard should NOT contain the "Become a member" button
        // (the button is only shown when Permissions::has($user, APPLY_MEMBERSHIP))
        $response->assertSee('Apply for a loan');
        $response->assertDontSee('Become a member');
    }

    /** @test */
    public function user_role_does_see_become_a_member_on_dashboard(): void
    {
        $user = User::factory()->asUser()->create();

        $this->actingAs($user);

        $response = $this->get(route('user.dashboard'));

        // The dashboard SHOULD contain the "Become a member" button
        // (APPLY_MEMBERSHIP permission is granted only to ROLE_USER)
        $response->assertSee('Become a member');
    }

    /** @test */
    public function member_without_active_coop_link_redirected_to_dashboard_with_error_on_loan_attempt(): void
    {
        // A user with usertype='member' but no active CoopMember link
        $member = User::factory()->create(['usertype' => 'member']);

        $this->actingAs($member);

        $response = $this->get(route('user.loans.create'));

        // A member without an active coop link is sent to the membership page
        // with an error, not to the dashboard (see UserController::createApplication).
        $response->assertRedirect(route('user.membership'));

        $response->assertSessionHas('error', 'Your membership is currently inactive. Please reactivate your membership before applying for a loan.');
    }

    /** @test */
    public function member_visiting_user_membership_redirected_to_dashboard(): void
    {
        // A user with usertype='member' AND an active CoopMember link
        $member = User::factory()->asMember()->create();

        $this->actingAs($member);

        $response = $this->get(route('user.membership'));

        // Should redirect to dashboard (the controller checks isActiveCoopMember first)
        $response->assertRedirect(route('user.dashboard'));

        // Should have the info message
        $response->assertSessionHas('info', 'You are already a member.');
    }
}