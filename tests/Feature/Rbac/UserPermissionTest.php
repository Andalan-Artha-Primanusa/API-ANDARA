<?php

namespace Tests\Feature\Rbac;

use App\Modules\User\Models\User;
use App\Modules\Administration\Models\Role;
use App\Modules\Administration\Models\Permission;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTables();
        $this->seedRolesAndPermissions();
    }

    private function setupTables(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    private function seedRolesAndPermissions(): void
    {
        foreach (config('rbac.roles') as $roleName => $perms) {
            Role::create(['name' => $roleName]);
        }

        $allPerms = [];
        foreach (config('rbac.roles') as $roleName => $perms) {
            foreach ((array)$perms as $p) {
                $allPerms[$p] = $p;
            }
        }

        foreach ($allPerms as $permName) {
            Permission::create(['name' => $permName]);
        }

        foreach (config('rbac.roles') as $roleName => $perms) {
            $role = Role::where('name', $roleName)->first();
            if ($role && is_array($perms)) {
                $permIds = Permission::whereIn('name', $perms)->pluck('id')->toArray();
                $role->permissions()->sync($permIds);
            }
        }
    }

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->first()->id);
        $user->unsetRelation('roles');
        $user->load('roles.permissions');
        return $user;
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $user = $this->makeUserWithRole('super_admin');

        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->hasPermission('employee.view'));
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isHR());
        $this->assertTrue($user->isManager());
        $this->assertTrue($user->isEmployee());
    }

    public function test_config_level_indicators_are_loaded(): void
    {
        $admin = config('rbac.level_indicators.admin');
        $hr = config('rbac.level_indicators.hr');
        $manager = config('rbac.level_indicators.manager');
        $employee = config('rbac.level_indicators.employee');

        $this->assertNotNull($admin, 'admin level_indicators is null');
        $this->assertNotNull($hr, 'hr level_indicators is null');
        $this->assertNotNull($manager, 'manager level_indicators is null');
        $this->assertNotNull($employee, 'employee level_indicators is null');
        $this->assertNotEmpty($admin, 'admin level_indicators is empty');
    }

    public function test_admin_role_has_all_levels_except_superadmin(): void
    {
        $user = $this->makeUserWithRole('admin');

        $this->assertFalse($user->isSuperAdmin());
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isHR());
        $this->assertTrue($user->isManager());
        $this->assertTrue($user->isEmployee());
    }

    public function test_hr_role_has_hr_and_below_levels(): void
    {
        $user = $this->makeUserWithRole('hr');

        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isHR());
        $this->assertTrue($user->isManager());
        $this->assertTrue($user->isEmployee());
    }

    public function test_manager_role_has_manager_and_employee_levels(): void
    {
        $user = $this->makeUserWithRole('manager');

        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isHR());
        $this->assertTrue($user->isManager());
        $this->assertTrue($user->isEmployee());
    }

    public function test_user_with_no_role_has_no_levels(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isHR());
        $this->assertFalse($user->isManager());
        $this->assertFalse($user->isEmployee());
        $this->assertFalse($user->hasPermission('employee.view'));
    }

    public function test_employee_role_has_employee_level_only(): void
    {
        $user = $this->makeUserWithRole('employee');

        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isHR());
        $this->assertFalse($user->isManager());
        $this->assertTrue($user->isEmployee());
    }

    public function test_dynamic_role_via_permissions(): void
    {
        $customRole = Role::create(['name' => 'custom_supervisor']);
        $customRole->permissions()->sync(
            Permission::whereIn('name', ['leave.approve', 'employee.view'])->pluck('id')
        );

        $user = User::factory()->create();
        $user->roles()->attach($customRole->id);
        $user->unsetRelation('roles');
        $user->load('roles.permissions');

        $this->assertTrue($user->hasPermission('leave.approve'));
        $this->assertTrue($user->hasPermission('employee.view'));
        $this->assertFalse($user->hasPermission('payroll.view'));
        $this->assertTrue($user->isManager());
        $this->assertFalse($user->isHR());
        $this->assertFalse($user->isAdmin());
    }

    public function test_user_with_no_role_has_no_permissions(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->hasPermission('employee.view'));
        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isHR());
        $this->assertFalse($user->isManager());
        $this->assertFalse($user->isEmployee());
    }
}

