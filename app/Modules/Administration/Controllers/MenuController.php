<?php

namespace App\Modules\Administration\Controllers;

use App\Helpers\ApiResponse;
use App\Models\MenuPermission;
use App\Modules\Administration\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MenuController
{
    public function definitions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('role.assign_permission') && !$user->hasPermission('role.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $roles = Role::where('name', '!=', 'super_admin')->get();
        $assignments = MenuPermission::all()->groupBy('menu_key')->map->pluck('role_id')->toArray();
        $requirements = $this->menuRequirements();

        $items = $this->menuDefinitions()
            ->map(function (array $def) use ($assignments, $requirements) {
                $def['assigned_role_ids'] = $assignments[$def['key']] ?? [];
                $def['required_permissions'] = $requirements[$def['key']] ?? [];
                return $def;
            })
            ->all();

        return ApiResponse::success('Menu definitions', [
            'items' => $items,
            'roles' => $roles,
        ]);
    }

    public function assignRole(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('role.assign_permission')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $data = $request->validate([
            'menu_key' => 'required|string|exists:menus,key',
            'role_id' => 'required|exists:roles,id',
        ]);

        MenuPermission::firstOrCreate([
            'menu_key' => $data['menu_key'],
            'role_id' => $data['role_id'],
        ]);

        return ApiResponse::success('Role assigned to menu');
    }

    public function removeRole(Request $request, string $menuKey, int $roleId): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('role.assign_permission')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        MenuPermission::where('menu_key', $menuKey)
            ->where('role_id', $roleId)
            ->delete();

        return ApiResponse::success('Role removed from menu');
    }

    public function userMenus(Request $request): JsonResponse
    {
        return ApiResponse::success('Allowed menus', $this->resolveUserMenuKeys($request->user()));
    }

    public function userMenuTree(Request $request): JsonResponse
    {
        $definitions = $this->menuDefinitions();
        $allowedKeys = collect($this->resolveUserMenuKeys($request->user()));
        $visibleKeys = $this->withAncestorKeys($definitions, $allowedKeys);
        $items = $definitions
            ->filter(fn (array $item) => $visibleKeys->contains($item['key']))
            ->values();

        return ApiResponse::success('Allowed menu tree', $this->buildTree($items));
    }

    private function resolveUserMenuKeys($user): array
    {
        $definitions = $this->menuDefinitions();

        if ($user->isSuperAdmin()) {
            return $definitions->pluck('key')->unique()->values()->all();
        }

        $user->loadMissing('roles');

        if (!$user->roles->count()) {
            return [];
        }

        $userRoleIds = $user->roles->pluck('id');

        return MenuPermission::whereIn('role_id', $userRoleIds)
            ->pluck('menu_key')
            ->unique()
            ->values()
            ->filter(fn (string $key) => $definitions->contains('key', $key))
            ->filter(fn (string $key) => $this->canAccessMenuKey($user, $key))
            ->values()
            ->all();
    }

    private function canAccessMenuKey($user, string $key): bool
    {
        $requiredPermissions = $this->menuRequirements()[$key] ?? [];

        if (empty($requiredPermissions)) {
            return true;
        }

        return $user->hasAnyPermission($requiredPermissions);
    }

    private function menuDefinitions(): Collection
    {
        return DB::table('menus')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'key' => $row->key,
                'label' => $row->label,
                'path' => $row->path,
                'icon' => $row->icon,
                'parent_key' => $row->parent_key,
                'sort_order' => (int) $row->sort_order,
            ]);
    }

    private function menuRequirements(): array
    {
        return DB::table('menu_required_permissions')
            ->select('menu_key', 'permission_name')
            ->get()
            ->groupBy('menu_key')
            ->map(fn (Collection $items) => $items->pluck('permission_name')->values()->all())
            ->all();
    }

    private function withAncestorKeys(Collection $definitions, Collection $allowedKeys): Collection
    {
        $byKey = $definitions->keyBy('key');
        $visibleKeys = $allowedKeys->values();

        foreach ($allowedKeys as $key) {
            $current = $byKey[$key] ?? null;
            while ($current && !empty($current['parent_key'])) {
                $visibleKeys->push($current['parent_key']);
                $current = $byKey[$current['parent_key']] ?? null;
            }
        }

        return $visibleKeys->unique()->values();
    }

    private function buildTree(Collection $items, ?string $parentKey = null): array
    {
        return $items
            ->filter(fn (array $item) => $item['parent_key'] === $parentKey)
            ->map(function (array $item) use ($items) {
                $children = $this->buildTree($items, $item['key']);
                unset($item['parent_key'], $item['sort_order']);

                if (!empty($children)) {
                    $item['children'] = $children;
                }

                return $item;
            })
            ->values()
            ->all();
    }
}
