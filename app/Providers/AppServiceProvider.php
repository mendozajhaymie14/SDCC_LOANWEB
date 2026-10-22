<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        self::warnIfSessionDriverIsFile();

        $this->registerRateLimiters();
    }

    /**
     * Warn (non-testing) when SESSION_DRIVER=file, which does NOT enforce idle timeout.
     */
    public static function warnIfSessionDriverIsFile(): void
    {
        if (config('session.driver') === 'file' && config('app.env') !== 'testing') {
            \Log::warning(
                'SESSION_DRIVER=file does not enforce the 15-minute idle timeout; ' .
                'set SESSION_DRIVER=database and run php artisan migrate.'
            );
        }
    }

    /**
     * Rate limiters for the public entry points.
     *
     *  - login          : five attempts a minute per e-mail address and per IP.
     *  - registration   : five registrations a minute per IP.
     *  - member-verification: five code requests a minute, per IP and per
     *    member ID, so one caller cannot spam a member's inbox.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $field = config('fortify.username', 'email');
            $email = strtolower((string) $request->input($field));

            return [
                Limit::perMinute(5)->by('login-email:' . $email),
                Limit::perMinute(5)->by('login-ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(5)->by('register-ip:' . $request->ip());
        });

        RateLimiter::for('member-verification', function (Request $request) {
            $memberId = strtoupper(trim((string) $request->input('member_id')));

            return [
                Limit::perMinute(5)->by('verify-ip:' . $request->ip()),
                Limit::perMinute(5)->by('verify-member:' . $memberId),
            ];
        });
    }
}
