<?php

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\ThrottleRegistration;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\DB;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Redirect authenticated users to /dashboard
        $middleware->redirectTo(
            users: '/dashboard'
        );

        // Role-based access control alias
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        // Registration throttling (Fortify registers POST /register itself)
        $middleware->append(ThrottleRegistration::class);

        // Rate limit the public member lookup endpoint
        $middleware->statefulApi();
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // NFR-05: a timed-out session must tell the user *why* they were bounced,
        // not just that they were. JSON requests get a 401; web requests get a
        // redirect with a flash message. redirect()->guest() preserves the
        // intended URL so the user is returned to the page they were on after
        // re-signing in.
        $exceptions->render(function (AuthenticationException $e) {
            $request = $e->getRequest();

            if ($request && $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // Detect a genuine session expiry (as opposed to a plain "not logged
            // in" hit) by checking the database session store: an expired session
            // was readable but is now gone, so the cookie is present but the row
            // is not. Under the array/file driver the cookie itself is simply
            // missing and this stays generic.
            $expired = false;
            if (config('session.driver') === 'database') {
                $sessionId = null;
                $cookieName = config('session.cookie');
                if ($request && $request->hasCookie($cookieName)) {
                    $sessionId = $request->cookie($cookieName);
                }
                if ($sessionId) {
                    $expired = !DB::table(config('session.table', 'sessions'))
                        ->where('id', $sessionId)
                        ->exists();
                }
            }

            $message = $expired
                ? 'Your session has expired. Please sign in again.'
                : 'Please sign in to continue.';

            $redirect = redirect()->guest(route('login'))->with('status', $message);

            if ($request && $request->wantsJson()) {
                return $redirect;
            }

            return $redirect;
        });
    })->create();
