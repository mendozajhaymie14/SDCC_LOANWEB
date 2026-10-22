<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Staff account management (FR-01): admin creates/updates/deletes staff
 * accounts, with password rules, audit logging and a generated password that
 * is shown once and never persisted.
 */
class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The acting administrator.
     *
     * `manage_users` is granted to the admin role only (see the PERMISSIONS
     * matrix) — the branch manager cannot reach the staff-management routes.
     */
    private function actingAdmin(): User
    {
        $admin = User::factory()->asAdmin()->create();

        $this->actingAs($admin);

        return $admin;
    }

    /**
     * Non-admins cannot reach the staff-management routes at all.
     *
     * @dataProvider nonAdminRoleProvider
     */
    public function test_non_admin_cannot_create_staff_account(string $role): void
    {
        $user = User::factory()->create(['usertype' => $role]);

        $this->actingAs($user);

        $response = $this->post('/admin/users', [
            'name'  => 'Attempted Staff',
            'email' => 'attempted@sdcc.coop',
            'role'  => Permissions::ROLE_ADMIN,
        ]);

        // The route is gated by role:manage_users; a non-admin never reaches
        // the controller, so this is a 403, not a validation 422.
        $response->assertForbidden();
    }

    public static function nonAdminRoleProvider(): array
    {
        return [
            'member'               => [Permissions::ROLE_MEMBER],
            'registered_non_member' => [Permissions::ROLE_USER],
        ];
    }

    /**
     * The role field must be one of the four staff roles.
     */
    public function test_admin_cannot_create_a_non_staff_role(): void
    {
        $this->actingAdmin();

        $response = $this->post('/admin/users', [
            'name'  => 'Not Staff',
            'email' => 'notstaff@sdcc.coop',
            'role'  => Permissions::ROLE_MEMBER,
        ]);

        $response->assertSessionHasErrors(['role']);
    }

    /**
     * The password must be at least 8 characters.
     */
    public function test_password_rule_enforced_on_create(): void
    {
        $this->actingAdmin();

        $response = $this->post('/admin/users', [
            'name'     => 'Short Pwd Officer',
            'email'    => 'shortpwd@sdcc.coop',
            'role'     => Permissions::ROLE_LOAN_OFFICER,
            'password' => 'short',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /**
     * An admin cannot edit their own account (422).
     */
    public function test_admin_cannot_edit_own_account(): void
    {
        $admin = $this->actingAdmin();

        $response = $this->put("/admin/users/{$admin->id}", [
            'name'  => 'Admin Renamed',
            'email' => $admin->email,
            'role'  => Permissions::ROLE_BRANCH_MANAGER,
        ]);

        $response->assertStatus(422);
    }

    /**
     * An admin cannot delete their own account (422).
     */
    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->actingAdmin();

        $response = $this->delete("/admin/users/{$admin->id}");

        $response->assertStatus(422);
    }

    /**
     * A generated password is returned once in the JSON response, is never
     * stored in plaintext in the database, is never written to the audit log
     * and never appears in the Laravel log file.
     */
    public function test_generated_password_is_returned_once_and_never_persisted(): void
    {
        $this->actingAdmin();

        $response = $this->post('/admin/users', [
            'name'  => 'Generated Pwd Officer',
            'email' => 'generated@sdcc.coop',
            'role'  => Permissions::ROLE_LOAN_OFFICER,
            // password omitted → generated once
        ]);

        $response->assertOk();

        $payload = $response->json();
        $this->assertArrayHasKey('password', $payload, 'The generated password is returned once.');
        $generated = $payload['password'];
        $this->assertIsString($generated);
        $this->assertGreaterThan(15, strlen($generated), 'Generated password is 16 chars.');

        $user = User::where('email', 'generated@sdcc.coop')->first();
        $this->assertNotNull($user);

        // Never stored in plaintext: the stored value is a bcrypt hash.
        $this->assertNotSame($generated, $user->password);
        $this->assertStringStartsWith('$2', $user->password, 'Stored password is a bcrypt hash.');

        // Never written to the audit log.
        $audit = AuditLog::where('action', 'Staff Account Created')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString($generated, $audit->details ?? '');
        $this->assertStringNotContainsString($generated, json_encode($audit->new_values ?? []));

        // Never written to the Laravel log file.
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $this->assertStringNotContainsString($generated, file_get_contents($logPath),
                'The generated password must not appear in laravel.log.');
        }
    }
}