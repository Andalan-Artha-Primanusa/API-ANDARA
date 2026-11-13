<?php

namespace App\Modules\Administration\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ApprovalFlow;
use App\Models\ApprovalStep;
use App\Services\ApprovalFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApprovalFlowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.approval_flow.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $flows = ApprovalFlow::with([
                'steps.role:id,name',
                'steps.user:id,name,email',
                'steps.user.profile:id,user_id,profile_photo_path',
                'steps.user.employee:id,user_id,employee_code,department_id,position_id',
                'steps.user.employee.department:id,name',
                'steps.user.employee.position:id,name',
            ])
            ->withCount('steps')
            ->orderBy('module')
            ->orderBy('name')
            ->get();

        return ApiResponse::success('Approval flows retrieved successfully', $flows);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.approval_flow.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $flow = ApprovalFlow::with([
            'steps.role:id,name',
            'steps.user:id,name,email',
            'steps.user.profile:id,user_id,profile_photo_path',
            'steps.user.employee:id,user_id,employee_code,department_id,position_id',
            'steps.user.employee.department:id,name',
            'steps.user.employee.position:id,name',
        ])->find($id);

        if (!$flow) {
            return ApiResponse::error('Approval flow not found', null, 404);
        }

        return ApiResponse::success('Approval flow detail', $flow);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.approval_flow.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'module' => 'required|string|max:100',
            'company_id' => 'nullable|integer|exists:companies,id',
            'steps' => 'required|array|min:1',
            'steps.*.step_order' => 'required|integer|min:1|distinct',
            'steps.*.role_id' => 'required|exists:roles,id',
            'steps.*.user_id' => 'nullable|exists:users,id',
        ]);

        $companyId = $validated['company_id'] ?? null;

        $exists = ApprovalFlow::where('module', $validated['module'])
            ->where('is_active', true)
            ->where('company_id', $companyId)
            ->exists();

        if ($exists) {
            return ApiResponse::error('Approval flow already exists for this module/company while active', null, 422);
        }

        $flow = DB::transaction(function () use ($validated, $companyId) {
            $flow = ApprovalFlow::create([
                'name' => $validated['name'],
                'module' => $validated['module'],
                'company_id' => $companyId,
                'is_active' => true,
            ]);

            foreach ($validated['steps'] as $step) {
                ApprovalStep::create([
                    'approval_flow_id' => $flow->id,
                    'step_order' => $step['step_order'],
                    'role_id' => $step['role_id'],
                    'user_id' => $step['user_id'] ?? null,
                ]);
            }

            return $flow->load([
                'steps.role:id,name',
                'steps.user:id,name,email',
                'steps.user.profile:id,user_id,profile_photo_path',
                'steps.user.employee:id,user_id,employee_code,department_id,position_id',
                'steps.user.employee.department:id,name',
                'steps.user.employee.position:id,name',
            ]);
        });

        return ApiResponse::success('Approval flow created successfully', $flow, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.approval_flow.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $flow = ApprovalFlow::with('steps.role', 'steps.user.employee')->find($id);

        if (!$flow) {
            return ApiResponse::error('Approval flow not found', null, 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'module' => 'sometimes|string|max:100|unique:approval_flows,module,' . $flow->id . ',id,is_active,1',
            'is_active' => 'sometimes|boolean',
            'steps' => 'sometimes|array|min:1',
            'steps.*.step_order' => 'required_with:steps|integer|min:1|distinct',
            'steps.*.role_id' => 'required_with:steps|exists:roles,id',
            'steps.*.user_id' => 'nullable|exists:users,id',
        ]);

        $flow = DB::transaction(function () use ($flow, $validated) {
            $flow->update(collect($validated)->only(['name', 'module', 'is_active'])->filter(fn($v) => $v !== null)->toArray());

            if (array_key_exists('steps', $validated)) {
                $flow->steps()->delete();

                foreach ($validated['steps'] as $step) {
                    ApprovalStep::create([
                        'approval_flow_id' => $flow->id,
                        'step_order' => $step['step_order'],
                        'role_id' => $step['role_id'],
                        'user_id' => $step['user_id'] ?? null,
                    ]);
                }
            }

            return $flow->fresh([
                'steps.role:id,name',
                'steps.user:id,name,email',
                'steps.user.profile:id,user_id,profile_photo_path',
                'steps.user.employee:id,user_id,employee_code,department_id,position_id',
                'steps.user.employee.department:id,name',
                'steps.user.employee.position:id,name',
            ]);
        });

        return ApiResponse::success('Approval flow updated successfully', $flow);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.approval_flow.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $flow = ApprovalFlow::with('steps.role')->find($id);

        if (!$flow) {
            return ApiResponse::error('Approval flow not found', null, 404);
        }

        try {
            $deleted = $flow->toArray();
            $flow->delete();

            return ApiResponse::success('Approval flow deleted successfully', $deleted);
        } catch (\Exception $e) {
            Log::error('Failed to delete approval flow', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Gagal menghapus approval flow: ' . $e->getMessage(), null, 500);
        }
    }

    public function history(Request $request, string $module, int $moduleId): JsonResponse
    {
        try {
            $user = $request->user();

            // Gap-4 fix: approval history tidak boleh dibaca sembarang user login.
            $modulePermissions = [
                'leave'               => ['leave.view', 'leave.approve'],
                'assignment_letter'   => ['assignment_letter.approve'],
                'overtime'            => ['overtime.view', 'overtime.approve'],
                'training'            => ['training.view', 'training.enroll'],
                'reimbursement'       => ['reimbursement.view', 'reimbursement.approve'],
                'promotion'           => ['career.promotion.approve'],
                'asset_assignment'    => ['asset.view', 'asset.assign'],
                'benefit_assignment'  => ['benefit.view'],
                'shift_swap'          => ['shift_swap.view'],
                'document'            => ['document.view', 'document.approve'],
                'payroll'             => ['payroll.view', 'payroll.approve'],
                'kpi'                 => ['kpi.view'],
            ];

            $allowed = $user->isSuperAdmin()
                || $user->hasPermission('admin.approval_flow.manage')
                || $user->hasAnyPermission($modulePermissions[$module] ?? []);

            if (!$allowed) {
                return ApiResponse::error('Forbidden', 'No permission to view approval history', 403);
            }

            $approvalService = app(ApprovalFlowService::class);
            $history = $approvalService->getHistory($module, $moduleId);

            $totalSteps = 0;
            if ($history->isNotEmpty()) {
                $flowId = $history->first()->approval_flow_id;
                $flow = \App\Models\ApprovalFlow::withCount('steps')->find($flowId);
                $totalSteps = $flow ? $flow->steps_count : 0;
            }

            if ($totalSteps === 0) {
                $totalSteps = $history->max('step_order') ?? 0;
            }

            return response()->json([
                'success' => true,
                'message' => 'Approval history retrieved',
                'data' => $history,
                'meta' => ['total_steps' => $totalSteps],
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch approval history', null, 500);
        }
    }
}
