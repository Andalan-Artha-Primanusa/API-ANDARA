<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Checks roles via the pivot table (user_roles), NOT the deprecated users.role column.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('Unauthenticated', null, 401);
        }

        $user->loadMissing('roles');
        $path = '/' . ltrim($request->path(), '/');

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        foreach ($this->routePermissions() as $routePrefix => $requiredPermissions) {
            if (str_starts_with($path, $routePrefix)) {
                $user->loadMissing('roles.permissions');

                foreach ($requiredPermissions as $permission) {
                    if ($user->hasPermission($permission)) {
                        return $next($request);
                    }
                }

                return ApiResponse::error('Forbidden', 'Missing explicit permission: ' . implode(' / ', $requiredPermissions), 403);
            }
        }

        if (in_array('*', $roles, true)) {
            return ApiResponse::error('Forbidden', 'No permission registry for this route', 403);
        }

        if ($user->hasAnyRole($roles)) {
            return $next($request);
        }

        return ApiResponse::error('Forbidden', 'Insufficient role or explicit capability', 403);
    }

    private function routePermissions(): array
    {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        try {
            $map = DB::table('route_permissions')
                ->where('is_active', true)
                ->orderByRaw('CHAR_LENGTH(route_prefix) DESC')
                ->get(['route_prefix', 'permission_name'])
                ->groupBy('route_prefix')
                ->map(fn ($items) => $items->pluck('permission_name')->values()->all())
                ->all();
        } catch (Throwable) {
            $map = [];
        }

        return $map;
    }
}
