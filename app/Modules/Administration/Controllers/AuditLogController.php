<?php

namespace App\Modules\Administration\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('admin.audit.view') && !$user->hasPermission('audit.logs.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'user_id' => 'sometimes|integer|exists:users,id',
            'module' => 'sometimes|string|max:100',
            'action' => 'sometimes|string|max:255',
            'route_name' => 'sometimes|string|max:255',
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to' => 'sometimes|date_format:Y-m-d|after_or_equal:date_from',
            'search' => 'sometimes|string|max:255',
        ]);

        $query = AuditLog::with('user:id,name,email')->latest();

        if (!empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (!empty($validated['module'])) {
            $query->where('module', $validated['module']);
        }

        if (!empty($validated['action'])) {
            $query->where('action', 'like', '%' . $validated['action'] . '%');
        }

        if (!empty($validated['route_name'])) {
            $query->where('route_name', 'like', '%' . $validated['route_name'] . '%');
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('path', 'like', '%' . $search . '%')
                    ->orWhere('method', 'like', '%' . $search . '%')
                    ->orWhere('module', 'like', '%' . $search . '%');
            });
        }

        $logs = $query->paginate($validated['per_page'] ?? 10)->withQueryString();

        return ApiResponse::success('Audit logs retrieved successfully', $logs);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->hasPermission('admin.audit.view') && !$user->hasPermission('audit.logs.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $log = AuditLog::with('user:id,name,email')->find($id);

        if (!$log) {
            return ApiResponse::error('Audit log not found', null, 404);
        }

        return ApiResponse::success('Audit log detail', $log);
    }
}
