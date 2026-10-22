<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles the RouteMiddleware contract to keep it simple: when a role is
 * missing a permission that is missing on purpose, refuse, but do so gently
 * instead of throwing a raw 403 — a 'user' who lacks apply_loan is bounced to
 * the membership page with an actionable message, not an error wall.
 */
class RoleMiddleware
{
    protected array $friendlyDenial = [
        // permission => redirect target name (for authenticated non-member users)
        Permissions::APPLY_LOAN => 'user.membership',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->check()) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();

        $permission = $this->extractPermission($request->route());

        if ($permission === null) {
            // Group-level guard: refuse unless some route declared a permission.
            if (! $this->routeDeclaresPermission($request)) {
                abort(403, 'Unauthorized action.');
            }

            return $next($request);
        }

        if (! Permissions::has($user, $permission)) {
            $friendly = $this->friendlyDenialFor($permission);

            if ($friendly !== null && Permissions::has($user, Permissions::OWN_PORTAL)) {
                return redirect()->route($friendly)
                    ->with('error', $this->friendlyMessage($permission));
            }

            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }

    /**
     * Pull the permission string off the matched route.
     *
     * Accepts both the raw alias `role:apply_loan` and the resolved form
     * `RoleMiddleware:apply_loan`, for robustness against refactors.
     */
    protected function extractPermission(?\Illuminate\Routing\Route $route): ?string
    {
        if ($route === null) {
            return null;
        }

        foreach ($route->getAction()['middleware'] ?? [] as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if (str_starts_with($middleware, 'role:')) {
                return substr($middleware, 5);
            }

            if (str_starts_with($middleware, self::class . ':')) {
                return strtok(substr($middleware, strlen(self::class . ':')), ':');
            }
        }

        return null;
    }

    /**
     * Mirror the old route-declared-permission check used by the group mode.
     */
    protected function routeDeclaresPermission(Request $request): bool
    {
        $route = $request->route();

        if ($route === null) {
            return false;
        }

        foreach (data_get($route->getAction(), 'middleware', []) as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if (str_starts_with($middleware, 'role:')) {
                return true;
            }

            if (str_starts_with($middleware, self::class . ':')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Friendly messages for the intentionally denied permissions, shown to
     * registered non-members so they know what to do next.
     */
    private function friendlyMessage(string $permission): string
    {
        return match ($permission) {
            Permissions::APPLY_LOAN => 'You need to become an active cooperative member before you can apply for a loan. Click “Become a member” to start your membership application.',
            default => 'This feature is restricted to staff accounts.',
        };
    }

    public function friendlyDenialFor(string $permission): ?string
    {
        return $this->friendlyDenial[$permission] ?? null;
    }
}
