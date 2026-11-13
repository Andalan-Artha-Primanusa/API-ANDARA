<?php

namespace App\Modules\Leave\Services;

use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\ApprovalFlow;
use App\Modules\User\Models\User;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeavePolicy;
use App\Models\ApprovalFlowHistory;
use App\Services\CompanyScopeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use App\Enums\LeaveStatus;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LeaveService
{
    public function __construct(
        protected CompanyScopeService $companyScope
    ) {}

    /**
     * Create a new leave request and attach it to the approval flow.
     *
     * @throws \RuntimeException if no approval flow is configured for 'leave'
     */
    public function createLeave(User $user, array $data): Leave
    {
        $flow = ApprovalFlow::where('module', 'leave')->where('is_active', true)->first();

        if (!$flow) {
            throw new \DomainException('Leave approval flow has not been configured.');
        }

        $employee = $user->employee;
        if (!$employee) {
            if ($user->isAdmin() || $user->isSuperAdmin()) {
                throw new \DomainException('Super Admin must be linked to an Employee profile to request leaves.');
            }
            throw new \DomainException('Employee record not found for this user.');
        }

        $totalDays = Leave::calculateDays($data['start_date'], $data['end_date']);
        $leaveTypeId = $data['leave_type_id'] ?? null;
        $leaveTypeCode = $data['type'] ?? null;

        if ($leaveTypeId) {
            $leaveTypeModel = LeaveType::find($leaveTypeId);
            if (!$leaveTypeModel) {
                throw new \DomainException('Selected leave type not found.');
            }
            $leaveTypeCode = $leaveTypeModel->code;
        }

        if (!$leaveTypeCode) {
            throw new \DomainException('Leave type is required.');
        }

        return DB::transaction(function () use ($user, $data, $flow, $employee, $totalDays, $leaveTypeCode, $leaveTypeId) {
            $balance = $this->getOrCreateAnnualBalance($employee->id, (int) date('Y', strtotime($data['start_date'])));

            if ($balance->availableDays() < $totalDays) {
                throw new \DomainException('Insufficient leave balance for this request.');
            }

            $balance->increment('pending_days', $totalDays);

            $leave = Leave::create([
                'user_id'          => $user->id,
                'employee_id'      => $employee->id,
                'leave_type_id'    => $leaveTypeId,
                'start_date'       => $data['start_date'],
                'end_date'         => $data['end_date'],
                'total_days'       => $totalDays,
                'type'             => $leaveTypeCode,
                'reason'           => $data['reason'] ?? null,
                'status'           => LeaveStatus::Pending,
                'approval_flow_id' => $flow->id,
                'current_step'     => 1,
            ]);

            $firstStep = $flow->steps->where('step_order', 1)->first();
            if ($firstStep) {
                ApprovalFlowHistory::create([
                    'module' => 'leave',
                    'module_id' => $leave->id,
                    'approval_flow_id' => $flow->id,
                    'step_order' => 1,
                    'role_id' => $firstStep->role_id,
                    'user_id' => $firstStep->user_id,
                    'action' => 'pending',
                    'acted_at' => now(),
                ]);
            }

            return $leave->load([
                'user:id,name,email',
                'user.profile:id,user_id,profile_photo_path',
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'leaveType:id,name',
                'flow.steps.role:id,name',
                'approver.profile:id,user_id,profile_photo_path'
            ]);
        });
    }

    /**
     * Retrieve leaves based on the user's role/permissions.
     *
     * Authorization priority (most privileged first):
     * 1. Admin/HR/SuperAdmin â†’ all leaves
     * 2. Manager â†’ subordinates' leaves + own
     * 3. Employee â†’ own leaves only
     */
    public function getLeavesByRole(User $user, ?Request $request = null): LengthAwarePaginator
    {
        $query = Leave::with([
            'user:id,name,email',
            'user.profile:id,user_id,profile_photo_path',
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'leaveType:id,name',
            'flow.steps.role:id,name',
            'approver.profile:id,user_id,profile_photo_path'
        ]);

        // Admin/HR/SuperAdmin â€” see all (no filter)
        if ($user->isAdmin() || $user->isHR()) {
            // no filter â€” all records
        } elseif ($user->isManager()) {
            // Manager â€” subordinates' leaves + own
            $subordinateUserIds = $user->teamMembers()->pluck('user_id');
            $query->where(function ($q) use ($user, $subordinateUserIds) {
                $q->whereIn('user_id', $subordinateUserIds)
                  ->orWhere('user_id', $user->id);
            });
        } else {
            // Employee (default) â€” own leaves only
            $query->where('user_id', $user->id);
        }

        if ($request) {
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            $this->companyScope->applyThroughEmployee($query, $request);
        }

        return $query->latest()->paginate(request()->integer('per_page', 10));
    }

    /**
     * Process an approval or rejection for a leave request.
     *
     * @throws \DomainException for business rule violations
     * @throws \RuntimeException for system configuration issues
     * @return array{leave: Leave, final: bool, action: string, current_step?: int, next_role?: string}
     */
    public function processApproval(Leave $leave, User $approver, string $action, ?string $note = null): array
    {
        if (!$leave->isPending()) {
            throw new \DomainException('Leave request has already been processed.');
        }

        if ((int) $approver->id === (int) $leave->user_id) {
            throw new \DomainException('Anda tidak dapat menyetujui permintaan cuti Anda sendiri.');
        }

        // Admin/HR approvers can approve directly without waiting for the configured step role.
        if ($action === 'approved' && $this->canBypassLeaveApprovalStep($approver)) {
            $leave->update([
                'status' => LeaveStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);
            $this->finalizeAnnualLeave($leave);

            $this->recordHistory($leave, $approver, 'approved', null);

            return [
                'leave' => $leave->fresh([
                    'user:id,name,email',
                    'user.profile:id,user_id,profile_photo_path',
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id,profile_photo_path',
                    'employee.department:id,name',
                    'employee.position:id,name',
                    'leaveType:id,name',
                    'flow.steps.role:id,name',
                    'approver.profile:id,user_id,profile_photo_path'
                ]),
                'final' => true,
                'action' => 'approved',
                'override' => true,
                'approved_by_role' => $approver->isSuperAdmin() ? User::ROLE_SUPER_ADMIN : 'admin_or_hr',
            ];
        }

        if ($action === 'rejected' && $this->canBypassLeaveApprovalStep($approver)) {
            $this->releaseAnnualLeave($leave);

            $leave->update([
                'status' => LeaveStatus::Rejected,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_note' => $note,
            ]);

            $this->recordHistory($leave, $approver, 'rejected', $note);

            return [
                'leave' => $leave->fresh([
                    'user:id,name,email',
                    'user.profile:id,user_id,profile_photo_path',
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id,profile_photo_path',
                    'employee.department:id,name',
                    'employee.position:id,name',
                    'leaveType:id,name',
                    'flow.steps.role:id,name',
                    'approver.profile:id,user_id,profile_photo_path'
                ]),
                'final' => true,
                'action' => 'rejected',
                'override' => true,
                'approved_by_role' => $approver->isSuperAdmin() ? User::ROLE_SUPER_ADMIN : 'admin_or_hr',
            ];
        }

        if (!$leave->flow) {
            throw new \DomainException('Approval flow not found.');
        }

        $step = $leave->flow->steps
            ->where('step_order', $leave->current_step)
            ->first();

        if (!$step) {
            throw new \DomainException('Approval step not found.');
        }

        if (!$approver->hasRole($step->role->name)) {
            throw new \DomainException('It is not your turn to approve this request.');
        }

        if (!is_null($step->user_id) && $step->user_id !== $approver->id) {
            throw new \DomainException('This request is assigned to a specific approver.');
        }

        // Dynamic scope: jika role step tidak punya HR-level permission, batasi ke subordinate
        $step->role->loadMissing('permissions');
        $hasBroadScope = $step->role->permissions->contains('name', 'employee.delete');
        if (!$hasBroadScope) {
            $subordinates = $approver->teamMembers()->pluck('user_id');
            if (!$subordinates->contains($leave->user_id)) {
                throw new \DomainException('You can only approve requests for your direct subordinates.');
            }
        }

        // Rejection â€” immediately finalize
        if ($action === 'rejected') {
            $this->releaseAnnualLeave($leave);
            $leave->update([
                'status' => LeaveStatus::Rejected,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_note' => $note,
            ]);

            $this->recordHistory($leave, $approver, 'rejected', $note);

            return [
                'leave'  => $leave->fresh([
                    'user:id,name,email',
                    'user.profile:id,user_id,profile_photo_path',
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id,profile_photo_path',
                    'employee.department:id,name',
                    'employee.position:id,name',
                    'leaveType:id,name',
                    'flow.steps.role:id,name',
                    'approver.profile:id,user_id,profile_photo_path'
                ]),
                'final'  => true,
                'action' => 'rejected',
            ];
        }

        // Check if there's a next step
        $nextStep = $leave->flow->steps
            ->where('step_order', $leave->current_step + 1)
            ->first();

        if ($nextStep) {
            $leave->update(['current_step' => $leave->current_step + 1]);
            $leave->refresh();

            $this->recordHistory($leave, $approver, 'approved', $note, $step);
            $this->recordPendingHistory($leave, $nextStep);

            return [
                'leave' => $leave->load([
                    'user:id,name,email',
                    'user.profile:id,user_id,profile_photo_path',
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id,profile_photo_path',
                    'employee.department:id,name',
                    'employee.position:id,name',
                    'leaveType:id,name',
                    'flow.steps.role:id,name',
                    'approver.profile:id,user_id,profile_photo_path'
                ]),
                'final'        => false,
                'action'       => 'approved',
                'current_step' => $leave->current_step,
                'next_role'    => $nextStep->role->name,
            ];
        }

        // Final approval
        $leave->update([
            'status' => LeaveStatus::Approved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
        $this->finalizeAnnualLeave($leave);

        $this->recordHistory($leave, $approver, 'approved', $note, $step);

        return [
            'leave' => $leave->fresh([
                'user:id,name,email',
                'user.profile:id,user_id,profile_photo_path',
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'leaveType:id,name',
                'flow.steps.role:id,name',
                'approver.profile:id,user_id,profile_photo_path'
            ]),
            'final'  => true,
            'action' => 'approved',
        ];
    }

    private function recordHistory(Leave $leave, User $approver, string $action, ?string $note, $step = null): void
    {
        if (!$leave->flow) {
            return;
        }

        $currentStep = $step ?? $leave->flow->steps->where('step_order', $leave->current_step)->first();

        if (!$currentStep) {
            return;
        }

        ApprovalFlowHistory::create([
            'module' => 'leave',
            'module_id' => $leave->id,
            'approval_flow_id' => $leave->flow->id,
            'step_order' => $currentStep->step_order,
            'role_id' => $currentStep->role_id,
            'user_id' => $approver->id,
            'action' => $action,
            'note' => $note,
            'acted_at' => now(),
        ]);
    }

    private function recordPendingHistory(Leave $leave, $step): void
    {
        ApprovalFlowHistory::create([
            'module' => 'leave',
            'module_id' => $leave->id,
            'approval_flow_id' => $leave->flow->id,
            'step_order' => $step->step_order,
            'role_id' => $step->role_id,
            'user_id' => $step->user_id,
            'action' => 'pending',
            'acted_at' => now(),
        ]);
    }

    public function canUserAct(Leave $leave, User $user): bool
    {
        if (!$leave->isPending()) {
            return false;
        }

        if ((int) $leave->user_id === (int) $user->id) {
            return false;
        }

        if ($this->canBypassLeaveApprovalStep($user)) {
            return true;
        }

        if (!$leave->flow) {
            return false;
        }

        $leave->flow->loadMissing('steps.role.permissions');
        $step = $leave->flow->steps->where('step_order', $leave->current_step)->first();

        if (!$step || !$step->role || !$user->hasRole($step->role->name)) {
            return false;
        }

        if (!is_null($step->user_id) && (int) $step->user_id !== (int) $user->id) {
            return false;
        }

        $hasBroadScope = $step->role->permissions->contains('name', 'employee.delete');
        if ($hasBroadScope) {
            return true;
        }

        return $user->teamMembers()->pluck('user_id')->contains($leave->user_id);
    }

    public function getLeaveBalance(User $user): array
    {
        $employee = $user->employee;

        if (!$employee) {
            return [
                'policy' => null,
                'balance' => [
                    'allocated_days' => 0,
                    'carry_over_days' => 0,
                    'used_days' => 0,
                    'pending_days' => 0,
                    'available_days' => 0,
                ],
            ];
        }

        $year = (int) date('Y');
        // Prioritaskan kebijakan aktif terbaru untuk tahun ini agar mencerminkan konfigurasi terkini admin
        $policy = LeavePolicy::where('year', $year)->where('active', true)->latest()->first()
            ?? LeavePolicy::where('year', $year)->latest()->first()
            ?? LeavePolicy::firstOrCreate(
                ['year' => $year],
                ['annual_allowance' => 12, 'carry_over_allowance' => 0, 'max_pending_days' => 30, 'active' => true]
            );

        $balance = $this->getOrCreateAnnualBalance($employee->id, $year, $policy);

        // Jika rujukan kebijakan di saldo karyawan belum selaras dengan kebijakan aktif terbaru, sinkronisasikan secara otomatis
        if ($balance->leave_policy_id !== $policy->id) {
            $balance->update([
                'leave_policy_id' => $policy->id,
                'allocated_days' => $policy->annual_allowance
            ]);
        }

        $actualPolicy = $balance->fresh('policy')->policy ?? $policy;

        return [
            'policy' => $policy,
            'balance' => [
                'allocated_days' => $balance->allocated_days,
                'carry_over_days' => $balance->carry_over_days,
                'used_days' => $balance->used_days,
                'pending_days' => $balance->pending_days,
                'available_days' => $balance->availableDays(),
            ],
        ];
    }

    public function updatePendingLeave(Leave $leave, array $data): Leave
    {
        if (!$leave->isPending()) {
            throw new \DomainException('Only pending leaves can be updated.');
        }

        $oldDays = (int) $leave->total_days;
        $newStart = $data['start_date'] ?? Carbon::parse($leave->start_date)->toDateString();
        $newEnd = $data['end_date'] ?? Carbon::parse($leave->end_date)->toDateString();
        $newDays = Leave::calculateDays($newStart, $newEnd);

        return DB::transaction(function () use ($leave, $data, $oldDays, $newStart, $newEnd, $newDays) {
            $oldBalance = $this->getOrCreateAnnualBalance($leave->employee_id, (int) Carbon::parse($leave->start_date)->format('Y'));
            $this->decreasePendingDays($oldBalance, $oldDays);

            $newBalance = $this->getOrCreateAnnualBalance($leave->employee_id, (int) date('Y', strtotime($newStart)));
            if ($newBalance->availableDays() < $newDays) {
                throw new \DomainException('Insufficient leave balance for this update.');
            }
            $newBalance->increment('pending_days', $newDays);

            $leave->update([
                'start_date' => $newStart,
                'end_date' => $newEnd,
                'total_days' => $newDays,
                'reason' => $data['reason'] ?? $leave->reason,
            ]);

            return $leave->fresh([
                'user:id,name,email',
                'user.profile:id,user_id,profile_photo_path',
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'leaveType:id,name',
                'flow.steps.role:id,name',
                'approver.profile:id,user_id,profile_photo_path'
            ]);
        });
    }

    public function deletePendingLeave(Leave $leave): void
    {
        $this->releaseAnnualLeave($leave);

        $leave->delete();
    }

    private function getOrCreateAnnualBalance(int $employeeId, int $year, ?LeavePolicy $policy = null): EmployeeLeaveBalance
    {
        $policy ??= LeavePolicy::where('year', $year)->first() ?? LeavePolicy::firstOrCreate(
            ['year' => $year],
            ['annual_allowance' => 12, 'carry_over_allowance' => 0, 'max_pending_days' => 30, 'active' => true]
        );

        $previousYearBalance = EmployeeLeaveBalance::where('employee_id', $employeeId)
            ->where('year', $year - 1)
            ->where('leave_type', Leave::TYPE_ANNUAL)
            ->first();

        $carryOver = 0;
        if ($previousYearBalance) {
            $carryOver = min(
                $policy->carry_over_allowance,
                max(0, ($previousYearBalance->allocated_days + $previousYearBalance->carry_over_days) - $previousYearBalance->used_days)
            );
        }

        return EmployeeLeaveBalance::firstOrCreate(
            [
                'employee_id' => $employeeId,
                'year' => $year,
                'leave_type' => Leave::TYPE_ANNUAL,
            ],
            [
                'leave_policy_id' => $policy->id,
                'allocated_days' => $policy->annual_allowance,
                'carry_over_days' => $carryOver,
                'used_days' => 0,
                'pending_days' => 0,
            ]
        );
    }

    private function finalizeAnnualLeave(Leave $leave): void
    {
        // Selalu proses finalisasi saldo agar real-time dashboard terupdate

        $year = (int) Carbon::parse($leave->start_date)->format('Y');
        $balance = $this->getOrCreateAnnualBalance($leave->employee_id, $year);

        $balance->forceFill([
            'pending_days' => max(0, (int) $balance->pending_days - (int) $leave->total_days),
            'used_days' => (int) $balance->used_days + (int) $leave->total_days,
        ])->save();
    }

    private function releaseAnnualLeave(Leave $leave): void
    {
        // Selalu rilis saldo jika ditolak atau dibatalkan

        $year = (int) Carbon::parse($leave->start_date)->format('Y');
        $balance = $this->getOrCreateAnnualBalance($leave->employee_id, $year);

        $this->decreasePendingDays($balance, (int) $leave->total_days);
    }

    private function decreasePendingDays(EmployeeLeaveBalance $balance, int $days): void
    {
        $balance->forceFill([
            'pending_days' => max(0, (int) $balance->pending_days - $days),
        ])->save();
    }

    private function canBypassLeaveApprovalStep(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isAdmin()
            || $user->isHR()
            || $user->hasPermission('leave.policy.manage');
    }
}

