<?php

namespace App\Modules\Asset\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Asset as InventoryAsset;
use App\Models\AssetAssignment as InventoryAssetAssignment;
use App\Modules\Employee\Models\Employee;
use App\Models\UserNotification;
use App\Services\ApprovalFlowService;
use App\Services\CompanyScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('asset.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|string|in:available,assigned,maintenance,retired',
            'condition' => 'sometimes|string|in:new,good,fair,damaged,retired',
            'search' => 'sometimes|string|max:255',
        ]);

        $query = InventoryAsset::with([
                'assignments.employee:id,user_id,employee_code,department_id,position_id',
                'assignments.employee.user:id,name,email',
                'assignments.employee.user.profile:id,user_id,profile_photo_path',
                'assignments.employee.department:id,name',
                'assignments.employee.position:id,name',
            ])->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['condition'])) {
            $query->where('condition', $validated['condition']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('code', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('brand', 'like', '%' . $search . '%')
                    ->orWhere('model', 'like', '%' . $search . '%')
                    ->orWhere('serial_number', 'like', '%' . $search . '%');
            });
        }

        app(CompanyScopeService::class)->applyEmployeeScope($query, $request);

        return ApiResponse::success('Assets retrieved successfully', $query->paginate($validated['per_page'] ?? 10));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('asset.create')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:100|unique:assets,code',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255|unique:assets,serial_number',
            'condition' => 'sometimes|string|in:new,good,fair,damaged,retired',
            'status' => 'sometimes|string|in:available,assigned,maintenance,retired',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $validated['company_id'] = $validated['company_id'] ?? app(CompanyScopeService::class)->selectedCompanyId($request);

        $asset = InventoryAsset::create($validated);

        return ApiResponse::success('Asset created successfully', $asset, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $query = InventoryAsset::with([
            'assignments.employee:id,user_id,employee_code,department_id,position_id',
            'assignments.employee.user:id,name,email',
            'assignments.employee.user.profile:id,user_id,profile_photo_path',
            'assignments.employee.department:id,name',
            'assignments.employee.position:id,name',
            'assignments.assignedBy:id,name,email',
            'assignments.assignedBy.profile:id,user_id,profile_photo_path'
        ]);
        app(CompanyScopeService::class)->applyEmployeeScope($query, $request);
        $asset = $query->find($id);

        if (!$asset) {
            return ApiResponse::error('Asset not found', null, 404);
        }

        $user = $request->user();
        if (!$user->hasPermission('asset.view')) {
            $employee = $user->employee;
            $hasAssignment = $asset->assignments->contains(fn ($assignment) => $assignment->employee_id === $employee?->id && $assignment->status === 'assigned');

            if (!$hasAssignment) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }
        }

        return ApiResponse::success('Asset detail', $asset);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('asset.update')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $query = InventoryAsset::query();
        app(CompanyScopeService::class)->applyEmployeeScope($query, $request);
        $asset = $query->find($id);

        if (!$asset) {
            return ApiResponse::error('Asset not found', null, 404);
        }

        $validated = $request->validate([
            'code' => 'sometimes|string|max:100|unique:assets,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|nullable|string|max:255',
            'brand' => 'sometimes|nullable|string|max:255',
            'model' => 'sometimes|nullable|string|max:255',
            'serial_number' => 'sometimes|nullable|string|max:255|unique:assets,serial_number,' . $id,
            'condition' => 'sometimes|string|in:new,good,fair,damaged,retired',
            'status' => 'sometimes|string|in:available,assigned,maintenance,retired',
            'purchase_date' => 'sometimes|nullable|date',
            'purchase_price' => 'sometimes|numeric|min:0',
            'notes' => 'sometimes|nullable|string',
        ]);

        $asset->update($validated);

        return ApiResponse::success('Asset updated successfully', $asset->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('asset.delete')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $query = InventoryAsset::with('assignments');
        app(CompanyScopeService::class)->applyEmployeeScope($query, $request);
        $asset = $query->find($id);

        if (!$asset) {
            return ApiResponse::error('Asset not found', null, 404);
        }

        $deleted = $asset->toArray();
        $asset->delete();

        return ApiResponse::success('Asset deleted successfully', $deleted);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('asset.assign')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'assignment_note' => 'nullable|string',
            'assigned_at' => 'nullable|date',
        ]);

        $query = InventoryAsset::query();
        app(CompanyScopeService::class)->applyEmployeeScope($query, $request);
        $asset = $query->find($id);

        if (!$asset) {
            return ApiResponse::error('Asset not found', null, 404);
        }

        if (!app(CompanyScopeService::class)->canAccessEmployeeCompany((int) $validated['employee_id'], $request->user())) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        if ($asset->status === 'retired') {
            return ApiResponse::error('Asset is retired', null, 400);
        }

        if (InventoryAssetAssignment::where('asset_id', $asset->id)->where('status', 'assigned')->exists()) {
            return ApiResponse::error('Asset already assigned', null, 400);
        }

        $assignment = InventoryAssetAssignment::create([
            'asset_id' => $asset->id,
            'employee_id' => $validated['employee_id'],
            'assigned_by' => $user->id,
            'assigned_at' => $validated['assigned_at'] ?? now(),
            'status' => 'assigned',
            'assignment_note' => $validated['assignment_note'] ?? null,
        ]);

        $asset->update(['status' => 'assigned']);

        try {
            $approvalService = app(ApprovalFlowService::class);
            $approvalService->applyToModel('asset_assignment', $assignment);
            $assignment->refresh();
        } catch (\RuntimeException $e) {
            // No approval flow configured â€” fall back to direct assignment
        }

        $employee = Employee::with('user')->find($validated['employee_id']);
        if ($employee?->user) {
            UserNotification::create([
                'user_id' => $employee->user_id,
                'sender_user_id' => $user->id,
                'title' => 'Asset assigned',
                'message' => 'An asset has been assigned to you: ' . $asset->name,
                'type' => 'asset.assigned',
                'category' => 'asset_management',
                'data' => [
                    'asset_id' => $asset->id,
                    'asset_code' => $asset->code,
                    'asset_name' => $asset->name,
                ],
            ]);
        }

        return ApiResponse::success('Asset assigned successfully', $assignment->load([
            'asset:id,code,name,brand,model', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'assignedBy:id,name,email', 
            'assignedBy.profile:id,user_id,profile_photo_path',
            'approvalFlow.steps.role', 
            'approvalFlow.steps.user'
        ]), 201);
    }

    public function approveAssignment(Request $request, int $assignmentId): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('asset.assign')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $query = InventoryAssetAssignment::with('approvalFlow.steps.role', 'approvalFlow.steps.user', 'asset');
        app(CompanyScopeService::class)->applyThroughEmployee($query, $request);
        $assignment = $query->findOrFail($assignmentId);

        if (!$assignment->approval_flow_id) {
            return ApiResponse::error('No approval flow configured for this assignment', null, 400);
        }

        try {
            $approvalService = app(ApprovalFlowService::class);
            $result = $approvalService->processApproval($assignment, $user, 'approved', $request->note);

            if ($result['final']) {
                $assignment->asset->update(['status' => 'assigned']);
            }

            return ApiResponse::success('Asset assignment approved', $result['model']->fresh([
                'asset:id,code,name,brand,model', 
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'assignedBy:id,name,email', 
                'assignedBy.profile:id,user_id,profile_photo_path',
                'approvalFlow.steps.role', 
                'approvalFlow.steps.user'
            ]));
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function rejectAssignment(Request $request, int $assignmentId): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('asset.assign')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $query = InventoryAssetAssignment::with('approvalFlow.steps.role', 'approvalFlow.steps.user', 'asset');
        app(CompanyScopeService::class)->applyThroughEmployee($query, $request);
        $assignment = $query->findOrFail($assignmentId);

        if (!$assignment->approval_flow_id) {
            return ApiResponse::error('No approval flow configured for this assignment', null, 400);
        }

        try {
            $approvalService = app(ApprovalFlowService::class);
            $result = $approvalService->processApproval($assignment, $user, 'rejected', $request->note ?? $request->input('note'));

            $assignment->asset->update(['status' => 'available']);

            return ApiResponse::success('Asset assignment rejected', $result['model']->fresh([
                'asset:id,code,name,brand,model', 
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'assignedBy:id,name,email', 
                'assignedBy.profile:id,user_id,profile_photo_path',
                'approvalFlow.steps.role', 
                'approvalFlow.steps.user'
            ]));
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function returnAsset(Request $request, int $assignmentId): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('asset.update')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'return_note' => 'nullable|string',
            'returned_at' => 'nullable|date',
            'condition' => 'nullable|string|in:new,good,fair,damaged,retired',
        ]);

        $query = InventoryAssetAssignment::with('asset', 'employee.user');
        app(CompanyScopeService::class)->applyThroughEmployee($query, $request);
        $assignment = $query->find($assignmentId);

        if (!$assignment) {
            return ApiResponse::error('Assignment not found', null, 404);
        }

        if ($assignment->status !== 'assigned') {
            return ApiResponse::error('Asset is not currently assigned', null, 400);
        }

        $assignment->update([
            'status' => 'returned',
            'returned_at' => $validated['returned_at'] ?? now(),
            'return_note' => $validated['return_note'] ?? null,
        ]);

        $assignment->asset->update([
            'status' => $validated['condition'] === 'retired' ? 'retired' : 'available',
            'condition' => $validated['condition'] ?? $assignment->asset->condition,
        ]);

        if ($assignment->employee?->user) {
            UserNotification::create([
                'user_id' => $assignment->employee->user_id,
                'sender_user_id' => $user->id,
                'title' => 'Asset returned',
                'message' => 'Asset return has been processed for: ' . $assignment->asset->name,
                'type' => 'asset.returned',
                'category' => 'asset_management',
                'data' => [
                    'asset_id' => $assignment->asset->id,
                    'asset_code' => $assignment->asset->code,
                    'asset_name' => $assignment->asset->name,
                ],
            ]);
        }

        return ApiResponse::success('Asset returned successfully', $assignment->fresh([
            'asset:id,code,name,brand,model', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'assignedBy:id,name,email', 
            'assignedBy.profile:id,user_id,profile_photo_path'
        ]));
    }

    public function returnAssetByEmployee(Request $request, int $assignmentId): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return ApiResponse::error('Employee record not found', null, 404);
        }

        $validated = $request->validate([
            'return_note' => 'nullable|string',
            'returned_at' => 'nullable|date',
        ]);

        $assignment = InventoryAssetAssignment::with('asset', 'employee.user')->find($assignmentId);

        if (!$assignment) {
            return ApiResponse::error('Assignment not found', null, 404);
        }

        if ($assignment->status !== 'assigned') {
            return ApiResponse::error('Asset is not currently assigned', null, 400);
        }

        if ($assignment->employee_id !== $employee->id) {
            return ApiResponse::error('Forbidden', 'This asset is not assigned to you', 403);
        }

        $assignment->update([
            'status' => 'returned',
            'returned_at' => $validated['returned_at'] ?? now(),
            'return_note' => $validated['return_note'] ?? null,
        ]);

        $assignment->asset->update([
            'status' => 'available',
        ]);

        return ApiResponse::success('Asset return request submitted', $assignment->fresh(['asset']));
    }

    public function assignments(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('asset.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status'   => 'sometimes|string|in:assigned,returned,pending,approved,rejected',
        ]);

        $query = InventoryAssetAssignment::with([
            'asset:id,code,name,brand,model', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'assignedBy:id,name,email', 
            'assignedBy.profile:id,user_id,profile_photo_path',
            'approvalFlow.steps.role',
            'approvalFlow.steps.user',
        ])->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        app(CompanyScopeService::class)->applyThroughEmployee($query, $request);

        $assignments = $query->paginate($validated['per_page'] ?? 10);
        $service = app(ApprovalFlowService::class);
        $assignments->getCollection()->transform(function ($item) use ($service, $user) {
            $item->can_act = $service->canUserAct($item, $user);
            return $item;
        });

        return ApiResponse::success(
            'Asset assignments retrieved successfully',
            $assignments
        );
    }

    public function myAssets(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return ApiResponse::success('My assets retrieved successfully', []);
        }

        $assignments = InventoryAssetAssignment::with([
                'asset:id,code,name,brand,model', 
                'assignedBy:id,name,email',
                'assignedBy.profile:id,user_id,profile_photo_path'
            ])
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return ApiResponse::success('My assets retrieved successfully', $assignments);
    }
}

