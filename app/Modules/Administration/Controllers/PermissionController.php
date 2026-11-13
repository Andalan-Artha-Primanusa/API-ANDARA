<?php

namespace App\Modules\Administration\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Modules\Administration\Models\Permission;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('permission.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $permissions = Permission::paginate($request->integer('per_page', 50))
            ->withQueryString();

        return ApiResponse::success('Permission list', $permissions);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('permission.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $permission = Permission::findOrFail($id);

        return ApiResponse::success('Permission detail', $permission);
    }
}

