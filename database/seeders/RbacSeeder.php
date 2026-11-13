<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Administration\Models\Role;
use App\Modules\Administration\Models\Permission;
use App\Constants\Permissions as PermissionRegistry;

class RbacSeeder extends Seeder
{
    /**
     * Seed the RBAC system with roles and permissions
     * 
     * All permissions are defined in App\Constants\Permissions
     * Role-permission mappings can be customized at runtime via:
     * 
     * POST /admin/roles/{id}/assign-permission
     * {
     *     "permissions": [array of permission IDs]
     * }
     */
    public function run(): void
    {
        echo "\n🔐 Seeding RBAC System...\n";

        // ======================
        // 1. CREATE ROLES
        // ======================
        echo "  ✓ Creating roles...\n";

        $roleConfigs = config('rbac.roles', []);
        $roleNames = array_keys($roleConfigs);

        foreach ($roleNames as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // ======================
        // 2. CREATE PERMISSIONS FROM REGISTRY
        // ======================
        echo "  ✓ Creating permissions from registry...\n";
        
        $allPermissionsData = PermissionRegistry::all();
        $createdPermissions = [];

        foreach ($allPermissionsData as $permissionName => $permissionDescription) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['description' => $permissionDescription]
            );
            $createdPermissions[$permissionName] = $permission->id;
        }

        // ======================
        // 3. ASSIGN PERMISSIONS TO ROLES
        // ======================
        echo "  ✓ Assigning permissions to roles...\n";

        $rolePermissionMappings = PermissionRegistry::roleDefaultPermissions();

        foreach ($rolePermissionMappings as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();
            
            if (!$role) {
                $this->command->warn("    ⚠ Role '{$roleName}' not found, skipping...");
                continue;
            }

            // Convert permission names to IDs
            $permissionIds = array_map(
                fn($permName) => $createdPermissions[$permName] ?? null,
                $permissionNames
            );
            
            // Remove null values
            $permissionIds = array_filter($permissionIds);

            // Sync permissions
            $role->permissions()->sync($permissionIds);

            $count = count($permissionIds);
            $this->command->info("    → {$roleName}: {$count} permissions assigned");
        }

        echo "\n✅ RBAC System seeded successfully!\n";
        echo "📝 Customize permissions via: POST /admin/roles/{roleId}/assign-permission\n\n";
    }
}


