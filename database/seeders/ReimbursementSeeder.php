<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Reimbursement\Models\Reimbursement;
use App\Modules\Employee\Models\Employee;
use App\Modules\User\Models\User;

class ReimbursementSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::all();

        // Find an admin user via pivot-based RBAC (not deprecated 'role' column)
        $admin = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->first() ?? User::first();

        if ($employees->isEmpty()) {
            $this->command?->warn('No employees found. Skipping ReimbursementSeeder.');
            return;
        }

        foreach ($employees as $employee) {
            // Create 1-3 reimbursements per employee
            for ($i = 0; $i < rand(1, 3); $i++) {
                $status = fake()->randomElement(['draft', 'submitted', 'approved', 'rejected', 'paid']);
                $category = fake()->randomElement([
                    'travel', 'medical', 'office_supplies', 'training',
                    'meal', 'accommodation', 'transportation', 'other',
                ]);

                $reimbursement = Reimbursement::create([
                    'employee_id'  => $employee->id,
                    'title'        => fake()->sentence(3),
                    'description'  => fake()->paragraph(),
                    'amount'       => fake()->randomFloat(2, 10, 1000),
                    'category'     => $category,
                    'expense_date' => fake()->dateTimeBetween('-30 days', 'now'),
                    'status'       => $status,
                    'receipt_path' => rand(0, 1) ? 'receipts/' . fake()->uuid() . '.jpg' : null,
                ]);

                // Set approval data for non-draft statuses
                if (in_array($status, ['approved', 'rejected', 'paid'])) {
                    $reimbursement->update([
                        'submitted_at'  => fake()->dateTimeBetween('-20 days', '-5 days'),
                        'approved_by'   => $admin?->id,
                        'approved_at'   => fake()->dateTimeBetween('-5 days', 'now'),
                        'approval_note' => $status === 'rejected' ? fake()->sentence() : null,
                    ]);

                    if ($status === 'paid') {
                        $reimbursement->update([
                            'paid_at' => fake()->dateTimeBetween('-2 days', 'now'),
                        ]);
                    }
                } elseif ($status === 'submitted') {
                    $reimbursement->update([
                        'submitted_at' => fake()->dateTimeBetween('-10 days', '-1 day'),
                    ]);
                }
            }
        }

        $this->command?->info('Reimbursement records seeded successfully.');
    }
}


