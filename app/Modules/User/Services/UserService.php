<?php

namespace App\Modules\User\Services;

use App\Modules\Administration\Models\Role;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Modules\User\Models\User;
use Illuminate\Support\Str;
use App\Modules\Employee\Models\Employee;

class UserService
{
    public function __construct(
        protected UserRepositoryInterface $userRepo
    ) {}

    public function register(array $data): User
    {
        $user = $this->userRepo->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Assign default role via RBAC pivot table (configurable via rbac.php)
        $defaultRoleName = config('rbac.default_role', 'employee');
        $defaultRole = Role::where('name', $defaultRoleName)->first();
        if ($defaultRole) {
            $user->roles()->syncWithoutDetaching([$defaultRole->id]);
        }

        return $user->load([
            'roles.permissions',
            'profile',
            'employee.manager.profile',
        ]);
    }

    public function login(array $data): User
    {
        $user = $this->userRepo->findByEmail($data['email']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password'],
            ]);
        }

        return $user->load([
            'roles.permissions',
            'profile',
            'employee.manager.profile',
        ]);
    }

    public function findOrCreateFromGoogle($googleUser): User
{
    $user = $this->userRepo->findByEmail($googleUser->getEmail());

    if (!$user) {
        $user = $this->userRepo->create([
            'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? 'User',
            'email' => $googleUser->getEmail(),
            'password' => Hash::make(Str::random(32)),
        ]);

        // assign default role
        $defaultRoleName = config('rbac.default_role', 'employee');
        $defaultRole = Role::where('name', $defaultRoleName)->first();
        if ($defaultRole) {
            $user->roles()->syncWithoutDetaching([$defaultRole->id]);
        }
    }

    // ðŸ”¥ auto create employee (IMPORTANT)
    if (!$user->employee()->exists()) {
        Employee::create([
            'user_id' => $user->id,
            'employee_code' => 'EMP-' . str_pad((string)$user->id, 4, '0', STR_PAD_LEFT),
            'position' => 'Staff',
            'department' => 'General',
            'hire_date' => now(),
            'salary' => 0,
        ]);
    }

    return $user;
}
}

