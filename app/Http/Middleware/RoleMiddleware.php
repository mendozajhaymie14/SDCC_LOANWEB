<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the user is not authenticated, let the auth middleware handle it.
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $usertype = $user->usertype ?? 'user';
        $path = $request->path();

        // Define the exception list for admin-only routes.
        $exceptionList = [
            'user/*',
            'member-application*',
            'faqs',
            '',
            'logout',
            'dashboard',
        ];

        // Check if the current path matches any of the exception patterns.
        $isException = false;
        foreach ($exceptionList as $pattern) {
            if ($this->pathMatchesPattern($path, $pattern)) {
                $isException = true;
                break;
            }
        }

        if (!$isException) {
            // Admin-only route: only 'admin' type
            if ($usertype !== 'admin') {
                abort(403, 'Unauthorized action.');
            }
        } else {
            // Exception route
            if ($this->pathMatchesPattern($path, 'user/*')) {
                // Allow 'user', 'member', and empty/null usertype
                if (!in_array($usertype, ['member', 'user'], true)) {
                    abort(403, 'Unauthorized action.');
                }
            }
            // Other exception routes allow any authenticated user.
        }

        return $next($request);
    }

    /**
     * Check if the given path matches a simple pattern.
     * Supports * wildcard at the end only.
     *
     * @param  string  $path
     * @param  string  $pattern
     * @return bool
     */
    protected function pathMatchesPattern(string $path, string $pattern): bool
    {
        if (substr($pattern, -1) === '*') {
            $prefix = substr($pattern, 0, -1);
            return str_starts_with($path, $prefix);
        }

        return $path === $pattern;
    }
}
