<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Administration\Models\Benefit;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeBenefit;
use App\Services\ApprovalFlowService;
use App\Traits\HasEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BenefitController extends Controller
{
    use HasEmployee;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'sometimes|string|in:allowance,insurance,reward,other',
            'is_active' => 'sometimes|boolean',
            'search' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Benefit::query()->latest();

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('code', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        return ApiResponse::success('Benefits retrieved successfully', $query->paginate($validated['per_page'] ?? 10)->withQueryString());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request, 'benefit.create');

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:benefits,code',
            'name' => 'required|string|max:255',
            'type' => 'sometimes|string|in:allowance,insurance,reward,other',
            'default_amount' => 'nullable|numeric|min:0',
            'is_taxable' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string|max:5000',
        ]);

        $benefit = Benefit::create([
            ...$validated,
            'type' => $validated['type'] ?? Benefit::TYPE_OTHER,
            'is_taxable' => $validated['is_taxable'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return ApiResponse::success('Benefit created successfully', $benefit, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $benefit = Benefit::with([
            'employeeBenefits.employee:id,user_id,employee_code,department_id,position_id',
            'employeeBenefits.employee.user:id,name,email',
            'employeeBenefits.employee.user.profile:id,user_id,profile_photo_path',
            'employeeBenefits.employee.department:id,name',
            'employeeBenefits.employee.position:id,name'
        ])->find($id);

        if (!$benefit) {
            return ApiResponse::error('Benefit not found', null, 404);
        }

        return ApiResponse::success('Benefit detail', $benefit);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request, 'benefit.update');

        $benefit = Benefit::find($id);

        if (!$benefit) {
            return ApiResponse::error('Benefit not found', null, 404);
        }

        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:benefits,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:allowance,insurance,reward,other',
            'default_amount' => 'sometimes|nullable|numeric|min:0',
            'is_taxable' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'description' => 'sometimes|nullable|string|max:5000',
        ]);

        $benefit->update($validated);

        return ApiResponse::success('Benefit updated successfully', $benefit->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request, 'benefit.delete');

        $benefit = Benefit::withCount('employeeBenefits')->find($id);

        if (!$benefit) {
            return ApiResponse::error('Benefit not found', null, 404);
        }

        $deleted = $benefit->toArray();
        $benefit->delete();

        return ApiResponse::success('Benefit deleted successfully', $deleted);
    }

    public function assignToEmployee(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request, 'benefit.assign');

        $benefit = Benefit::find($id);

        if (!$benefit) {
            return ApiResponse::error('Benefit not found', null, 404);
        }

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'custom_amount' => 'nullable|numeric|min:0',
            'status' => 'sometimes|string|in:active,inactive',
            'notes' => 'nullable|string|max:5000',
        ]);

        $assignment = EmployeeBenefit::create([
            'employee_id' => $validated['employee_id'],
            'benefit_id' => $benefit->id,
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            'custom_amount' => $validated['custom_amount'] ?? null,
            'status' => $validated['status'] ?? EmployeeBenefit::STATUS_ACTIVE,
            'notes' => $validated['notes'] ?? null,
            'assigned_by' => $request->user()->id,
        ]);

        try {
            $approvalService = app(ApprovalFlowService::class);
            $approvalService->applyToModel('benefit_assignment', $assignment);
            $assignment->refresh();
        } catch (\RuntimeException $e) {
            // No approval flow configured — fall back to direct assignment
        }

        return ApiResponse::success('Benefit assigned successfully', $assignment->load([
            'benefit', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'assigner:id,name,email', 
            'assigner.profile:id,user_id,profile_photo_path',
            'assigner.employee:id,user_id,position_id',
            'assigner.employee.position:id,name',
            'approvalFlow.steps.role', 
            'approvalFlow.steps.user'
        ]), 201);
    }

    public function approveBenefitAssignment(Request $request, int $assignmentId): JsonResponse
    {
        $this->authorizeManage($request, 'benefit.assign');

        $assignment = EmployeeBenefit::with('approvalFlow.steps.role', 'approvalFlow.steps.user')->findOrFail($assignmentId);

        if (!$assignment->approval_flow_id) {
            return ApiResponse::error('No approval flow configured for this benefit assignment', null, 400);
        }

        try {
            $approvalService = app(ApprovalFlowService::class);
            $result = $approvalService->processApproval($assignment, $request->user(), 'approved', $request->note);

            return ApiResponse::success('Benefit assignment approved', $result['model']->fresh(['benefit', 'employee.user.profile', 'assigner:id,name,email', 'approvalFlow.steps.role', 'approvalFlow.steps.user']));
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function rejectBenefitAssignment(Request $request, int $assignmentId): JsonResponse
    {
        $this->authorizeManage($request, 'benefit.assign');

        $assignment = EmployeeBenefit::with('approvalFlow.steps.role', 'approvalFlow.steps.user')->findOrFail($assignmentId);

        if (!$assignment->approval_flow_id) {
            return ApiResponse::error('No approval flow configured for this benefit assignment', null, 400);
        }

        try {
            $approvalService = app(ApprovalFlowService::class);
            $result = $approvalService->processApproval($assignment, $request->user(), 'rejected', $request->note ?? $request->input('note'));

            return ApiResponse::success('Benefit assignment rejected', $result['model']->fresh(['benefit', 'employee.user.profile', 'assigner:id,name,email', 'approvalFlow.steps.role', 'approvalFlow.steps.user']));
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function employeeBenefits(Request $request, int $employeeId): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('benefit.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $employee = Employee::find($employeeId);

        if (!$employee) {
            return ApiResponse::error('Employee not found', null, 404);
        }

        $validated = $request->validate([
            'status' => 'sometimes|string|in:active,inactive',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = EmployeeBenefit::with(['benefit', 'assigner:id,name,email', 'approvalFlow.steps.role', 'approvalFlow.steps.user'])
            ->where('employee_id', $employeeId)
            ->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $benefits = $query->paginate($validated['per_page'] ?? 10)->withQueryString();
        $service = app(\App\Services\ApprovalFlowService::class);
        $benefits->getCollection()->transform(function ($item) use ($service, $request) {
            $item->can_act = $service->canUserAct($item, $request->user());
            return $item;
        });

        return ApiResponse::success('Employee benefits retrieved successfully', $benefits->load([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name'
        ]));
    }

    public function myBenefits(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        $query = EmployeeBenefit::with(['benefit', 'assigner:id,name,email'])
            ->where('employee_id', $employee->id)
            ->latest();

        return ApiResponse::success('My benefits retrieved successfully', $query->paginate($request->integer('per_page', 10))->withQueryString()->load([
            'benefit', 
            'assigner:id,name,email',
            'assigner.profile:id,user_id,profile_photo_path',
            'assigner.employee:id,user_id,position_id',
            'assigner.employee.position:id,name'
        ]));
    }

    private function authorizeManage(Request $request, string $permission): void
    {
        $user = $request->user();

        if (!$user->hasPermission($permission)) {
            abort(403, 'No permission');
        }
    }
}


