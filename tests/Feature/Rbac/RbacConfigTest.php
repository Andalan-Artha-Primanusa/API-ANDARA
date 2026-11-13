<?php

namespace Tests\Feature\Rbac;

use Tests\TestCase;

class RbacConfigTest extends TestCase
{
    public function test_config_rbac_roles_contains_expected_structure(): void
    {
        $roles = config('rbac.roles');

        $this->assertIsArray($roles);
        $this->assertArrayHasKey('super_admin', $roles);
        $this->assertEquals('*', $roles['super_admin']);
        $this->assertArrayHasKey('admin', $roles);
        $this->assertArrayHasKey('hr', $roles);
        $this->assertArrayHasKey('manager', $roles);
        $this->assertArrayHasKey('employee', $roles);

        foreach (['admin', 'hr', 'manager', 'employee'] as $role) {
            $this->assertIsArray($roles[$role]);
            $this->assertNotEmpty($roles[$role]);
        }
    }

    public function test_config_rbac_level_indicators_contains_expected_keys(): void
    {
        $indicators = config('rbac.level_indicators');

        $this->assertIsArray($indicators);
        $this->assertArrayHasKey('admin', $indicators);
        $this->assertArrayHasKey('hr', $indicators);
        $this->assertArrayHasKey('manager', $indicators);
        $this->assertArrayHasKey('employee', $indicators);

        foreach (['admin', 'hr', 'manager', 'employee'] as $level) {
            $this->assertIsArray($indicators[$level]);
            $this->assertNotEmpty($indicators[$level]);
        }
    }

    public function test_config_rbac_default_role_exists(): void
    {
        $defaultRole = config('rbac.default_role');
        $this->assertNotNull($defaultRole);
        $this->assertEquals('employee', $defaultRole);

        $roles = config('rbac.roles');
        $this->assertArrayHasKey($defaultRole, $roles);
    }

    public function test_config_rbac_super_admin_has_star_permissions(): void
    {
        $this->assertEquals('*', config('rbac.roles.super_admin'));
    }

    public function test_permission_descriptions_are_defined(): void
    {
        $permissionClass = new \App\Constants\Permissions();
        $allPermissions = $permissionClass->all();

        $this->assertIsArray($allPermissions);
        $this->assertNotEmpty($allPermissions);
        $this->assertArrayHasKey('employee.view', $allPermissions);
        $this->assertArrayHasKey('leave.view', $allPermissions);
        $this->assertArrayHasKey('role.view', $allPermissions);

        foreach ($allPermissions as $key => $desc) {
            $this->assertIsString($key);
            $this->assertIsString($desc);
        }
    }

    public function test_role_default_permissions_from_config(): void
    {
        $defaults = \App\Constants\Permissions::roleDefaultPermissions();

        $this->assertArrayHasKey('super_admin', $defaults);
        $this->assertArrayHasKey('admin', $defaults);
        $this->assertArrayHasKey('hr', $defaults);
        $this->assertArrayHasKey('manager', $defaults);
        $this->assertArrayHasKey('employee', $defaults);

        $this->assertIsArray($defaults['super_admin']);
        $this->assertNotEmpty($defaults['super_admin']);

        $this->assertContains('employee.view', $defaults['admin']);
        $this->assertContains('leave.view', $defaults['admin']);
    }
}
