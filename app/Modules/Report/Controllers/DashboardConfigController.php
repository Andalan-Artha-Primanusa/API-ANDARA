<?php

namespace App\Modules\Report\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DashboardConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardConfigController extends Controller
{
    public function widgets(Request $request): JsonResponse
    {
        $widgets = collect([
            ['key' => 'headcount', 'label' => 'Headcount', 'required_permission' => 'employee.view'],
            ['key' => 'attendance_today', 'label' => 'Attendance Today', 'required_permission' => 'reporting.attendance'],
            ['key' => 'leave_pending', 'label' => 'Pending Leave', 'required_permission' => 'leave.approve'],
            ['key' => 'payroll_cost', 'label' => 'Payroll Cost', 'required_permission' => 'reporting.payroll'],
            ['key' => 'reimbursement_cost', 'label' => 'Reimbursement Cost', 'required_permission' => 'reimbursement.view'],
            ['key' => 'kpi_summary', 'label' => 'KPI Summary', 'required_permission' => 'kpi.view'],
            ['key' => 'training_summary', 'label' => 'Training Summary', 'required_permission' => 'training.view'],
            ['key' => 'compliance_risk', 'label' => 'Compliance Risk', 'required_permission' => 'compliance.view'],
        ])->filter(fn ($widget) => $request->user()->hasPermission($widget['required_permission']))->values();

        return ApiResponse::success('Dashboard widgets retrieved', $widgets);
    }

    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('dashboard.customize_self')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $configs = DashboardConfig::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return ApiResponse::success('Dashboard configs retrieved', $configs);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('dashboard.customize_self')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'scope' => 'nullable|string|in:self,company,all_companies',
            'company_id' => 'nullable|exists:companies,id',
            'layout_json' => 'nullable|array',
            'filters_json' => 'nullable|array',
            'is_default' => 'nullable|boolean',
        ]);

        if (($validated['scope'] ?? 'self') === 'all_companies' && !$request->user()->hasPermission('dashboard.view_all_company')) {
            return ApiResponse::error('Forbidden', 'All-company dashboard is not allowed', 403);
        }

        if (!empty($validated['is_default'])) {
            DashboardConfig::where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        $config = DashboardConfig::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::success('Dashboard config saved', $config, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $config = DashboardConfig::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'scope' => 'sometimes|string|in:self,company,all_companies',
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'layout_json' => 'sometimes|nullable|array',
            'filters_json' => 'sometimes|nullable|array',
            'is_default' => 'sometimes|boolean',
        ]);

        if (($validated['scope'] ?? $config->scope) === 'all_companies' && !$request->user()->hasPermission('dashboard.view_all_company')) {
            return ApiResponse::error('Forbidden', 'All-company dashboard is not allowed', 403);
        }

        if (!empty($validated['is_default'])) {
            DashboardConfig::where('user_id', $request->user()->id)->where('id', '!=', $config->id)->update(['is_default' => false]);
        }

        $config->update($validated);

        return ApiResponse::success('Dashboard config updated', $config->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $config = DashboardConfig::where('user_id', $request->user()->id)->findOrFail($id);
        $config->delete();

        return ApiResponse::success('Dashboard config deleted');
    }
}
