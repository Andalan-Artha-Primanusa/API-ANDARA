<?php

namespace App\Modules\Administration\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Modules\Administration\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\Administration\Models\Permission;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('role.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $roles = Role::withCount('permissions')
            ->where('name', '!=', User::ROLE_SUPER_ADMIN)
            ->paginate($request->integer('per_page', 50))
            ->withQueryString();

        return ApiResponse::success('Role list', $roles);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('role.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $role = Role::with('permissions')->findOrFail($id);

        // Prevent non-super-admin from viewing super_admin role
        if ($role->name === User::ROLE_SUPER_ADMIN && !$user->isSuperAdmin()) {
            return ApiResponse::error('Forbidden', 'Cannot view super_admin role', 403);
        }

        return ApiResponse::success('Role detail', $role);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('role.create')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Prevent creating a role named super_admin
        if (strtolower($data['name']) === User::ROLE_SUPER_ADMIN) {
            return ApiResponse::error('Cannot create a role with this name', null, 422);
        }

        $role = Role::create($data);

        return ApiResponse::success('Role created successfully', $role->load('permissions'), 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('role.update')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $role = Role::findOrFail($id);

        // Protect super_admin role
        if ($role->name === User::ROLE_SUPER_ADMIN) {
            return ApiResponse::error('Cannot modify super_admin role', null, 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,' . $id],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Prevent renaming to super_admin
        if (strtolower($data['name']) === User::ROLE_SUPER_ADMIN) {
            return ApiResponse::error('Cannot rename role to super_admin', null, 422);
        }

        $role->update($data);

        return ApiResponse::success('Role updated successfully', $role->fresh()->load('permissions'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('role.delete')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $role = Role::findOrFail($id);

        // Protect super_admin role
        if ($role->name === User::ROLE_SUPER_ADMIN) {
            return ApiResponse::error('Cannot delete super_admin role', null, 403);
        }

        // Check if any users are assigned this role
        if ($role->users()->count() > 0) {
            return ApiResponse::error('Cannot delete role that is assigned to users', null, 409);
        }

        $role->permissions()->detach();
        $role->delete();

        return ApiResponse::success('Role deleted successfully');
    }

    public function assignPermission(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('role.assign_permission')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $role = Role::findOrFail($id);

        if ($role->name === User::ROLE_SUPER_ADMIN && !$user->isSuperAdmin()) {
            return ApiResponse::error('Forbidden', 'Cannot modify super_admin role permissions', 403);
        }

        $data = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['exists:permissions,id']
        ]);

        if (!$user->isSuperAdmin()) {
            $sensitivePerms = Permission::whereIn('name', [
                'role.assign_permission',
                'user.assign_role'
            ])->pluck('id')->toArray();

            $data['permission_ids'] = array_values(array_diff($data['permission_ids'], $sensitivePerms));
        }

        $role->permissions()->sync($data['permission_ids']);

        return ApiResponse::success(
            'Permissions assigned successfully',
            $role->load('permissions')
        );
    }

    public function removePermission(Request $request, $id, $permissionId): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('role.assign_permission')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $role = Role::findOrFail($id);

        if ($role->name === User::ROLE_SUPER_ADMIN && !$user->isSuperAdmin()) {
            return ApiResponse::error('Forbidden', 'Cannot modify super_admin role permissions', 403);
        }

        $permission = Permission::findOrFail($permissionId);

        $role->permissions()->detach($permission->id);

        return ApiResponse::success(
            'Permission removed successfully',
            $role->load('permissions')
        );
    }

    public function canModify(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $role = Role::findOrFail($id);

        $canModify = $user->isSuperAdmin() ||
            ($user->hasPermission('role.update') && $role->name !== User::ROLE_SUPER_ADMIN);

        return ApiResponse::success('OK', ['can_modify' => $canModify]);
    }

    public function canAssign(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $role = Role::findOrFail($id);

        $canAssign = $user->isSuperAdmin() ||
            ($user->hasPermission('user.assign_role') && $role->name !== User::ROLE_SUPER_ADMIN);

        return ApiResponse::success('OK', ['can_assign' => $canAssign]);
    }
}

