<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Leave\Models\Leave;
use App\Modules\Employee\Models\Employee;
use App\Modules\Approval\Models\ApprovalFlow;
use App\Enums\LeaveStatus;
use Carbon\Carbon;

class LeaveSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::all();
        $flow = ApprovalFlow::where('module', 'leave')->first();

        if ($employees->isEmpty()) {
            $this->command?->warn('No employees found. Skipping LeaveSeeder.');
            return;
        }

        foreach ($employees as $employee) {
            // Each employee gets 1-3 leave requests
            for ($i = 0; $i < rand(1, 3); $i++) {
                $start = Carbon::now()->subDays(rand(1, 60));
                $end = (clone $start)->addDays(rand(1, 5));

                Leave::create([
                    'user_id'          => $employee->user_id,
                    'employee_id'      => $employee->id,
                    'start_date'       => $start,
                    'end_date'         => $end,
                    'total_days'       => $start->diffInDays($end) + 1,
                    'type'             => fake()->randomElement(['annual', 'sick', 'unpaid']),
                    'reason'           => fake()->sentence(),
                    'status'           => fake()->randomElement([
                        LeaveStatus::Pending,
                        LeaveStatus::Approved,
                        LeaveStatus::Rejected,
                    ]),
                    'approval_flow_id' => $flow?->id,
                    'current_step'     => 1,
                ]);
            }
        }

        $this->command?->info('Leave records seeded successfully.');
    }
}



