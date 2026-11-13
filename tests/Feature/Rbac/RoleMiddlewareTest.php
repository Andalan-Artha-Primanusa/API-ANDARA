<?php

namespace Tests\Feature\Rbac;

use App\Modules\User\Models\User;
use App\Modules\Administration\Models\Role;
use App\Modules\Administration\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('config:clear');

        $this->seedRolesAndPermissions();
    }

    private function seedRolesAndPermissions(): void
    {
        Role::create(['name' => 'super_admin']);

        foreach (config('rbac.roles', []) as $roleName => $perms) {
            if ($roleName === 'super_admin') continue;
            $role = Role::firstOrCreate(['name' => $roleName]);
            if (is_array($perms)) {
                foreach ($perms as $permName) {
                    $perm = Permission::firstOrCreate(['name' => $permName]);
                    $role->permissions()->syncWithoutDetaching([$perm->id]);
                }
            }
        }
    }

    public function test_super_admin_can_access_role_protected_route(): void
    {
        $user = User::factory()->create();
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $user->roles()->sync([$superAdminRole->id]);

        $this->actingAs($user);

        $response = $this->getJson('/api/approval-flows');

        $response->assertStatus(200);
    }

    public function test_user_without_permission_cannot_access_role_protected_route(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/approval-flows');

        $response->assertStatus(403);
    }

    public function test_user_with_correct_permission_can_access_route(): void
    {
        $user = User::factory()->create();
        $customRole = Role::create(['name' => 'approval_manager']);

        $flowManagePerm = Permission::firstOrCreate(['name' => 'admin.approval_flow.manage']);
        $customRole->permissions()->sync([$flowManagePerm->id]);
        $user->roles()->sync([$customRole->id]);

        $this->actingAs($user);

        $response = $this->getJson('/api/approval-flows');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson('/api/approval-flows');
        $response->assertStatus(401);
    }

    public function test_super_admin_can_access_all_routes(): void
    {
        $user = User::factory()->create();
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $user->roles()->sync([$superAdminRole->id]);

        $this->actingAs($user);

        $routes = [
            '/api/employees',
            '/api/leaves',
            '/api/payroll',
            '/api/reimbursements',
            '/api/approval-flows',
            '/api/admin/roles',
            '/api/admin/permissions',
        ];

        foreach ($routes as $route) {
            $response = $this->getJson($route);
            $this->assertTrue(
                $response->status() !== 401 && $response->status() !== 403,
                "Super admin was denied access to {$route} (status: {$response->status()})"
            );
        }
    }
}

