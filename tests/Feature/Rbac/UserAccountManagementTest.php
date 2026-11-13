<?php

namespace Tests\Feature\Rbac;

use App\Modules\Administration\Models\Permission;
use App\Modules\Administration\Models\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(['name' => $permissionName]);
        $role = Role::firstOrCreate(['name' => 'account_admin']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user->fresh('roles.permissions');
    }

    public function test_admin_can_create_user_with_generated_password_and_force_reset_flag(): void
    {
        $employeeRole = Role::firstOrCreate(['name' => 'employee']);

        $response = $this->actingAs($this->userWithPermission('user.create'))->postJson('/api/admin/users', [
            'name' => 'New Guard',
            'email' => 'guard@example.test',
            'generate_password' => true,
            'must_change_password' => true,
            'role_ids' => [$employeeRole->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'guard@example.test')
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonStructure(['data' => ['temporary_password']]);

        $createdUser = User::where('email', 'guard@example.test')->firstOrFail();

        $this->assertTrue($createdUser->must_change_password);
        $this->assertTrue(Hash::check($response->json('data.temporary_password'), $createdUser->password));
        $this->assertTrue($createdUser->roles()->where('roles.id', $employeeRole->id)->exists());
    }

    public function test_user_must_send_current_password_to_clear_force_reset_flag(): void
    {
        $temporaryPassword = 'TempPassword123!';
        $user = User::factory()->create([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/change-password', [
            'current_password' => $temporaryPassword,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertOk();

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertCount(0, $user->tokens);
    }

    public function test_user_create_requires_permission(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson('/api/admin/users', [
            'name' => 'Blocked User',
            'email' => 'blocked@example.test',
            'generate_password' => true,
        ]);

        $response->assertForbidden();
    }
}
