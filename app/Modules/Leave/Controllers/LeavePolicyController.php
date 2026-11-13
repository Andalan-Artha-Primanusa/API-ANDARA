<?php

namespace App\Modules\Leave\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LeavePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeavePolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // 📖 READ-ONLY reference data - all authenticated users can view
        $policies = LeavePolicy::where('active', true)->latest('year')->get();

        return ApiResponse::success('Leave policies retrieved successfully', $policies);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // 📖 READ-ONLY reference data - all authenticated users can view
        $policy = LeavePolicy::find($id);

        if (!$policy) {
            return ApiResponse::error('Leave policy not found', null, 404);
        }

        return ApiResponse::success('Leave policy retrieved successfully', $policy);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('leave.policy.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'policy_code' => 'required|string|max:50|unique:leave_policies,policy_code',
            'entitlement_type' => 'sometimes|string|in:fixed,accrual,unlimited',
            'entitlement_value' => 'sometimes|integer|min:0',
            'max_carryover_days' => 'sometimes|integer|min:0',
            'is_paid' => 'sometimes|boolean',
            'year' => 'sometimes|nullable|integer|min:2020|max:2100',
            'annual_allowance' => 'sometimes|integer|min:0|max:365',
            'carry_over_allowance' => 'sometimes|integer|min:0|max:365',
            'max_pending_days' => 'sometimes|integer|min:0|max:365',
            'active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $policy = LeavePolicy::create($validated);

        return ApiResponse::success('Leave policy created successfully', $policy, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('leave.policy.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $policy = LeavePolicy::find($id);

        if (!$policy) {
            return ApiResponse::error('Leave policy not found', null, 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'policy_code' => 'sometimes|required|string|max:50|unique:leave_policies,policy_code,' . $id,
            'entitlement_type' => 'sometimes|string|in:fixed,accrual,unlimited',
            'entitlement_value' => 'sometimes|integer|min:0',
            'max_carryover_days' => 'sometimes|integer|min:0',
            'is_paid' => 'sometimes|boolean',
            'year' => 'sometimes|nullable|integer|min:2020|max:2100',
            'annual_allowance' => 'sometimes|integer|min:0|max:365',
            'carry_over_allowance' => 'sometimes|integer|min:0|max:365',
            'max_pending_days' => 'sometimes|integer|min:0|max:365',
            'active' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string',
        ]);

        $policy->update($validated);

        return ApiResponse::success('Leave policy updated successfully', $policy->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('leave.policy.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $policy = LeavePolicy::find($id);

        if (!$policy) {
            return ApiResponse::error('Leave policy not found', null, 404);
        }

        $deleted = $policy->toArray();
        $policy->delete();

        return ApiResponse::success('Leave policy deleted successfully', $deleted);
    }
}
