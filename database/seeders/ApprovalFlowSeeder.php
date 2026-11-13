<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Approval\Models\ApprovalFlow;
use App\Modules\Approval\Models\ApprovalStep;
use App\Modules\Administration\Models\Role;

class ApprovalFlowSeeder extends Seeder
{
    /**
     * Fully idempotent: safe to run multiple times without creating duplicates.
     */
    public function run(): void
    {
        $manager = Role::where('name', 'manager')->first();
        $hr = Role::where('name', 'hr')->first();

        if (!$manager || !$hr) {
            $this->command?->error('Role manager / hr not found! Run RbacSeeder first.');
            return;
        }

        $flows = [
            'leave'                => 'Leave Approval Flow',
            'assignment_letter'    => 'Assignment Letter Approval Flow',
            'overtime'             => 'Overtime Approval Flow',
            'training'             => 'Training Enrollment Approval Flow',
            'reimbursement'        => 'Reimbursement Approval Flow',
            'promotion'            => 'Promotion Approval Flow',
            'asset_assignment'     => 'Asset Assignment Approval Flow',
            'benefit_assignment'   => 'Benefit Assignment Approval Flow',
            'shift_swap'           => 'Shift Swap Approval Flow',
            'document'             => 'Employee Document Approval Flow',
            'payroll'              => 'Payroll Approval Flow',
            'kpi'                  => 'KPI Approval Flow',
            'benefit_assignment'   => 'Benefit Assignment Approval Flow',
        ];

        foreach ($flows as $module => $name) {
            $flow = ApprovalFlow::firstOrCreate(
                ['module' => $module],
                ['name' => $name, 'is_active' => true]
            );

            // Step 1 → Manager
            ApprovalStep::firstOrCreate(
                [
                    'approval_flow_id' => $flow->id,
                    'step_order' => 1,
                ],
                ['role_id' => $manager->id]
            );

            // Step 2 → HR
            ApprovalStep::firstOrCreate(
                [
                    'approval_flow_id' => $flow->id,
                    'step_order' => 2,
                ],
                ['role_id' => $hr->id]
            );

            $this->command?->info("Approval Flow for {$name} seeded successfully.");
        }
    }
}


