<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CoopMember;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberSelfProfileTest extends TestCase
{
    use RefreshDatabase;

    /** Helper: create an active coop member linked to a user with 'member' role. */
    protected function createActiveMember(array $userAttrs = [], array $memberAttrs = []): array
    {
        $user = User::factory()->create(array_merge([
            'usertype' => Permissions::ROLE_MEMBER,
        ], $userAttrs));

        $member = CoopMember::factory()->create(array_merge([
            'user_id' => $user->id,
            'status' => CoopMember::STATUS_ACTIVE,
            'full_name' => $user->name,
            'email' => $user->email,
            'contact_number' => '09123456789',
            'address' => '123 Sample St',
            'occupation' => 'Employee',
            'savings_balance' => 10000,
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 5000,
            'approved_credit_limit' => 50000,
        ], $memberAttrs));

        $user->forceFill(['coop_member_id' => $member->id])->save();

        return ['user' => $user, 'member' => $member];
    }

    /** Helper: create a user without an active CoopMember link. */
    protected function createRegisteredUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'usertype' => Permissions::ROLE_MEMBER,
        ], $attrs));
    }

    /** @test */
    public function member_can_view_own_profile()
    {
        $data = $this->createActiveMember([
            'email' => 'member@example.com',
            'name' => 'Jane Doe',
        ]);

        $this->actingAs($data['user'])
            ->get(route('user.profile'))
            ->assertOk();
    }

    /** @test */
    public function member_sees_only_own_data_on_profile()
    {
        $own = $this->createActiveMember([
            'email' => 'own@example.com',
            'name' => 'Own Person',
        ]);

        $other = $this->createActiveMember([
            'email' => 'other@example.com',
            'name' => 'Other Person',
        ]);

        $response = $this->actingAs($own['user'])->get(route('user.profile'));

        $response->assertSee($own['member']->full_name);
        $response->assertSee($own['member']->email);
        $response->assertDontSee($other['member']->full_name);
        $response->assertDontSee($other['member']->email);
    }

    /** @test */
    public function member_cannot_see_another_member_profile()
    {
        // Routes have no id parameter — a member can only resolve their own
        // CoopMember via Auth::user()->coopMember.
        $own = $this->createActiveMember(['email' => 'own@example.com']);

        // Craft a request with someone else's member id — it must be ignored.
        $response = $this->actingAs($own['user'])
            ->get(route('user.profile', ['member' => 99999]));

        $response->assertOk(); // resolves own member regardless of any id
    }

    /** @test */
    public function user_role_gets_403_on_profile()
    {
        $user = User::factory()->create(['usertype' => Permissions::ROLE_USER]);

        $this->actingAs($user)->get(route('user.profile'))->assertForbidden();
        $this->actingAs($user)->put(route('user.profile.update'))->assertForbidden();
    }

    /** @test */
    public function staff_gets_403_on_profile()
    {
        $admin = User::factory()->create(['usertype' => 'admin']);
        $bm = User::factory()->create(['usertype' => 'branch_manager']);
        $lo = User::factory()->create(['usertype' => 'loan_officer']);
        $ci = User::factory()->create(['usertype' => 'credit_investigator']);

        foreach ([$admin, $bm, $lo, $ci] as $staff) {
            $this->actingAs($staff)->get(route('user.profile'))->assertForbidden();
            $this->actingAs($staff)->put(route('user.profile.update'))->assertForbidden();
        }
    }

    /** @test */
    public function member_without_active_coop_link_gets_403()
    {
        $user = $this->createRegisteredUser();

        $this->actingAs($user)->get(route('user.profile'))->assertForbidden();
        $this->actingAs($user)->put(route('user.profile.update'))->assertForbidden();
    }

    /** @test */
    public function invalid_phone_is_rejected()
    {
        $data = $this->createActiveMember(
            ['email' => 'phone@example.com'],
            ['contact_number' => '09123456789']
        );

        $this->actingAs($data['user'])
            ->put(route('user.profile.update'), [
                'contact_number' => 'not-a-phone',
                'email' => $data['member']->email,
                'current_password' => 'password',
                'password' => 'secret',
                'password_confirmation' => 'secret',
            ])
            ->assertSessionHasErrors('contact_number');

        // Phone unchanged
        $this->assertSame('09123456789', $data['member']->fresh()->contact_number);
    }

    /** @test */
    public function read_only_fields_are_ignored_on_update()
    {
        $data = $this->createActiveMember(
            ['email' => 'readonly@example.com'],
            [
                'member_id' => 'MEM-2026-0001',
                'status' => CoopMember::STATUS_ACTIVE,
                'savings_balance' => 10000,
                'monthly_income' => 30000,
                'existing_monthly_obligations' => 5000,
                'approved_credit_limit' => 50000,
            ]
        );

        $this->actingAs($data['user'])
            ->put(route('user.profile.update'), [
                'email' => $data['member']->email,
                'member_id' => 'MEM-999-9999',
                'status' => 'suspended',
                'savings_balance' => 999999,
                'monthly_income' => 999999,
                'existing_monthly_obligations' => 999999,
                'approved_credit_limit' => 999999,
                'current_password' => 'password',
                'password' => 'secret',
                'password_confirmation' => 'secret',
            ])
            ->assertSessionHas('success');

        $member = $data['member']->fresh();
        $this->assertSame('MEM-2026-0001', $member->member_id);
        $this->assertSame('active', $member->status);
        $this->assertSame(10000, $member->savings_balance);
        $this->assertSame(30000, $member->monthly_income);
        $this->assertSame(5000, $member->existing_monthly_obligations);
        $this->assertSame(50000, $member->approved_credit_limit);
    }

    /** @test */
    public function email_change_syncs_users_and_coop_members()
    {
        $data = $this->createActiveMember([
            'email' => 'before@example.com',
        ]);

        $this->actingAs($data['user'])
            ->put(route('user.profile.update'), [
                'email' => 'after@example.com',
                'current_password' => 'password',
                'password' => 'secret',
                'password_confirmation' => 'secret',
            ])
            ->assertSessionHas('success');

        $this->assertSame('after@example.com', $data['user']->fresh()->email);
        $this->assertSame('after@example.com', $data['member']->fresh()->email);
    }

    /** @test */
    public function email_change_without_current_password_is_rejected()
    {
        $data = $this->createActiveMember([
            'email' => 'nopass@example.com',
        ]);

        $this->actingAs($data['user'])
            ->put(route('user.profile.update'), [
                'email' => 'changed@example.com',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('nopass@example.com', $data['user']->fresh()->email);
        $this->assertSame('nopass@example.com', $data['member']->fresh()->email);
    }

    /** @test */
    public function contact_address_occupation_update_without_current_password()
    {
        $data = $this->createActiveMember(
            ['email' => 'nopass2@example.com'],
            [
                'contact_number' => '09123456789',
                'address' => 'Old Address',
                'occupation' => 'Old Job',
            ]
        );

        $this->actingAs($data['user'])
            ->put(route('user.profile.update'), [
                'contact_number' => '09876543210',
                'address' => 'New Address',
                'occupation' => 'New Job',
                'email' => $data['member']->email,
            ])
            ->assertSessionHas('success');

        $member = $data['member']->fresh();
        $this->assertSame('09876543210', $member->contact_number);
        $this->assertSame('New Address', $member->address);
        $this->assertSame('New Job', $member->occupation);
        $this->assertSame('nopass2@example.com', $member->email); // unchanged
    }

    /** @test */
    public function duplicate_email_is_blocked()
    {
        $data = $this->createActiveMember(['email' => 'own@example.com']);

        // Create another member with a taken email
        User::factory()->create(['usertype' => 'member', 'email' => 'taken@example.com']);

        $this->actingAs($data['user'])
            ->put(route('user.profile.update'), [
                'email' => 'taken@example.com',
                'current_password' => 'password',
                'password' => 'secret',
                'password_confirmation' => 'secret',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('own@example.com', $data['user']->fresh()->email);
        $this->assertSame('own@example.com', $data['member']->fresh()->email);
    }

    /** @test */
    public function audit_log_is_written_on_profile_update()
    {
        $data = $this->createActiveMember(
            ['email' => 'audit@example.com'],
            [
                'contact_number' => '09123456789',
                'address' => 'Old Address',
                'occupation' => 'Old Job',
            ]
        );

        $this->actingAs($data['user'])
            ->put(route('user.profile.update'), [
                'email' => 'audit@example.com',
                'contact_number' => '09999999999',
                'address' => 'New Address',
                'occupation' => 'New Job',
                'current_password' => 'password',
                'password' => 'secret',
                'password_confirmation' => 'secret',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Member Updated Own Profile',
            'target_type' => 'App\Models\CoopMember',
            'target_id' => $data['member']->id,
        ]);
    }

    /** @test */
    public function no_audit_log_when_no_fields_changed()
    {
        $data = $this->createActiveMember(
            ['email' => 'nochange@example.com'],
            ['contact_number' => '09123456789']
        );

        // Submit exactly the current values — nothing changes, so no audit log.
        $this->actingAs($data['user'])
            ->put(route('user.profile.update'), [
                'email' => 'nochange@example.com',
                'contact_number' => '09123456789',
            ]);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'Member Updated Own Profile',
            'target_id' => $data['member']->id,
        ]);
    }
}
