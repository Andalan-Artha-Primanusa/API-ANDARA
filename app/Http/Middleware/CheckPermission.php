<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     * Checks if the authenticated user has the required permission
     * through their assigned roles.
     *
     * Usage in routes: ->middleware('permission:employee.view')
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('Unauthenticated', null, 401);
        }

        // Eager-load roles and permissions for efficient checking
        $user->loadMissing('roles.permissions');

        if (!$user->hasPermission($permission)) {
            return ApiResponse::error('Forbidden', 'No permission: ' . $permission, 403);
        }

        return $next($request);
    }
}
