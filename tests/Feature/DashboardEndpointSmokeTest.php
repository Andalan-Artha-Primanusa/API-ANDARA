<?php

namespace Tests\Feature;

use App\Modules\Administration\Models\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEndpointSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_endpoints_do_not_return_500_for_super_admin(): void
    {
        $role = Role::firstOrCreate(['name' => User::ROLE_SUPER_ADMIN]);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        $endpoints = [
            '/api/attendance/today',
            '/api/attendance/all',
            '/api/reimbursements/pending',
            '/api/leaves',
            '/api/employees',
            '/api/payroll',
            '/api/leaves/pending',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->actingAs($user)->getJson($endpoint);

            $this->assertNotSame(
                500,
                $response->status(),
                "{$endpoint} returned 500: {$response->getContent()}"
            );
        }
    }
}
