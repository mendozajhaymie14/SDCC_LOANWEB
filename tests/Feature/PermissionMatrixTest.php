<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The permission matrix, proven cell by cell.
 *
 * Every role × permission cell is exercised against the REAL route that
 * carries the permission, so a route that is renamed, moved or loses its
 * middleware fails here instead of in production.
 *
 * Six roles: four staff, plus 'member' (linked to a cooperative membership)
 * and 'user' (registered, but not a member).
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Probe route + method per permission key. Some routes need an existing row
     * (marked with 'fixture' => 'create_application' or 'create_loan') so that
     * SubstituteBindings runs before the role middleware and we get 403 not 404.
     */
    protected static $probes = [
        'manage_users'          => ['route' => '/admin/users', 'method' => 'GET'],
        'manage_settings'       => ['route' => '/admin/settings', 'method' => 'GET'],
        'manage_backups'        => ['route' => '/admin/backup', 'method' => 'GET'],
        'view_audit_logs'       => ['route' => '/admin/audit-logs', 'method' => 'GET'],
        'view_dashboard'        => ['route' => '/dashboard', 'method' => 'GET'],
        'view_member_profiles'  => ['route' => '/active-members', 'method' => 'GET'],
        'edit_member_profiles'  => ['route' => '/active-members/1/approve', 'method' => 'POST', 'fixture' => 'create_coop_member'],
        'encode_applications'   => ['route' => '/applications', 'method' => 'GET'],
        'view_applications'     => ['route' => '/admin/applications/1', 'method' => 'GET', 'fixture' => 'create_application'],
        'view_evaluation'       => ['route' => '/admin/credit-assessment', 'method' => 'GET'],
        'approve_loans'         => ['route' => '/applications/1/approve', 'method' => 'POST', 'fixture' => 'create_application'],
        'reject_loans'          => ['route' => '/applications/1/reject', 'method' => 'POST', 'fixture' => 'create_application'],
        'record_payments'       => ['route' => '/repayments', 'method' => 'GET'],
        'view_reports'          => ['route' => '/reports', 'method' => 'GET'],
        'staff_activity'        => ['route' => '/admin/notifications-log', 'method' => 'GET'],
        'verify_documents'      => ['route' => '/admin/risk-flags', 'method' => 'GET'],
        'add_review_notes'      => ['route' => '/admin/applications/1/notes', 'method' => 'POST', 'fixture' => 'create_application'],
        'validate_recommendation' => ['route' => '/admin/applications/1/recommendation', 'method' => 'POST', 'fixture' => 'create_application'],
        'approve_memberships'   => ['route' => '/memberships', 'method' => 'GET'],
        'process_disbursements' => ['route' => '/disbursements', 'method' => 'GET'],
        'own_portal'            => ['route' => '/user/dashboard', 'method' => 'GET'],
        'view_own_loans'        => ['route' => '/user/loans/1/amortization', 'method' => 'GET', 'fixture' => 'create_loan'],
        'apply_membership'      => ['route' => '/user/membership', 'method' => 'GET'],
        'apply_loan'            => ['route' => '/user/loans/apply', 'method' => 'GET', 'fixture' => 'create_active_member'],
    ];

    /** The six roles of the matrix. */
    public static function provider(): array
    {
        $roles = [
            Permissions::ROLE_ADMIN,
            Permissions::ROLE_BRANCH_MANAGER,
            Permissions::ROLE_LOAN_OFFICER,
            Permissions::ROLE_CREDIT_INVESTIGATOR,
            Permissions::ROLE_MEMBER,
            Permissions::ROLE_USER,
        ];

        $cases = [];

        foreach ($roles as $role) {
            foreach (static::$probes as $permKey => $perm) {
                $cases[] = [
                    'role'     => $role,
                    'perm'     => $permKey,
                    'method'   => $perm['method'],
                    'route'    => $perm['route'],
                    'fixture'  => $perm['fixture'] ?? null,
                ];
            }
        }

        return $cases;
    }

    /** @dataProvider provider */
    public function test_permission_matrix(
        string $role,
        string $perm,
        string $method,
        string $route,
        ?string $fixture
    ): void {
        $usertype = $role; // role name == usertype string ('admin', 'branch_manager', ...)

        $user = User::factory()->create(['usertype' => $usertype]);

        // Fixture setup
        if ($fixture === 'create_application') {
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
            Application::factory()->create([
                'loan_application_id' => $loanApp->id,
                'app_id' => 'APP-' . str_pad($loanApp->id, 4, '0', STR_PAD_LEFT),
                'applicant' => 'Test Borrower',
                'status' => 'Pending',
                'recommended_amount' => 2500.00,
                'risk_level' => 'Medium',
                'risk_score' => 60,
            ]);
        } elseif ($fixture === 'create_coop_member') {
            $member = User::factory()->create(['usertype' => Permissions::ROLE_MEMBER]);
            CoopMember::factory()->create(['user_id' => $member->id, 'share_capital' => 30000]);
        } elseif ($fixture === 'create_loan') {
            CoopMember::factory()->create(['user_id' => $user->id, 'share_capital' => 30000]);
            LoanApplication::factory()->create([
                'user_id' => $user->id,
                'full_name' => $user->name,
                'email' => $user->email,
                'loan_type' => 'regular',
                'amount' => 5000,
                'status' => 'approved',
                'monthly_income' => 20000,
                'existing_monthly_obligations' => 0,
                'share_capital' => 30000,
                'term_months' => 12,
            ]);
        } elseif ($fixture === 'create_active_member') {
            // The loan form is only reachable by a member whose cooperative
            // membership is live, so the probe needs exactly that.
            if ($usertype === Permissions::ROLE_MEMBER) {
                $coopMember = CoopMember::factory()->create([
                    'user_id'       => $user->id,
                    'status'        => CoopMember::STATUS_ACTIVE,
                    'share_capital' => 30000,
                ]);

                $user->forceFill(['coop_member_id' => $coopMember->id])->save();
            }
        }

        $allowed = Permissions::has($user, $perm);

        // A portal role (member or user) hitting /dashboard is sent to its own
        // portal rather than to the staff panel.
        if ($perm === Permissions::VIEW_DASHBOARD && Permissions::has($user, Permissions::OWN_PORTAL)) {
            $this->actingAs($user)->get('/dashboard')->assertRedirect(route('user.dashboard'));
            return;
        }

        $response = $method === 'GET'
            ? $this->actingAs($user)->get($route)
            : $this->actingAs($user)->post($route);

        if ($allowed) {
            // Allowed: the route answers (200) or redirects back (302).
            $this->assertContains($response->getStatusCode(), [200, 302],
                "Role '$usertype' permission '$perm': expected 200/302 but got {$response->getStatusCode()}");
        } elseif ($perm === Permissions::APPLY_LOAN && Permissions::has($user, Permissions::OWN_PORTAL)) {
            // A registered non-member is not shown a raw 403 for the loan
            // form: they are taken to "Become a member" with a message.
            $response->assertRedirect(route('user.membership'));
            $response->assertSessionHas('error');
        } else {
            $this->assertEquals(403, $response->getStatusCode(),
                "Role '$usertype' permission '$perm': expected 403 but got {$response->getStatusCode()}");
        }
    }

    /**
     * Every authenticated route must declare at least one `role:<permission>` middleware.
     * Scans the router and fails if any auth-protected route lacks a permission declaration.
     */
    public function test_every_authenticated_route_declares_a_permission(): void
    {
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction();
            $middleware = data_get($action, 'middleware', []);
            $controller = data_get($action, 'controller', '');

            // Only check app-owned routes. Framework routes (Fortify/Jetstream
            // profile, 2FA, passkeys, teams) carry their own 'auth' and are
            // self-service account management; intentionally outside the matrix.
            if ($controller !== '' && str_starts_with($controller, 'Laravel\\')) {
                continue;
            }

            // Only check routes inside the web group (which have 'auth')
            $hasAuth = collect($middleware)->contains(function ($m) {
                if (! is_string($m)) return false;
                return $m === 'auth' || str_starts_with($m, 'auth:');
            });

            if (! $hasAuth) {
                continue; // public route
            }

            // Must have at least one 'role:xxx' middleware
            $hasRole = collect($middleware)->contains(function ($m) {
                if (! is_string($m)) return false;
                return str_starts_with($m, 'role:');
            });

            if (! $hasRole) {
                $missing[] = $route->uri() . ' [' . $route->methods()[0] . ']';
            }
        }

        $this->assertEmpty($missing,
            'The following authenticated routes lack a role: permission middleware: ' . implode(', ', $missing));
    }

    /**
     * A route with only ['auth','role'] (group guard, no specific permission) must be denied by default.
     * Register a probe route at runtime to assert deny-by-default behaviour.
     */
    public function test_route_without_a_permission_is_denied_by_default(): void
    {
        $testRoute = '/__test_deny_by_default_' . uniqid();
        Route::middleware(['auth', 'role'])->get($testRoute, function () {
            return response('ok', 200);
        });

        $user = User::factory()->create(['usertype' => Permissions::ROLE_ADMIN]);
        $response = $this->actingAs($user)->get($testRoute);

        $this->assertEquals(403, $response->getStatusCode(),
            'Route without explicit role: permission must be denied by default');
    }
}
