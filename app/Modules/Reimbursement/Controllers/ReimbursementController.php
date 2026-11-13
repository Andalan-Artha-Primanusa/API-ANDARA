<?php

namespace App\Modules\Reimbursement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reimbursement;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreReimbursementRequest;
use App\Traits\HasEmployee;
use App\Services\ApprovalFlowService;
use App\Services\CompanyScopeService;
use Illuminate\Http\JsonResponse;

class ReimbursementController extends Controller
{
    use HasEmployee;

    public function __construct(
        protected CompanyScopeService $companyScope
    ) {}

    /*
    |--------------------------------------------------------------------------
    | 🔥 HR / MANAGER / FINANCE (Admin Level)
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Reimbursement::with([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.departmentRel:id,name',
            'employee.positionRel:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'approver.employee:id,user_id,position_id',
            'approver.employee.position:id,name',
            'approvalFlow.steps.role:id,name'
        ]);

        // Scope by manager subordinates if not admin/hr
        if ($user->isManager() && !$user->isAdmin() && !$user->isHR() && !$user->hasPermission('reimbursement.view')) {
            $subordinateIds = $user->teamMembers()->pluck('id');
            $query->whereIn('employee_id', $subordinateIds);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        if ($request->has('employee_id') && $request->employee_id) {
            // Further verify they can see this employee if they are a manager
            if ($user->isManager() && !$user->isAdmin() && !$user->isHR() && !$user->hasPermission('reimbursement.view')) {
                if (!in_array($request->employee_id, $subordinateIds->toArray())) {
                    return ApiResponse::error('Forbidden', 'Cannot access this employee data', 403);
                }
            }
            $query->where('employee_id', $request->employee_id);
        }

        $this->companyScope->applyThroughEmployee($query, $request);

        $reimbursements = $query->latest()->paginate($request->integer('per_page', 10))->withQueryString();
        $service = app(ApprovalFlowService::class);
        $reimbursements->getCollection()->transform(function ($item) use ($service, $user) {
            $item->can_act = $service->canUserAct($item, $user);
            return $item;
        });

        return ApiResponse::success('Reimbursements retrieved successfully', $reimbursements);
    }

    public function store(StoreReimbursementRequest $request): JsonResponse
    {
        $reimbursement = Reimbursement::create(array_merge(
            $request->validated(),
            ['status' => 'draft']
        ));

        return ApiResponse::success('Reimbursement created successfully', $reimbursement->load([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.departmentRel:id,name',
            'employee.positionRel:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'approver.employee:id,user_id,position_id',
            'approver.employee.position:id,name'
        ]));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $query = Reimbursement::with([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.departmentRel:id,name',
            'employee.positionRel:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'approver.employee:id,user_id,position_id',
            'approver.employee.position:id,name'
        ]);
        $this->companyScope->applyThroughEmployee($query, $request);
        $reimbursement = $query->findOrFail($id);

        return ApiResponse::success('Reimbursement details', $reimbursement);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $query = Reimbursement::query();
        $this->companyScope->applyThroughEmployee($query, $request);
        $reimbursement = $query->findOrFail($id);

        if (!$reimbursement->isDraft()) {
            return ApiResponse::error('Reimbursement already submitted cannot be updated', null, 400);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|numeric|min:0',
            'category' => 'sometimes|string|in:travel,medical,office_supplies,training,meal,accommodation,transportation,other',
            'expense_date' => 'sometimes|date|before_or_equal:today',
            'receipt_path' => 'nullable|string'
        ]);

        $reimbursement->update($request->only([
            'title', 'description', 'amount', 'category', 'expense_date', 'receipt_path'
        ]));

        return ApiResponse::success('Reimbursement updated successfully', $reimbursement->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.departmentRel:id,name',
            'employee.positionRel:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'approver.employee:id,user_id,position_id',
            'approver.employee.position:id,name'
        ]));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $query = Reimbursement::with(['employee.user', 'approver']);
        $this->companyScope->applyThroughEmployee($query, $request);
        $reimbursement = $query->findOrFail($id);

        if (!$reimbursement->isDraft()) {
            return ApiResponse::error('Submitted reimbursement cannot be deleted', null, 400);
        }

        $deleted = $reimbursement->toArray();
        $reimbursement->delete();

        return ApiResponse::success('Reimbursement deleted successfully', $deleted);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('reimbursement.approve')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }
        $query = Reimbursement::with('approvalFlow.steps.role', 'approvalFlow.steps.user');
        $this->companyScope->applyThroughEmployee($query, $request);
        $reimbursement = $query->findOrFail($id);

        // Use approval flow if configured
        if ($reimbursement->approval_flow_id) {
            try {
                $approvalService = app(ApprovalFlowService::class);
                $result = $approvalService->processApproval($reimbursement, $user, 'approved', $request->note);

                $reimbursement = $result['model'];
                $reimbursement->load([
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id,profile_photo_path',
                    'employee.department:id,name',
                    'employee.position:id,name',
                    'approver.profile:id,user_id,profile_photo_path',
                    'approvalFlow.steps'
                ]);
                
                if ($result['final']) {
                    return ApiResponse::success('Reimbursement fully approved', $reimbursement);
                }

                return ApiResponse::success('Approved - proceeding to next step (' . ($result['next_role'] ?? 'next approver') . ')', $reimbursement);
            } catch (\DomainException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            } catch (\RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), null, 500);
            }
        }

        // Fallback: simple single-step approval
        if (!$reimbursement->isSubmitted()) {
            return ApiResponse::error('Only submitted reimbursements can be approved', null, 400);
        }

        $reimbursement->approve($user->id, $request->note);

        return ApiResponse::success('Reimbursement approved', $reimbursement->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.departmentRel:id,name',
            'employee.positionRel:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'approver.employee:id,user_id,position_id',
            'approver.employee.position:id,name',
            'approvalFlow.steps'
        ]));
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('reimbursement.approve')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }
        $query = Reimbursement::with('approvalFlow.steps.role', 'approvalFlow.steps.user');
        $this->companyScope->applyThroughEmployee($query, $request);
        $reimbursement = $query->findOrFail($id);

        // Use approval flow if configured
        if ($reimbursement->approval_flow_id) {
            try {
                $approvalService = app(ApprovalFlowService::class);
                $result = $approvalService->processApproval($reimbursement, $user, 'rejected', $request->note ?? $request->input('note'));

                return ApiResponse::success('Reimbursement rejected', $result['model']->fresh([
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id,profile_photo_path',
                    'employee.department:id,name',
                    'employee.position:id,name',
                    'approver.profile:id,user_id,profile_photo_path',
                    'approvalFlow.steps'
                ]));
            } catch (\DomainException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            } catch (\RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), null, 500);
            }
        }

        // Fallback: simple single-step rejection
        if (!$reimbursement->isSubmitted()) {
            return ApiResponse::error('Only submitted reimbursements can be rejected', null, 400);
        }

        $request->validate([
            'note' => 'required|string|max:500'
        ]);

        $reimbursement->reject($user->id, $request->note);

        return ApiResponse::success('Reimbursement rejected', $reimbursement->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.departmentRel:id,name',
            'employee.positionRel:id,name',
            'approver.profile:id,user_id,profile_photo_path',
            'approvalFlow.steps'
        ]));
    }

    public function markAsPaid(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('reimbursement.pay')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }
        $query = Reimbursement::query();
        $this->companyScope->applyThroughEmployee($query, $request);
        $reimbursement = $query->findOrFail($id);

        if (!$reimbursement->isApproved()) {
            return ApiResponse::error('Only approved reimbursements can be marked as paid', null, 400);
        }

        $reimbursement->markAsPaid();

        return ApiResponse::success('Reimbursement marked as paid', $reimbursement->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.departmentRel:id,name',
            'employee.positionRel:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'approver.employee:id,user_id,position_id',
            'approver.employee.position:id,name'
        ]));
    }

    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Reimbursement::with([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.departmentRel:id,name',
            'employee.positionRel:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'approver.employee:id,user_id,position_id',
            'approver.employee.position:id,name',
            'approvalFlow.steps.role:id,name'
        ])
            ->where('status', 'submitted');

        $this->companyScope->applyThroughEmployee($query, $request);

        $reimbursements = $query
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $service = app(ApprovalFlowService::class);
        $reimbursements->getCollection()->transform(function ($item) use ($service, $user) {
            $item->can_act = $service->canUserAct($item, $user);
            return $item;
        });

        return ApiResponse::success('Pending reimbursements', $reimbursements);
    }

    public function byEmployee(Request $request, $employee_id): JsonResponse
    {
        $query = Reimbursement::where('employee_id', $employee_id)
            ->with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'approver.profile:id,user_id,profile_photo_path'
            ]);
        $this->companyScope->applyThroughEmployee($query, $request);
        $reimbursements = $query->latest()
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return ApiResponse::success('Employee reimbursements', $reimbursements);
    }

    public function statistics(Request $request): JsonResponse
    {
        $employeeId = $request->employee_id;

        $stats = [
            'total_count' => Reimbursement::getCountByStatus($employeeId),
            'total_amount' => Reimbursement::getTotalByStatus($employeeId),
            'draft_count' => Reimbursement::getCountByStatus($employeeId, 'draft'),
            'draft_amount' => Reimbursement::getTotalByStatus($employeeId, 'draft'),
            'submitted_count' => Reimbursement::getCountByStatus($employeeId, 'submitted'),
            'submitted_amount' => Reimbursement::getTotalByStatus($employeeId, 'submitted'),
            'approved_count' => Reimbursement::getCountByStatus($employeeId, 'approved'),
            'approved_amount' => Reimbursement::getTotalByStatus($employeeId, 'approved'),
            'paid_count' => Reimbursement::getCountByStatus($employeeId, 'paid'),
            'paid_amount' => Reimbursement::getTotalByStatus($employeeId, 'paid'),
        ];

        return ApiResponse::success('Reimbursement statistics', $stats);
    }

    /*
    |--------------------------------------------------------------------------
    | 👤 EMPLOYEE SELF-SERVICE (ESS)
    |--------------------------------------------------------------------------
    */

    public function myReimbursements(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        $query = Reimbursement::where('employee_id', $employee->id)
            ->with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'approver.profile:id,user_id,profile_photo_path'
            ]);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $reimbursements = $query->latest()->paginate($request->integer('per_page', 10))->withQueryString();

        return ApiResponse::success('My Reimbursements', $reimbursements);
    }

    public function submit(Request $request, $id): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();
        $reimbursement = Reimbursement::where('employee_id', $employee->id)->findOrFail($id);

        if (!$reimbursement->isDraft()) {
            return ApiResponse::error('Reimbursement is already submitted or processed', null, 400);
        }

        $reimbursement->submit();

        // Apply approval flow
        try {
            $approvalService = app(ApprovalFlowService::class);
            $approvalService->applyToModel('reimbursement', $reimbursement);
        } catch (\RuntimeException $e) {
            // If no approval flow configured, keep simple approval
        }

        return ApiResponse::success('Reimbursement submitted', $reimbursement->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.departmentRel:id,name',
            'employee.positionRel:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'approver.employee:id,user_id,position_id',
            'approver.employee.position:id,name',
            'approvalFlow.steps.role:id,name'
        ]));
    }

    public function createMyReimbursement(StoreReimbursementRequest $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        $reimbursement = Reimbursement::create(array_merge(
            $request->validated(),
            [
                'employee_id' => $employee->id,
                'status' => 'draft'
            ]
        ));

        return ApiResponse::success('My Reimbursement created successfully', $reimbursement->load([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.departmentRel:id,name',
            'employee.positionRel:id,name',
            'approver.profile:id,user_id,profile_photo_path'
        ]));
    }
}
