<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Throttles account registration.
 *
 * Fortify registers POST /register itself and offers no hook for a limiter
 * on that route, so the limit is applied here: five registrations a minute
 * per IP address.
 */
class ThrottleRegistration
{
    public function __construct(private readonly ThrottleRequests $throttle)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post') && $request->path() === 'register') {
            return $this->throttle->handle($request, $next, 'registration');
        }

        return $next($request);
    }
}
