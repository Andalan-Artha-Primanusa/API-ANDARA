<?php

namespace App\Modules\Leave\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // 📖 READ-ONLY reference data - all authenticated users can view
        $types = LeaveType::where('is_active', true)->latest()->get();
        return ApiResponse::success('Leave types retrieved successfully', $types);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // 📖 READ-ONLY reference data - all authenticated users can view
        $type = LeaveType::find($id);
        if (!$type) {
            return ApiResponse::error('Leave type not found', null, 404);
        }

        return ApiResponse::success('Leave type retrieved successfully', $type);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('leave.policy.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:leave_types,code',
            'description' => 'nullable|string',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $type = LeaveType::create($validated);
        return ApiResponse::success('Leave type created successfully', $type, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('leave.policy.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $type = LeaveType::find($id);
        if (!$type) {
            return ApiResponse::error('Leave type not found', null, 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:leave_types,code,' . $id,
            'description' => 'nullable|string',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $type->update($validated);
        return ApiResponse::success('Leave type updated successfully', $type->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('leave.policy.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $type = LeaveType::find($id);
        if (!$type) {
            return ApiResponse::error('Leave type not found', null, 404);
        }

        $deleted = $type->toArray();
        $type->delete();
        return ApiResponse::success('Leave type deleted successfully', $deleted);
    }
}
