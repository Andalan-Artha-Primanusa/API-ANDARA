<?php

namespace Tests\Feature\Rbac;

use App\Modules\Administration\Models\Permission;
use App\Modules\Administration\Models\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BusinessProcessAccessTest extends TestCase
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
        Role::firstOrCreate(['name' => 'super_admin']);

        foreach (config('rbac.roles', []) as $roleName => $permissions) {
            if ($roleName === 'super_admin') {
                continue;
            }

            $role = Role::firstOrCreate(['name' => $roleName]);

            if (!is_array($permissions)) {
                continue;
            }

            foreach ($permissions as $permissionName) {
                $permission = Permission::firstOrCreate(['name' => $permissionName]);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::where('name', $roleName)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user->fresh('roles.permissions');
    }

    #[DataProvider('allowedRouteProvider')]
    public function test_allowed_roles_can_reach_business_process_routes(string $role, string $method, string $uri): void
    {
        $response = $this->actingAs($this->userWithRole($role))->json($method, $uri);

        $this->assertNotContains(
            $response->status(),
            [401, 403, 500],
            "{$role} should reach {$method} {$uri}, got {$response->status()}: {$response->getContent()}"
        );
    }

    public static function allowedRouteProvider(): array
    {
        return [
            'admin employees index' => ['admin', 'GET', '/api/employees'],
            'hr employees index' => ['hr', 'GET', '/api/employees'],
            'ho companies index' => ['ho', 'GET', '/api/companies'],
            'admin companies index' => ['admin', 'GET', '/api/companies'],
            'hr payroll index' => ['hr', 'GET', '/api/payroll'],
            'ho payroll index' => ['ho', 'GET', '/api/payroll'],
            'hr payroll generate validation' => ['hr', 'POST', '/api/payroll/generate/monthly'],
            'hr payroll create validation' => ['hr', 'POST', '/api/payroll'],
            'hr payroll detail create validation' => ['hr', 'POST', '/api/payroll-details'],
            'hr payroll detail read not found' => ['hr', 'GET', '/api/payroll-details/999999'],
            'hr reimbursement index' => ['hr', 'GET', '/api/reimbursements'],
            'hr leave pending' => ['hr', 'GET', '/api/leaves/pending'],
            'employee dashboard widgets' => ['employee', 'GET', '/api/dashboard/widgets'],
            'employee dashboard config validation' => ['employee', 'POST', '/api/dashboard/configs'],
            'hr dashboard widgets' => ['hr', 'GET', '/api/dashboard/widgets'],
            'hr attendance qr generate validation' => ['hr', 'POST', '/api/attendance/qr/generate'],
            'employee attendance qr check-in validation' => ['employee', 'POST', '/api/attendance/qr/check-in'],
            'employee attendance qr check-out validation' => ['employee', 'POST', '/api/attendance/qr/check-out'],
            'employee patrol scan validation' => ['employee', 'POST', '/api/patrol/scan'],
            'hr patrol checkpoints' => ['hr', 'GET', '/api/patrol/checkpoints'],
            'hr patrol scans' => ['hr', 'GET', '/api/patrol/scans'],
        ];
    }

    #[DataProvider('forbiddenRouteProvider')]
    public function test_roles_without_permission_are_blocked_from_restricted_routes(string $role, string $method, string $uri): void
    {
        $response = $this->actingAs($this->userWithRole($role))->json($method, $uri);

        $response->assertForbidden();
    }

    public static function forbiddenRouteProvider(): array
    {
        return [
            'employee cannot list companies' => ['employee', 'GET', '/api/companies'],
            'employee cannot list admin payroll' => ['employee', 'GET', '/api/payroll'],
            'employee cannot generate payroll' => ['employee', 'POST', '/api/payroll/generate/monthly'],
            'employee cannot create payroll detail' => ['employee', 'POST', '/api/payroll-details'],
            'employee cannot generate attendance QR' => ['employee', 'POST', '/api/attendance/qr/generate'],
            'employee cannot manage patrol checkpoint' => ['employee', 'POST', '/api/patrol/checkpoints'],
            'employee cannot monitor patrol scans' => ['employee', 'GET', '/api/patrol/scans'],
            'ho cannot generate payroll' => ['ho', 'POST', '/api/payroll/generate/monthly'],
            'ho cannot pay payroll' => ['ho', 'POST', '/api/payroll/999999/pay'],
        ];
    }
}
