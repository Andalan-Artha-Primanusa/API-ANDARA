<?php

namespace App\Modules\Leave\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreLeaveRequest;
use App\Http\Requests\ApproveLeaveRequest;
use App\Models\Leave;
use App\Models\LeavePolicy;
use App\Models\ApprovalFlow;
use App\Services\LeaveService;
use App\Services\CompanyScopeService;
use App\Traits\HasEmployee;
use App\Enums\LeaveStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    use HasEmployee;

    public function __construct(
        protected LeaveService $leaveService,
        protected CompanyScopeService $companyScope
    ) {}

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE SELF-SERVICE (ESS)
    |--------------------------------------------------------------------------
    */

    public function myLeaves(Request $request): JsonResponse
    {
        // Using user_id here as Leave is tied contextually to user mapping in current DB
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            if ($user->hasPermission('leave.view')) {
                $emptyLeaves = Leave::whereRaw('1 = 0')
                    ->paginate($request->integer('per_page', 10))
                    ->withQueryString();

                return ApiResponse::success('My Leaves', $emptyLeaves);
            }

            return ApiResponse::error('Forbidden: User is not an employee', null, 403);
        }

        $leaves = Leave::where('employee_id', $employee->id)
            ->with([
                'user:id,name,email',
                'user.profile:id,user_id,profile_photo_path',
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'leaveType:id,name'
            ])
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return ApiResponse::success('My Leaves', $leaves);
    }

    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $balance = $this->leaveService->getLeaveBalance($user);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 500);
        }

        return ApiResponse::success('Leave balance', $balance);
    }

    /*
    |--------------------------------------------------------------------------
    | GENERAL / ADMIN ROUTES
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('leave.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $leaves = $this->leaveService->getLeavesByRole($user, $request)->withQueryString();
        return ApiResponse::success('Leave list', $leaves);
    }

    public function store(StoreLeaveRequest $request): JsonResponse
    {
        try {
            $leave = $this->leaveService->createLeave(
                $request->user(),
                $request->validated()
            );

            return ApiResponse::success('Leave request submitted', $leave, 201);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
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
                'approver:id,name,email',
                'approver.profile:id,user_id,profile_photo_path',
                'approver.employee:id,user_id,position_id',
                'approver.employee.position:id,name'
            ]);

        $this->companyScope->applyThroughEmployee($query, $request);

        $leave = $query->findOrFail($id);

        $user = $request->user();

        if ($leave->user_id !== $user->id && !$user->hasPermission('leave.view')) {
            return ApiResponse::error('Forbidden', 'No permission to view this leave', 403);
        }

        return ApiResponse::success('Leave details', $leave);
    }

    public function calendar(Request $request): JsonResponse
    {
        // Validasi date range. Default ke bulan berjalan jika tidak dikirim.
        // Maksimal 90 hari untuk mencegah query data terlalu besar.
        $request->validate([
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'employee_id' => 'nullable|integer|exists:employees,id',
        ]);

        $startDate = $request->input('start_date')
            ? \Carbon\Carbon::parse($request->input('start_date'))->startOfDay()
            : \Carbon\Carbon::now()->startOfMonth();

        $endDate = $request->input('end_date')
            ? \Carbon\Carbon::parse($request->input('end_date'))->endOfDay()
            : \Carbon\Carbon::now()->endOfMonth();

        // Batasi maksimal 90 hari agar tidak bisa tarik data terlalu besar sekaligus
        if ($startDate->diffInDays($endDate) > 90) {
            $endDate = $startDate->copy()->addDays(90)->endOfDay();
        }

        $query = Leave::with([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
        ])
        // Filter berdasarkan overlap rentang tanggal: ambil leave yang menyentuh period ini
        ->where(function ($q) use ($startDate, $endDate) {
            $q->where('start_date', '<=', $endDate)
              ->where('end_date', '>=', $startDate);
        });

        $this->companyScope->applyThroughEmployee($query, $request);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        $leaves = $query->get();

        $data = $leaves->map(function ($leave) {
            $name = $leave->employee?->user?->name ?? 'User';
            $dept = $leave->employee?->department?->name ?? 'N/A';
            $pos  = $leave->employee?->position?->name ?? 'N/A';

            return [
                'id'     => $leave->id,
                'title'  => strtoupper($leave->type ?? '') . " - $name ($dept - $pos)",
                'start'  => $leave->start_date,
                'end'    => $leave->end_date,
                'status' => $leave->status,
                'employee' => [
                    'id'         => $leave->employee_id,
                    'name'       => $name,
                    'department' => $dept,
                    'position'   => $pos,
                    // Fix: gunakan profile_photo_path (field yang benar), bukan ->avatar
                    'avatar'     => $leave->employee?->user?->profile?->profile_photo_path,
                ],
            ];
        });

        return ApiResponse::success('Leave calendar', $data);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $query = Leave::query();
        $this->companyScope->applyThroughEmployee($query, $request);
        $leave = $query->findOrFail($id);

        if (!$leave->isPending()) {
            return ApiResponse::error('Only pending leaves can be updated', null, 400);
        }

        // Ensure user owns the leave or is admin
        $user = $request->user();

        if ($leave->user_id !== $user->id && !$user->hasPermission('leave.update')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        // Just handle basic updates (e.g. reason) for end user
        $request->validate([
            'reason' => 'sometimes|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date'
        ]);

        try {
            $leave = $this->leaveService->updatePendingLeave($leave, $request->only(['reason', 'start_date', 'end_date']));
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }

        return ApiResponse::success('Leave updated successfully', $leave->fresh([
            'user:id,name,email',
            'user.profile:id,user_id,profile_photo_path',
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'leaveType:id,name'
        ]));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $query = Leave::query();
        $this->companyScope->applyThroughEmployee($query, $request);
        $leave = $query->findOrFail($id);

        if (!$leave->isPending()) {
            return ApiResponse::error('Only pending leaves can be deleted', null, 400);
        }

        if ($leave->user_id !== $request->user()->id && !$request->user()->hasPermission('leave.delete')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $deleted = $leave->load([
            'user:id,name,email',
            'user.profile:id,user_id,profile_photo_path',
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'approver.profile:id,user_id,profile_photo_path',
            'flow.steps.role:id,name'
        ])->toArray();

        try {
            $this->leaveService->deletePendingLeave($leave);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }

        return ApiResponse::success('Leave deleted successfully', $deleted);
    }

    /*
    |--------------------------------------------------------------------------
    | HR / MANAGER APPROVAL FLOW
    |--------------------------------------------------------------------------
    */

    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

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
                'approver:id,name,email',
                'approver.profile:id,user_id,profile_photo_path',
                'approver.employee:id,user_id,position_id',
                'approver.employee.position:id,name'
            ])
            ->where('status', LeaveStatus::Pending->value);

        $this->companyScope->applyThroughEmployee($query, $request);

        if ($user->hasPermission('leave.approve')) {
            $leaves = $query->latest()->paginate($request->integer('per_page', 10))->withQueryString();
            
            $leaves->getCollection()->transform(function ($leave) use ($user) {
                $leave->setAttribute('can_act', $this->leaveService->canUserAct($leave, $user));
                return $leave;
            });

            return ApiResponse::success('Pending leaves', $leaves);
        }

        // Cari flow aktif untuk modul leave
        $flow = ApprovalFlow::where('module', 'leave')
            ->where('is_active', true)
            ->with('steps.role')
            ->first();

        if (!$flow || !$flow->steps) {
            return ApiResponse::success('Pending leaves', []);
        }

        // Filter steps di mana user saat ini berhak melakukan approval
        $validSteps = $flow->steps->filter(function ($step) use ($user) {
            if (!$step->role) {
                return false;
            }
            $hasRole = $user->hasRole($step->role->name);
            $userMatches = is_null($step->user_id) || $step->user_id === $user->id;
            return $hasRole && $userMatches;
        });

        if ($validSteps->isEmpty()) {
            return ApiResponse::success('Pending leaves', []);
        }

        // Terapkan filter visibilitas berdasarkan current_step dan batasan subordinate jika manager
        $query->where('approval_flow_id', $flow->id)
            ->where(function ($q) use ($validSteps, $user) {
                $subordinateUserIds = $user->teamMembers()->pluck('user_id');

                foreach ($validSteps as $step) {
                    $q->orWhere(function ($sq) use ($step, $subordinateUserIds) {
                        $sq->where('current_step', $step->step_order);

                        // Cek apakah role pada step ini memiliki "broad scope" (HR/Admin level),
                        // yang berarti mereka bisa melihat SEMUA pending leave, bukan hanya subordinate.
                        //
                        // 'leave.policy.manage' hanya dimiliki oleh role admin dan hr (bukan manager/employee),
                        // sehingga tepat digunakan sebagai penanda HR-level scope.
                        // Sumber: config/rbac.php — admin baris 26, hr baris 86, manager & employee: tidak ada.
                        $step->role->loadMissing('permissions');
                        $rolePermissions  = $step->role->permissions->pluck('name');
                        $hasBroadScope    = $rolePermissions->contains('leave.policy.manage');

                        if (!$hasBroadScope) {
                            $sq->whereIn('user_id', $subordinateUserIds);
                        }
                    });
                }
            });

        $leaves = $query->latest()->paginate($request->integer('per_page', 10))->withQueryString();

        // For non-admin users, leaves are already filtered to only show their steps
        $leaves->getCollection()->transform(function ($leave) use ($user) {
            $leave->setAttribute('can_act', $this->leaveService->canUserAct($leave, $user));
            return $leave;
        });

        return ApiResponse::success('Pending leaves', $leaves);
    }

    public function approve(ApproveLeaveRequest $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('leave.approve')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }
        $query = Leave::with('flow.steps.role');
        $this->companyScope->applyThroughEmployee($query, $request);
        $leave = $query->findOrFail($id);

        try {
            $result = $this->leaveService->processApproval(
                $leave,
                $request->user(),
                'approved',
                $request->note
            );
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 500);
        }

        $message = $result['final']
            ? 'Leave completely approved'
            : 'Approved step, proceeding to next step';

        return ApiResponse::success($message, $result);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('leave.approve')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $request->validate([
            'note' => 'required|string|max:500'
        ]);

        $query = Leave::with('flow.steps.role');
        $this->companyScope->applyThroughEmployee($query, $request);
        $leave = $query->findOrFail($id);

        if (!$leave->isPending()) {
            return ApiResponse::error('Leave is not pending', null, 400);
        }

        try {
            $result = $this->leaveService->processApproval($leave, $request->user(), 'rejected', $request->note);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 500);
        }

        return ApiResponse::success('Leave rejected successfully', $result['leave']->fresh([
            'user:id,name,email',
            'user.profile:id,user_id,profile_photo_path',
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'leaveType:id,name',
            'flow.steps.role:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'approver.employee:id,user_id,position_id',
            'approver.employee.position:id,name'
        ]));
    }

    public function returnForRevision(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('leave.approve')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $request->validate([
            'note' => 'required|string|max:500',
        ]);

        $query = Leave::with('flow.steps.role');
        $this->companyScope->applyThroughEmployee($query, $request);
        $leave = $query->findOrFail($id);

        try {
            $leave = $this->leaveService->returnForRevision($leave, $user, $request->note);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 500);
        }

        return ApiResponse::success('Leave returned for revision', $leave);
    }

    public function resubmit(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $query = Leave::with('flow.steps.role');
        $this->companyScope->applyThroughEmployee($query, $request);
        $leave = $query->findOrFail($id);

        if ($leave->user_id !== $user->id) {
            return ApiResponse::error('Hanya pemilik pengajuan yang dapat mengajukan ulang.', null, 403);
        }

        try {
            $leave = $this->leaveService->resubmit($leave, $user, $request->note);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 500);
        }

        return ApiResponse::success('Leave resubmitted successfully', $leave);
    }
}
