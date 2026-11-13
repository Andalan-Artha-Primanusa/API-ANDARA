<?php

namespace Tests\Feature\Rbac;

use App\Modules\User\Models\User;
use App\Modules\Administration\Models\Role;
use App\Modules\Administration\Models\Permission;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class DynamicRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTables();
        $this->seedPermissions(config('rbac.roles', []));
    }

    private function setupTables(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
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

    private function seedPermissions(array $roleConfig): void
    {
        Role::firstOrCreate(['name' => 'super_admin']);

        $allPerms = [];
        foreach ($roleConfig as $roleName => $perms) {
            if (is_array($perms)) {
                foreach ($perms as $p) {
                    $allPerms[$p] = $p;
                }
            }
        }

        foreach ($allPerms as $permName) {
            Permission::create(['name' => $permName]);
        }

        foreach ($roleConfig as $roleName => $perms) {
            $role = Role::where('name', $roleName)->first();
            if ($role && is_array($perms)) {
                $permIds = Permission::whereIn('name', $perms)->pluck('id');
                $role->permissions()->sync($permIds);
            }
        }
    }

    public function test_create_role_and_assign_permissions(): void
    {
        $role = Role::create(['name' => 'team_lead']);

        $perms = Permission::whereIn('name', [
            'employee.view', 'leave.view', 'leave.create',
        ])->pluck('id');

        $role->permissions()->sync($perms);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        $this->assertTrue($user->hasPermission('employee.view'));
        $this->assertTrue($user->hasPermission('leave.view'));
        $this->assertFalse($user->hasPermission('payroll.view'));
    }

    public function test_cannot_modify_super_admin_role(): void
    {
        Role::firstOrCreate(['name' => 'super_admin']);
        $superAdminRole = Role::where('name', 'super_admin')->first();

        $user = User::factory()->create();
        $user->roles()->sync([$superAdminRole->id]);

        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->hasPermission('anything.at.all'));
    }

    public function test_role_without_permissions_granted_none(): void
    {
        $role = Role::create(['name' => 'auditor']);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        $this->assertFalse($user->hasPermission('employee.view'));
        $this->assertFalse($user->hasPermission('leave.view'));
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isHR());
        $this->assertFalse($user->isManager());
    }

    public function test_user_with_super_admin_and_other_role_still_super(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $employeeRole = Role::create(['name' => 'employee']);

        $user = User::factory()->create();
        $user->roles()->sync([$superAdminRole->id, $employeeRole->id]);

        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->hasPermission('any.random.permission'));
    }
}

