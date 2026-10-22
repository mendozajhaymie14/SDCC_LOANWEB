<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SessionTimeoutTest exercises the production database session driver
     * (phpunit.xml defaults to array so BrowserSessionsTest keeps working with
     * Livewire's middleware-less test harness, which throws "Session store not
     * set on request" when config('session.driver') === 'database').
     */
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('session.driver', 'database');
        app(SessionManager::class)->forgetDrivers();
        app()->forgetInstance('session');
    }

    /**
     * Data provider: 6 roles × the expiry path.
     */
    public static function roleProvider(): array
    {
        return [
            'admin'                => [Permissions::ROLE_ADMIN],
            'branch_manager'       => [Permissions::ROLE_BRANCH_MANAGER],
            'loan_officer'         => [Permissions::ROLE_LOAN_OFFICER],
            'credit_investigator'  => [Permissions::ROLE_CREDIT_INVESTIGATOR],
            'member'               => [Permissions::ROLE_MEMBER],
            'user'                 => [Permissions::ROLE_USER],
        ];
    }

    /**
     * Log in through the real Fortify endpoint and return the session cookie.
     *
     * The test client keeps no cookie jar, so the cookie has to be handed back
     * to the next request explicitly. The value is already encrypted by
     * EncryptCookies, hence withUnencryptedCookie() (withCookie() would
     * encrypt it a second time).
     */
    private function loginAndGetSessionCookie(User $user): \Symfony\Component\HttpFoundation\Cookie
    {
        $loginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);
        $loginResponse->assertRedirect();

        $cookieName = config('session.cookie');

        foreach ($loginResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return $cookie;
            }
        }

        $this->fail('No session cookie was set on login.');
    }

    /**
     * The protected route each role lands on: portal roles on their own
     * dashboard, staff on the admin panel.
     */
    private function protectedRouteFor(string $role): string
    {
        return in_array($role, [Permissions::ROLE_MEMBER, Permissions::ROLE_USER], true)
            ? '/user/dashboard'
            : '/dashboard';
    }

    /**
     * Forget the in-memory session attributes and the cached auth guard so the
     * next request genuinely re-reads the session from storage.
     */
    private function resetInMemoryAuthState(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }

    /**
     * @dataProvider roleProvider
     */
    public function test_session_is_closed_after_fifteen_minutes_of_inactivity(string $role): void
    {
        $user = User::factory()->create([
            'usertype' => $role,
            'password' => bcrypt('password123'),
        ]);

        $cookie = $this->loginAndGetSessionCookie($user);

        // Idle for 16 minutes: the database session handler drops sessions whose
        // last_activity is older than now() - 15 minutes.
        DB::table('sessions')->update([
            'last_activity' => Carbon::now()->subMinutes(16)->getTimestamp(),
        ]);

        $this->resetInMemoryAuthState();

        $protectedRoute = $this->protectedRouteFor($role);
        $response = $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get($protectedRoute);

        // Session expired → redirect to login, no longer authenticated.
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * @dataProvider roleProvider
     */
    public function test_session_survives_activity_within_fifteen_minutes(string $role): void
    {
        $user = User::factory()->create([
            'usertype' => $role,
            'password' => bcrypt('password123'),
        ]);

        $cookie = $this->loginAndGetSessionCookie($user);

        // Idle for 14 minutes — still inside the 15-minute window.
        DB::table('sessions')->update([
            'last_activity' => Carbon::now()->subMinutes(14)->getTimestamp(),
        ]);

        $this->resetInMemoryAuthState();

        $protectedRoute = $this->protectedRouteFor($role);
        $response = $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get($protectedRoute);

        // Still authenticated (200 for the staff panel, 200 for the member portal).
        $response->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    /**
     * The file driver does not expire idle sessions on read — the app must say so.
     */
    public function test_boot_warns_when_session_driver_is_file(): void
    {
        Config::set('session.driver', 'file');
        Config::set('app.env', 'local');

        Log::spy();

        AppServiceProvider::warnIfSessionDriverIsFile();

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'SESSION_DRIVER=file does not enforce the 15-minute idle timeout; ' .
                'set SESSION_DRIVER=database and run php artisan migrate.'
            );
    }

    /**
     * No warning when the database driver is in use.
     */
    public function test_boot_no_warning_when_session_driver_is_database(): void
    {
        Config::set('session.driver', 'database');
        Config::set('app.env', 'local');

        Log::spy();

        AppServiceProvider::warnIfSessionDriverIsFile();

        Log::shouldNotHaveReceived('warning');
    }
}