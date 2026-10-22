<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Proves the permission matrix is visible in the UI, not only in the middleware.
 *
 * A control that a role can see but not use is a broken control: the click 403s.
 * These tests render the real pages as each role and assert that every link and
 * form the role is shown points at a route that role may actually reach.
 */
class ViewPermissionGatingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Staff roles that render the admin panel. The member role is excluded:
     * it lives in the portal, not the admin chrome.
     */
    public static function staffRoleProvider(): array
    {
        return [
            'admin'               => [Permissions::ROLE_ADMIN],
            'branch_manager'      => [Permissions::ROLE_BRANCH_MANAGER],
            'loan_officer'        => [Permissions::ROLE_LOAN_OFFICER],
            'credit_investigator' => [Permissions::ROLE_CREDIT_INVESTIGATOR],
        ];
    }

    /**
     * Resolve the permission a route requires, by matching the path against the
     * router. Returns null when the path is not an app route (or is public).
     */
    private function permissionForPath(string $path, string $method = 'GET'): ?string
    {
        foreach ([$method, 'GET', 'POST'] as $try) {
            try {
                $route = Route::getRoutes()->match(Request::create($path, $try));
            } catch (\Throwable $e) {
                continue;
            }

            $middleware = data_get($route->getAction(), 'middleware', []);

            foreach ($middleware as $m) {
                if (! is_string($m)) continue;
                if (str_starts_with($m, 'role:')) {
                    return substr($m, strlen('role:'));
                }
                if (str_starts_with($m, \App\Http\Middleware\RoleMiddleware::class . ':')) {
                    return substr($m, strlen(\App\Http\Middleware\RoleMiddleware::class . ':'));
                }
            }

            return null; // matched, but public / unguarded
        }

        return null;
    }

    /**
     * Pull every internal href and form action out of a rendered page.
     *
     * @return array<string, string> path => source snippet
     */
    private function internalTargets(string $html): array
    {
        preg_match_all('/(?:href|action)="([^"]+)"/', $html, $matches);

        $targets = [];

        foreach ($matches[1] as $raw) {
            $path = parse_url($raw, PHP_URL_PATH);

            if (! is_string($path) || $path === '') continue;
            if (! str_starts_with($path, '/')) continue;   // external / anchor / mailto
            if (str_starts_with($path, '/storage/')) continue;
            // JS template literals (e.g. "/active-members/${m.id}/approve") are built at
            // runtime; the markup around them is gated by a permission flag instead,
            // which the dedicated button tests assert directly.
            if (str_contains($path, '${')) continue;

            $targets[$path] = $raw;
        }

        return $targets;
    }

    /**
     * Every control the role is shown on the admin dashboard must lead to a
     * route that role is allowed to reach.
     *
     * @dataProvider staffRoleProvider
     */
    public function test_dashboard_shows_no_control_pointing_at_an_inaccessible_route(string $role): void
    {
        $user = User::factory()->create(['usertype' => $role]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        $this->assertNoInaccessibleControl($user, '/dashboard', $response->getContent());
    }

    /**
     * The same rule for every page the role is allowed to open.
     *
     * @dataProvider staffRoleProvider
     */
    public function test_pages_show_no_control_pointing_at_an_inaccessible_route(string $role): void
    {
        $user = User::factory()->create(['usertype' => $role]);

        $application = $this->makeApplication();

        $pages = [
            Permissions::VIEW_DASHBOARD       => '/dashboard',
            Permissions::VIEW_MEMBER_PROFILES => '/active-members',
            Permissions::VIEW_APPLICATIONS    => '/admin/applications/' . $application->id,
            Permissions::MANAGE_USERS         => '/admin/users',
            Permissions::APPROVE_MEMBERSHIPS  => '/memberships',
            Permissions::PROCESS_DISBURSEMENTS => '/disbursements',
            Permissions::RECORD_PAYMENTS      => '/repayments',
            Permissions::VIEW_REPORTS         => '/reports',
            Permissions::VERIFY_DOCUMENTS     => '/admin/risk-flags',
            Permissions::VIEW_AUDIT_LOGS      => '/admin/audit-logs',
            Permissions::STAFF_ACTIVITY       => '/admin/notifications-log',
            Permissions::VIEW_EVALUATION      => '/admin/credit-assessment',
            Permissions::MANAGE_SETTINGS      => '/admin/settings',
            Permissions::MANAGE_BACKUPS       => '/admin/backup',
        ];

        foreach ($pages as $permission => $path) {
            if (! Permissions::has($user, $permission)) {
                continue;
            }

            $response = $this->actingAs($user)->get($path);
            $response->assertOk("Role '{$role}' cannot open {$path}");

            $this->assertNoInaccessibleControl($user, $path, $response->getContent());
        }
    }

    /**
     * Assert that no link or form on the page points at a route the role may
     * not reach.
     */
    private function assertNoInaccessibleControl(User $user, string $path, string $html): void
    {
        $violations = [];

        foreach ($this->internalTargets($html) as $target => $raw) {
            $permission = $this->permissionForPath($target);

            if ($permission === null) continue;

            if (! Permissions::has($user, $permission)) {
                $violations[] = "{$target} (requires '{$permission}')";
            }
        }

        $this->assertEmpty($violations,
            "Role '{$user->usertype}' is shown controls on {$path} it cannot use: " . implode(', ', $violations));
    }

    /**
     * A pending application to open the detail page on.
     */
    private function makeApplication(): Application
    {
        $member = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER]);
        CoopMember::factory()->create(['user_id' => $member->id, 'share_capital' => 30000]);
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $member->id,
            'full_name' => 'Test Borrower',
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
            'applicant' => 'Test Borrower',
            'status' => 'Pending',
            'recommended_amount' => 2500.00,
            'risk_level' => 'Medium',
            'risk_score' => 60,
        ]);
    }

    /**
     * The "Add Member" button belongs to edit_member_profiles, not view_member_profiles.
     * Credit Investigator and Admin may look at members but must not see the button.
     *
     * @dataProvider staffRoleProvider
     */
    public function test_add_member_button_follows_edit_member_profiles(string $role): void
    {
        $user = User::factory()->create(['usertype' => $role]);

        $response = $this->actingAs($user)->get('/active-members');

        if (! Permissions::has($user, Permissions::VIEW_MEMBER_PROFILES)) {
            $response->assertForbidden();
            return;
        }

        $response->assertOk();

        $seesButton = str_contains($response->getContent(), '+ Add Member');

        $this->assertSame(
            Permissions::has($user, Permissions::EDIT_MEMBER_PROFILES),
            $seesButton,
            "Role '{$role}' button visibility for '+ Add Member' does not follow edit_member_profiles"
        );

        // The JS-built member modal (activate / suspend / delete) is gated by a flag
        // rather than by server-side markup; the flag must follow the same matrix.
        $this->assertStringContainsString(
            'edit: ' . (Permissions::has($user, Permissions::EDIT_MEMBER_PROFILES) ? 'true' : 'false'),
            $response->getContent(),
            "Role '{$role}' member modal flag does not follow edit_member_profiles"
        );
    }

    /**
     * Approve / Reject on an application belong to approve_loans / reject_loans.
     * A credit investigator may read the application but must see no decision button.
     *
     * @dataProvider staffRoleProvider
     */
    public function test_decision_buttons_follow_loan_decision_permissions(string $role): void
    {
        $user = User::factory()->create(['usertype' => $role]);

        $application = $this->makeApplication();

        $response = $this->actingAs($user)->get('/admin/applications/' . $application->id);

        if (! Permissions::has($user, Permissions::VIEW_APPLICATIONS)) {
            $response->assertForbidden();
            return;
        }

        $response->assertOk();
        $html = $response->getContent();

        $this->assertSame(
            Permissions::has($user, Permissions::APPROVE_LOANS),
            str_contains($html, route('applications.approve', ['appId' => $application->app_id])),
            "Role '{$role}' approve button does not follow approve_loans"
        );

        $this->assertSame(
            Permissions::has($user, Permissions::REJECT_LOANS),
            str_contains($html, route('applications.reject', ['appId' => $application->app_id])),
            "Role '{$role}' reject button does not follow reject_loans"
        );
    }

    /**
     * "Add Staff" is manage_users; "Add Membership Application" is approve_memberships.
     * Both pages are only reachable by the role that owns them, so this asserts
     * the button and the page permission agree rather than 403-ing.
     *
     * @dataProvider staffRoleProvider
     */
    public function test_admin_and_membership_buttons_follow_their_permissions(string $role): void
    {
        $user = User::factory()->create(['usertype' => $role]);

        $admins = $this->actingAs($user)->get('/admin/users');
        if (Permissions::has($user, Permissions::MANAGE_USERS)) {
            $admins->assertOk();
            $this->assertStringContainsString('+ Add Staff', $admins->getContent());
        } else {
            $admins->assertForbidden();
        }

        $memberships = $this->actingAs($user)->get('/memberships');
        if (Permissions::has($user, Permissions::APPROVE_MEMBERSHIPS)) {
            $memberships->assertOk();
            $this->assertStringContainsString('+ Add Membership Application', $memberships->getContent());
        } else {
            $memberships->assertForbidden();
        }
    }

    /**
     * Release records are process_disbursements (branch manager only). The nav
     * link, the KPI card and the page itself must agree.
     *
     * @dataProvider staffRoleProvider
     */
    public function test_release_records_are_visible_only_to_the_branch_manager(string $role): void
    {
        $user = User::factory()->create(['usertype' => $role]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        $this->assertSame(
            Permissions::has($user, Permissions::PROCESS_DISBURSEMENTS),
            str_contains($response->getContent(), 'href="' . route('disbursements.index') . '"'),
            "Role '{$role}' sees the Release Records control without process_disbursements"
        );
    }
}
