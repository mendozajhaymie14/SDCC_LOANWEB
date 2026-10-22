<?php

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\ThrottleRegistration;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            $expired = false;
            // Expired sessions are detectable when the cookie is present but the
            // underlying row is gone OR the last activity timestamp predates
            // the configured idle lifetime. With the array/file drivers the
            // cookie is absent from the start and this stays generic.
            if (config('session.driver') === 'database') {
                // An idle session is expired when its last activity timestamp
                // precedes the configured lifetime. A missing row (GC'd) means
                // the cookie is stale, not active — also expires.
                $sessionId = null;
                $cookieName = config('session.cookie');
                if ($request && $request->hasCookie($cookieName)) {
                    $sessionId = $request->cookie($cookieName);
                }
                if ($sessionId) {
                    $row = DB::table(config('session.table', 'sessions'))
                        ->where('id', $sessionId)
                        ->first();
                    if ($row) {
                        $expired = (int) $row->last_activity + config('session.lifetime', 15) * 60 <= now()->timestamp;
                    } else {
                        $expired = true;
                    }
                }
            }

            $message = $expired
                ? 'Your session has expired. Please sign in again.'
                : 'Please sign in to continue.';

            $response = redirect()->guest(route('login'))->with('status', $message);

            // No explicit save here: the pipeline catches the exception and
            // renders it *inside* the middleware stack, so StartSession still
            // runs its own save() on the way out and persists the flash (and
            // the intended URL). Saving twice would age the flash away before
            // the login page ever read it.
            return $response;
        });
    })->create();
