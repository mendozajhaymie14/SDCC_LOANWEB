<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A replacement for the deleted LegacyUsertypeTest.
 *
 * Business rule: usertype '' or NULL means "registered, no cooperative link"
 * — the same as usertype 'user'. Both reach the member portal (200) and get a
 * raw 403 on every staff route. They are NOT promoted to 'member': promotion
 * only happens for rows that already carry a cooperative link.
 */
class UsertypeRoleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_null_usertype_reaches_the_portal_and_is_denied_on_staff_routes()
    {
        $user = User::factory()->create(['usertype' => null]);

        $this->assertSame(Permissions::ROLE_USER, Permissions::role($user));

        $this->actingAs($user)->get('/user/dashboard')->assertOk();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    /** @test */
    public function a_user_usertype_reaches_the_portal_and_is_denied_on_staff_routes()
    {
        $user = User::factory()->create(['usertype' => Permissions::ROLE_USER]);

        $this->assertSame(Permissions::ROLE_USER, Permissions::role($user));

        $this->actingAs($user)->get('/user/dashboard')->assertOk();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/applications')->assertForbidden();
    }
}
