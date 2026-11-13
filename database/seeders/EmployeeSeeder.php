<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\User\Models\User;
use App\Modules\Employee\Models\Employee;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $managers = User::whereHas('roles', function ($q) {
            $q->where('name', 'manager');
        })->get();

        $allUsers = User::all();

        // Get existing employee codes to avoid collision
        $existingCodes = Employee::pluck('employee_code')->toArray();

        $counter = 1;

        foreach ($allUsers as $user) {
            // Skip if employee record already exists (idempotent)
            if (Employee::where('user_id', $user->id)->exists()) {
                continue;
            }

            // Generate unique employee code
            do {
                $code = 'EMP-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
                $counter++;
            } while (in_array($code, $existingCodes));

            $existingCodes[] = $code;

            // Assign a random manager if managers exist, null otherwise
            $managerId = $managers->isNotEmpty() ? $managers->random()->id : null;

            Employee::create([
                'user_id'       => $user->id,
                'manager_id'    => $managerId,
                'employee_code' => $code,
                'position'      => fake()->jobTitle(),
                'department'    => fake()->randomElement(['IT', 'HR', 'Finance', 'Marketing']),
                'hire_date'     => now()->subYears(rand(1, 5)),
                'salary'        => rand(4000000, 15000000),
            ]);
        }

        $this->command?->info('Employee records seeded successfully.');
    }
}


