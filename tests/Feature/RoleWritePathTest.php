<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\CoopMember;
use App\Models\MemberApplication;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Write-path tests for the `role` and `usertype` columns.
 * Every write path that sets usertype must also set role consistently.
 */
class RoleWritePathTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Plain registration → usertype='user', role='user'.
     *
     * The CreateNewUser action explicitly sets both columns (L69–L70).
     * The model's saving hook also mirrors them, but the action's write is
     * the authoritative path.
     */
    public function test_plain_registration_sets_user_in_both_columns(): void
    {
        $response = $this->post('/register', [
            'name'            => 'Alma Test',
            'email'           => 'alma@gmail.com',
            'password'        => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Registration redirects; follow through to confirm DB state.
        $user = User::where('email', 'alma@gmail.com')->first();

        $this->assertNotNull($user, 'Registration should have created a user row.');
        $this->assertSame(Permissions::ROLE_USER, $user->usertype,
            'usertype should be ROLE_USER after plain registration.');
        $this->assertSame(Permissions::ROLE_USER, $user->role,
            'role should be ROLE_USER after plain registration.');
    }

    /**
     * 2. Existing-member registration → usertype='member', role='member'.
     *
     * The session is seeded with verified-coop-member state; the register
     * path treats the applicant as an existing member and creates a linked
     * account with both columns = 'member'.
     */
    public function test_existing_member_registration_sets_member_in_both_columns(): void
    {
        $member = CoopMember::factory()->create([
            'member_id'      => 'SDCC-2023-0001',
            'email'          => 'juan@gmail.com',
            'contact_number' => '09171234567',
            'is_registered'  => false,
            'user_id'        => null,
        ]);

        $this->withSession([
            'verified_coop_member_id' => $member->id,
            'verified_coop_member_at' => now()->timestamp,
        ])->post('/register', [
            'member_id' => 'SDCC-2023-0001',
            'is_existing_member' => '1',
            'name'      => $member->full_name,
            'email'     => 'juan@gmail.com',
            'password'  => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'juan@gmail.com')->first();

        $this->assertNotNull($user, 'Registration with existing member should have created a user row.');
        $this->assertSame(Permissions::ROLE_MEMBER, $user->usertype,
            'usertype should be ROLE_MEMBER after existing-member registration.');
        $this->assertSame(Permissions::ROLE_MEMBER, $user->role,
            'role should be ROLE_MEMBER after existing-member registration.');
    }

    /**
     * 3. Membership approval → applicant's usertype/role become 'member'.
     *
     * After Branch Manager approval, the controller explicitly sets both
     * columns (L602-L693).
     */
    public function test_membership_approval_flips_user_to_member_columns(): void
    {
        $applicant = User::factory()->create([
            'name'     => 'Jane Roe',
            'email'    => 'jane@gmail.com',
            'password' => 'secret',
            'usertype' => Permissions::ROLE_USER,
        ]);

        $application = MemberApplication::create([
            'surname'       => 'Roe',
            'first_name'    => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'birthdate'     => now()->subYears(30)->format('Y-m-d'),
            'status'        => 'pending',
        ]);

        $manager = User::factory()->asBranchManager()->create();

        $this->actingAs($manager);

        $response = $this->post(route('memberships.approve', $application->id));

        $response->assertOk();

        $applicant->refresh();

        $this->assertSame(Permissions::ROLE_MEMBER, $applicant->usertype,
            'usertype should flip to ROLE_MEMBER after approval.');
        $this->assertSame(Permissions::ROLE_MEMBER, $applicant->role,
            'role should flip to ROLE_MEMBER after approval.');

        $this->assertNotNull($applicant->coop_member_id,
            'Approval should link a coop_members row.');
    }

    /**
     * 4. Membership rejection → applicant's usertype/role become 'user'.
     *
     * The controller sets both columns to ROLE_USER.
     */
    public function test_membership_rejection_keeps_user_columns(): void
    {
        $applicant = User::factory()->create([
            'name'     => 'John Doe',
            'email'    => 'jdoe@gmail.com',
            'password' => 'secret',
            'usertype' => Permissions::ROLE_MEMBER,
        ]);

        $application = MemberApplication::create([
            'surname'       => 'Doe',
            'first_name'    => 'John',
            'email'         => 'jdoe@gmail.com',
            'contact_number'=> '09171234567',
            'status'        => 'pending',
        ]);

        $manager = User::factory()->asBranchManager()->create();

        $this->actingAs($manager);

        $response = $this->post(route('memberships.reject', $application->id));

        $response->assertOk();

        $applicant->refresh();

        $this->assertSame(Permissions::ROLE_USER, $applicant->usertype,
            'usertype should be ROLE_USER after rejection.');
        $this->assertSame(Permissions::ROLE_USER, $applicant->role,
            'role should be ROLE_USER after rejection.');
    }

    /**
     * 5. Admin create staff account → both columns set to the chosen staff role.
     *
     * The storeAdmin method validates role ∈ {admin, branch_manager,
     * loan_officer, credit_investigator} and sets both columns to that role.
     *
     * All four staff roles are tested with the admin user acting.
     */
    public function test_admin_can_create_each_of_four_staff_roles(): void
    {
        // Only the administrator role has manage_users, so it is the only
        // role allowed to create staff accounts (see PERMISSIONS matrix).
        $admin = User::factory()->asAdmin()->create();

        $this->actingAs($admin);

        $staffRoles = Permissions::STAFF_ROLES; // [admin, branch_manager, loan_officer, credit_investigator]

        foreach ($staffRoles as $role) {
            $response = $this->post('/admin/users', [
                'name'      => "Staff {$role}",
                'email'     => "staff{$role}@sdcc.coop",
                'phone'     => '09171234567',
                'role'      => $role,
                // password omitted → generated once in the action
            ]);

            $response->assertOk();

            $user = User::where('email', "staff{$role}@sdcc.coop")->first();

            $this->assertNotNull($user, "Staff account for role '{$role}' should exist.");
            $this->assertSame($role, $user->role,
                "role column should be '{$role}' for staff account.");
            $this->assertSame($role, $user->usertype,
                "usertype column should be '{$role}' for staff account.");
        }
    }

    /**
     * 6. Admin update staff account → both columns move to the new role.
     */
    public function test_admin_can_update_staff_account_role(): void
    {
        // Only the administrator role has manage_users.
        $admin = User::factory()->asAdmin()->create();

        // Create a separate staff account to update (an admin cannot edit their
        // own account — that returns 422, see StaffManagementTest).
        $target = User::factory()->create([
            'name'     => 'Staff to Update',
            'email'    => 'staff-target@sdcc.coop',
            'usertype' => Permissions::ROLE_LOAN_OFFICER,
            'role'     => Permissions::ROLE_LOAN_OFFICER,
        ]);

        $this->actingAs($admin);

        $response = $this->put("/admin/users/{$target->id}", [
            'name'  => 'Updated Name',
            'email' => 'updated@sdcc.coop',
            'role'  => Permissions::ROLE_ADMIN,
        ]);

        $response->assertOk();

        $target->refresh();

        $this->assertSame(Permissions::ROLE_ADMIN, $target->role,
            'role column should move to admin after update.');
        $this->assertSame(Permissions::ROLE_ADMIN, $target->usertype,
            'usertype column should move to admin after update.');
    }

    /**
     * 7. Staff-account invariants.
     *
     * A staff account must never look like a registered non-member:
     * - role ∈ {admin, branch_manager, loan_officer, credit_investigator}
     * - usertype ∈ {admin, branch_manager, loan_officer, credit_investigator}
     * - Permissions::role($user) must be a staff role.
     * - Permissions::has($user, ...) checks using Permissions::role($user)
     *   must allow staff-only permissions and deny member‑only ones.
     */
    public function test_staff_account_never_looks_like_registered_non_member(): void
    {
        // Only the administrator role carries the staff-only permissions.
        $user = User::factory()->asAdmin()->create();

        // The role is explicit; the saving hook mirrors it into usertype.
        $this->assertSame(Permissions::ROLE_ADMIN, $user->role);
        $this->assertSame(Permissions::ROLE_ADMIN, $user->usertype);

        // Admin has staff-only permissions (broader than branch_manager).
        $this->assertTrue(Permissions::has($user, Permissions::MANAGE_USERS));
        $this->assertTrue(Permissions::has($user, Permissions::MANAGE_SETTINGS));
        $this->assertTrue(Permissions::has($user, Permissions::MANAGE_BACKUPS));
        $this->assertTrue(Permissions::has($user, Permissions::VIEW_AUDIT_LOGS));
        $this->assertTrue(Permissions::has($user, Permissions::VIEW_DASHBOARD));

        // Member‑only permissions must be denied for staff.
        $this->assertFalse(Permissions::has($user, Permissions::OWN_PORTAL));
        $this->assertFalse(Permissions::has($user, Permissions::VIEW_OWN_LOANS));
    }

    /**
     * 8. Audit log written without the generated password when creating staff.
     *
     * The AuditLog::log('Staff Account Created', ...) call records only the
     * action and display text; the generated password does NOT appear in
     * details or new_values.
     */
    public function test_audit_log_has_no_password_when_creating_staff(): void
    {
        $admin = User::factory()->asAdmin()->create();

        $this->actingAs($admin);

        $response = $this->post('/admin/users', [
            'name'      => 'Audit Test Officer',
            'email'     => 'audit@sdcc.coop',
            'role'      => Permissions::ROLE_LOAN_OFFICER,
            // password omitted → generated once, NOT written to audit log
        ]);

        $response->assertOk();

        $audit = AuditLog::where('action', 'Staff Account Created')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit, 'An audit row should have been written.');
        $this->assertStringNotContainsString('password',
            $audit->details ?? '', 'Details should not contain the password.');
        $this->assertStringNotContainsString('password',
            json_encode($audit->new_values ?? []), 'New values should not contain the password.');
    }
}