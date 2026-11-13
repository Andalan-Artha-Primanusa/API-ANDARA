<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeLifecycleEvent;
use App\Services\ApprovalFlowService;
use App\Services\CompanyScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotionController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = EmployeeLifecycleEvent::with([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'initiator:id,user_id,employee_code,department_id,position_id',
            'initiator.user:id,name,email',
            'initiator.user.profile:id,user_id,profile_photo_path',
            'initiator.department:id,name',
            'initiator.position:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'reportApprover:id,name,email',
            'reportApprover.profile:id,user_id,profile_photo_path',
            'approvalFlow.steps.role',
            'approvalFlow.steps.user',
        ])
        ->where('event_type', 'promotion');

        if ($user->hasPermission('career.promotion.view')) {
            // Admin/HR/SuperAdmin/explicit-permission: see all
        } elseif ($user->isManager()) {
            $employeeId = $user->employee?->id;
            $subordinateIds = Employee::where('manager_id', $employeeId)->pluck('id');
            $query->where(function ($q) use ($employeeId, $subordinateIds) {
                $q->whereIn('employee_id', $subordinateIds)
                  ->orWhere('employee_id', $employeeId)
                  ->orWhere('initiated_by_id', $employeeId);
            });
        } else {
            $employeeId = $user->employee?->id;
            $query->where(function ($q) use ($employeeId) {
                $q->where('employee_id', $employeeId)
                  ->orWhere('initiated_by_id', $employeeId);
            });
        }

        app(CompanyScopeService::class)->applyThroughEmployee($query, $request);

        $status = $request->query('status');
        $search = $request->query('search');

        if ($status) {
            $query->where('status', $status);
        }
        if ($search) {
            $query->whereHas('employee.user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $promotions = $query->latest()->paginate($request->integer('per_page', 10))->withQueryString();
        $service = app(ApprovalFlowService::class);
        $promotions->getCollection()->transform(function ($item) use ($service, $user) {
            $item->can_act = $service->canUserAct($item, $user);
            return $item;
        });
        return ApiResponse::success('Promotions retrieved', $promotions);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('career.promotion.create')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'new_position' => 'required|string|max:255',
            'new_department' => 'nullable|string|max:255',
            'new_salary' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:1000',
            'effective_date' => 'required|date',
        ]);

$employee = Employee::findOrFail($validated['employee_id']);

        if (!app(CompanyScopeService::class)->canAccessEmployeeCompany($employee->id, $request->user())) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        DB::beginTransaction();
        try {
            $event = EmployeeLifecycleEvent::create([
                'employee_id' => $employee->id,
                'event_type' => 'promotion',
                'event_date' => now(),
                'from_value' => $employee->position,
                'to_value' => $validated['new_position'],
                'reason' => $validated['reason'],
                'initiated_by_id' => $user->employee?->id,
                'status' => 'pending',
                'effective_date' => $validated['effective_date'],
                'remarks' => json_encode([
                    'new_department' => $validated['new_department'] ?? null,
                    'new_salary' => $validated['new_salary'] ?? null,
                ]),
            ]);

            $event->load(['employee.user', 'initiator.user']);

            // Apply approval flow
            try {
                $approvalService = app(ApprovalFlowService::class);
                $approvalService->applyToModel('promotion', $event);
            } catch (\RuntimeException $e) {
                // If no approval flow configured, keep simple approval
            }

            DB::commit();
            return ApiResponse::success('Pengajuan promosi berhasil dibuat', $event->fresh([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'initiator:id,user_id,employee_code,department_id,position_id',
                'initiator.user:id,name,email',
                'initiator.user.profile:id,user_id,profile_photo_path',
                'initiator.department:id,name',
                'initiator.position:id,name',
                'approvalFlow.steps.role', 
                'approvalFlow.steps.user'
            ]), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Gagal membuat pengajuan promosi', $e->getMessage(), 500);
        }
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $eventQuery = EmployeeLifecycleEvent::with('employee', 'approvalFlow.steps.role', 'approvalFlow.steps.user');
        app(CompanyScopeService::class)->applyThroughEmployee($eventQuery, $request);
        $event = $eventQuery->findOrFail($id);

        if ($event->event_type !== 'promotion') {
            return ApiResponse::error('Invalid event type', null, 400);
        }
        if (!$user->hasPermission('career.promotion.approve')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        // Use approval flow if configured
        if ($event->approval_flow_id) {
            try {
                $approvalService = app(ApprovalFlowService::class);
                $result = $approvalService->processApproval($event, $user, 'approved', $request->notes);

                $event = $result['model'];
                $event->load([
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id,profile_photo_path',
                    'employee.department:id,name',
                    'employee.position:id,name',
                    'approver:id,name,email',
                    'approver.profile:id,user_id,profile_photo_path',
                    'approvalFlow.steps.role', 
                    'approvalFlow.steps.user'
                ]);

                if ($result['final']) {
                    // Apply promotion changes to employee record
                    $this->applyPromotionChanges($event);
                    return ApiResponse::success('Promosi disetujui sepenuhnya', $event);
                }

                return ApiResponse::success('Disetujui - menunggu persetujuan ' . ($result['next_role'] ?? 'berikutnya'), $event);
            } catch (\DomainException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            } catch (\RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), null, 500);
            }
        }

        // Fallback: simple single-step approval
        if ($event->status !== 'pending') {
            return ApiResponse::error('Promotion already processed', null, 400);
        }
        if (!$event->employee) {
            return ApiResponse::error('Employee not found', null, 404);
        }

        DB::beginTransaction();
        try {
            $this->applyPromotionChanges($event);

            $event->update([
                'status' => 'approved',
                'approved_by_id' => $user->id,
                'approval_date' => now(),
            ]);
            $event->load([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'approver:id,name,email',
                'approver.profile:id,user_id,profile_photo_path'
            ]);

            DB::commit();
            return ApiResponse::success('Promosi disetujui', $event);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Gagal menyetujui promosi', $e->getMessage(), 500);
        }
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $eventQuery = EmployeeLifecycleEvent::with('employee', 'approvalFlow.steps.role', 'approvalFlow.steps.user');
        app(CompanyScopeService::class)->applyThroughEmployee($eventQuery, $request);
        $event = $eventQuery->findOrFail($id);

        if ($event->event_type !== 'promotion') {
            return ApiResponse::error('Invalid event type', null, 400);
        }
        if (!$user->hasPermission('career.promotion.approve')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        // Use approval flow if configured
        if ($event->approval_flow_id) {
            try {
                $validated = $request->validate([
                    'remarks' => 'nullable|string|max:500',
                ]);
                $approvalService = app(ApprovalFlowService::class);
                $result = $approvalService->processApproval($event, $user, 'rejected', $validated['remarks'] ?? null);

                return ApiResponse::success('Promosi ditolak', $result['model']->fresh([
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id,profile_photo_path',
                    'employee.department:id,name',
                    'employee.position:id,name',
                    'approver:id,name,email',
                    'approver.profile:id,user_id,profile_photo_path',
                    'approvalFlow.steps.role', 
                    'approvalFlow.steps.user'
                ]));
            } catch (\DomainException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            } catch (\RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), null, 500);
            }
        }

        // Fallback: simple single-step rejection
        if ($event->status !== 'pending') {
            return ApiResponse::error('Promotion already processed', null, 400);
        }

        $validated = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $event->update([
            'status' => 'rejected',
            'approved_by_id' => $user->id,
            'approval_date' => now(),
            'remarks' => $validated['remarks'] ?? $event->remarks,
        ]);

        return ApiResponse::success('Promosi ditolak', $event->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path'
        ]));
    }

    /**
     * Apply promotion changes to employee record.
     */
    private function applyPromotionChanges(EmployeeLifecycleEvent $event): void
    {
        $remarks = json_decode($event->remarks, true) ?? [];
        $employee = $event->employee;

        if (!$employee) {
            return;
        }

        DB::table('employees')
            ->where('id', $employee->id)
            ->update([
                'position' => $event->to_value,
                'department' => !empty($remarks['new_department']) ? $remarks['new_department'] : $employee->department,
                'salary' => !empty($remarks['new_salary']) ? $remarks['new_salary'] : $employee->salary,
                'updated_at' => now(),
            ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $eventQuery = EmployeeLifecycleEvent::query();
        app(CompanyScopeService::class)->applyThroughEmployee($eventQuery, $request);
        $event = $eventQuery->findOrFail($id);

        if ($event->event_type !== 'promotion') {
            return ApiResponse::error('Invalid event type', null, 400);
        }
        if (!$user->hasPermission('career.promotion.delete')) {
            return ApiResponse::error('Forbidden', null, 403);
        }
        if ($event->status !== 'pending') {
            return ApiResponse::error('Cannot delete processed promotion', null, 400);
        }

        $event->delete();
        return ApiResponse::success('Pengajuan promosi dihapus');
    }

    public function myPromotions(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        $query = EmployeeLifecycleEvent::with([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'initiator:id,user_id,employee_code,department_id,position_id',
            'initiator.user:id,name,email',
            'initiator.user.profile:id,user_id,profile_photo_path',
            'initiator.department:id,name',
            'initiator.position:id,name',
            'reportApprover:id,name,email',
            'reportApprover.profile:id,user_id,profile_photo_path',
        ])
        ->where('event_type', 'promotion');

        if ($user->hasPermission('career.promotion.view')) {
            // Admin/HR/Manager/explicit-permission: see all promotions
        } else {
            // Regular user: see promotions where they are the employee OR they initiated it
            $employeeId = $employee ? $employee->id : 0;
            $query->where(function ($q) use ($employeeId, $user) {
                $q->where('employee_id', $employeeId)
                  ->orWhere('initiated_by_id', $employeeId);
            });
        }

        $summary = [
            'total' => (clone $query)->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
        ];

        $promotions = $query->latest()->paginate($request->integer('per_page', 10))->withQueryString();

        return ApiResponse::success('My promotions', [
            'promotions' => $promotions,
            'summary' => $summary,
        ]);
    }

    public function submitReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return ApiResponse::error('Employee record not found', null, 404);
        }

        $eventQuery = EmployeeLifecycleEvent::with('employee');
        app(CompanyScopeService::class)->applyThroughEmployee($eventQuery, $request);
        $event = $eventQuery->findOrFail($id);

        if ($event->event_type !== 'promotion') {
            return ApiResponse::error('Invalid event type', null, 400);
        }
        if ($event->status !== 'approved') {
            return ApiResponse::error('Promotion must be approved first', null, 400);
        }
        if ($event->employee_id !== $employee->id) {
            return ApiResponse::error('Forbidden', 'This promotion is not yours', 403);
        }
        if ($event->report_status !== null) {
            return ApiResponse::error('Activity report already submitted', null, 400);
        }

        $validated = $request->validate([
            'activity_report' => 'required|string|max:5000',
        ]);

        $event->update([
            'activity_report' => $validated['activity_report'],
            'report_status' => 'submitted',
        ]);

        return ApiResponse::success('Activity report submitted', $event->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path'
        ]));
    }

    public function approveReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('career.promotion.approve')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        $eventQuery = EmployeeLifecycleEvent::with('employee');
        app(CompanyScopeService::class)->applyThroughEmployee($eventQuery, $request);
        $event = $eventQuery->findOrFail($id);

        if ($event->event_type !== 'promotion') {
            return ApiResponse::error('Invalid event type', null, 400);
        }
        if ($event->status !== 'approved') {
            return ApiResponse::error('Promotion is not approved', null, 400);
        }
        if ($event->report_status !== 'submitted') {
            return ApiResponse::error('No pending activity report', null, 400);
        }

        $event->update([
            'report_status' => 'approved',
            'report_approved_by_id' => $user->id,
            'report_approved_at' => now(),
            'status' => 'completed',
        ]);

        return ApiResponse::success('Activity report approved. Promotion completed!', $event->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'reportApprover:id,name,email',
            'reportApprover.profile:id,user_id,profile_photo_path'
        ]));
    }

    public function rejectReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('career.promotion.approve')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $eventQuery = EmployeeLifecycleEvent::with('employee');
        app(CompanyScopeService::class)->applyThroughEmployee($eventQuery, $request);
        $event = $eventQuery->findOrFail($id);

        if ($event->event_type !== 'promotion') {
            return ApiResponse::error('Invalid event type', null, 400);
        }
        if ($event->report_status !== 'submitted') {
            return ApiResponse::error('No pending activity report', null, 400);
        }

        $event->update([
            'report_status' => 'rejected',
            'report_approved_by_id' => $user->id,
            'report_approved_at' => now(),
            'report_rejection_reason' => $validated['rejection_reason'],
        ]);

        return ApiResponse::success('Activity report rejected', $event->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'reportApprover:id,name,email',
            'reportApprover.profile:id,user_id,profile_photo_path'
        ]));
    }
}

