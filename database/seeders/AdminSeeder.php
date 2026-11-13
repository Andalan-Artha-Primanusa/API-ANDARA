<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\User\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Administration\Models\Role;
use App\Modules\Attendance\Models\WorkSchedule;
use App\Modules\Organization\Models\Location;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminSeeder extends Seeder
{
    /**
     * Default seed accounts configuration
     */
    private array $accounts = [
        [
            'email'         => 'superadmin@gmail.com',
            'name'          => 'Super Admin',
            'role'          => 'super_admin',
            'employee_code' => 'ADM-001',
            'position'      => 'Super Administrator',
            'department'    => 'Management',
            'salary'        => 20000000,
            'env_password'  => 'SUPER_ADMIN_PASSWORD',
            'default_pass'  => 'SuperAdmin@123',
        ],
        [
            'email'         => 'raul@gmail.com',
            'name'          => 'Raul',
            'role'          => 'super_admin',
            'employee_code' => 'ADM-003',
            'position'      => 'Super Administrator',
            'department'    => 'Management',
            'salary'        => 20000000,
            'env_password'  => 'SUPER_ADMIN_PASSWORD',
            'default_pass'  => 'SuperAdmin@123',
        ],
        [
            'email'         => 'ryas@gmail.com',
            'name'          => 'Ryas',
            'role'          => 'super_admin',
            'employee_code' => 'ADM-004',
            'position'      => 'Super Administrator',
            'department'    => 'Management',
            'salary'        => 20000000,
            'env_password'  => 'SUPER_ADMIN_PASSWORD',
            'default_pass'  => 'SuperAdmin@123',
        ],
        [
            'email'         => 'iqbal@gmail.com',
            'name'          => 'Iqbal',
            'role'          => 'super_admin',
            'employee_code' => 'ADM-005',
            'position'      => 'Super Administrator',
            'department'    => 'Management',
            'salary'        => 20000000,
            'env_password'  => 'SUPER_ADMIN_PASSWORD',
            'default_pass'  => 'SuperAdmin@123',
        ],
        [
            'email'         => 'admin@gmail.com',
            'name'          => 'Admin',
            'role'          => 'admin',
            'employee_code' => 'ADM-002',
            'position'      => 'Administrator',
            'department'    => 'Management',
            'salary'        => 15000000,
            'env_password'  => 'ADMIN_PASSWORD',
            'default_pass'  => 'Admin@123456',
        ],
        [
            'email'         => 'hr@gmail.com',
            'name'          => 'HR',
            'role'          => 'hr',
            'employee_code' => 'HR-001',
            'position'      => 'HR Officer',
            'department'    => 'Human Resources',
            'salary'        => 12000000,
            'env_password'  => 'HR_PASSWORD',
            'default_pass'  => 'HrOfficer@123',
        ],
        [
            'email'         => 'manager@gmail.com',
            'name'          => 'Manager',
            'role'          => 'manager',
            'employee_code' => 'MGR-001',
            'position'      => 'Team Manager',
            'department'    => 'Management',
            'salary'        => 14000000,
            'env_password'  => 'MANAGER_PASSWORD',
            'default_pass'  => 'Manager@1234',
        ],
        [
            'email'         => 'employee@gmail.com',
            'name'          => 'Employee',
            'role'          => 'employee',
            'employee_code' => 'EMP-001',
            'position'      => 'Employee',
            'department'    => 'General',
            'salary'        => 5000000,
            'env_password'  => 'EMPLOYEE_PASSWORD',
            'default_pass'  => 'Employee@1234',
        ],
    ];

    public function run(): void
    {
        // Use transaction for data consistency
        DB::transaction(function () {
            // Cache roles to avoid repeated queries
            $roles = Role::whereIn('name', [
                'super_admin',
                'admin',
                'hr',
                'manager',
                'employee',
            ])->get()->keyBy('name');

            if ($roles->isEmpty()) {
                $this->command?->error('Roles not found. Run RbacSeeder first.');
                return;
            }

            // Get first available work schedule and location
            $workSchedule = WorkSchedule::first();
            $location = Location::first();

            // Process each account
            foreach ($this->accounts as $accountConfig) {
                $this->seedAccount($accountConfig, $roles, $workSchedule, $location);
            }

            if (app()->isLocal()) {
                $this->command?->info('✅ Seed accounts created successfully.');
                $this->command?->info('📝 Configure passwords in .env using: SUPER_ADMIN_PASSWORD, ADMIN_PASSWORD, EMPLOYEE_PASSWORD');
            }
        }, 5); // 5 retry attempts
    }

    /**
     * Seed a single account with its role and employee data
     */
    private function seedAccount(array $config, $roles, $workSchedule, $location): void
    {
        // Get password from env or use default (with validation)
        $password = $this->getPassword(
            $config['env_password'],
            $config['default_pass']
        );

        // Create or UPDATE user (ensures password & role are always current)
        $user = User::updateOrCreate(
            ['email' => $config['email']],
            [
                'name'     => $config['name'],
                'password' => Hash::make($password),
            ]
        );

        // Assign role (sync to avoid duplicates)
        if (isset($roles[$config['role']])) {
            $user->roles()->sync([$roles[$config['role']]->id]);
        }

        // Create employee record if not exists
        Employee::updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_code' => $config['employee_code'],
                'position'      => $config['position'],
                'department'    => $config['department'],
                'hire_date'     => now(),
                'salary'        => $config['salary'],
                'work_schedule_id' => $workSchedule?->id,
                'location_id'   => $location?->id,
            ]
        );
    }

    /**
     * Get password from environment or use secure default
     * 
     * @param string $envKey Environment variable key
     * @param string $default Default password
     * @return string
     */
    private function getPassword(string $envKey, string $default): string
    {
        $envPassword = env($envKey);
        
        // Use env password if set, otherwise use default
        $password = $envPassword ?: $default;

        // Validate only if custom env password is set in production
        if (!app()->isLocal() && $envPassword && strlen($envPassword) < 12) {
            throw new \Exception(
                "Password for {$envKey} must be at least 12 characters in production. Got: " . strlen($envPassword) . " characters."
            );
        }

        return $password;
    }
}





