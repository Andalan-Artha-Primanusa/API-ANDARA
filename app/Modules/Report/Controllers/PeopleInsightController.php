<?php

namespace App\Modules\Report\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CompanyScopeService;
use App\Services\PeopleInsightService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeopleInsightController extends Controller
{
    public function __construct(
        protected PeopleInsightService $peopleInsightService
    ) {}

    /**
     * HR/Manager/Admin dashboard for people metrics and risk alerts.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'window_days' => 'sometimes|integer|min:7|max:90',
            'department' => 'sometimes|string|max:100',
            'manager_user_id' => 'sometimes|integer|exists:users,id',
        ]);

        $windowDays = (int) ($validated['window_days'] ?? 30);
        $department = $validated['department'] ?? null;
        $managerUserId = isset($validated['manager_user_id']) ? (int) $validated['manager_user_id'] : null;
        $companyId = app(CompanyScopeService::class)->selectedCompanyId($request);

        $data = $this->peopleInsightService->buildDashboard($windowDays, $department, $managerUserId, $companyId);

        return ApiResponse::success('People insights dashboard retrieved successfully', $data);
    }

    public function trends(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'window_days' => 'sometimes|integer|min:7|max:90',
            'department' => 'sometimes|string|max:100',
            'manager_user_id' => 'sometimes|integer|exists:users,id',
        ]);

        $windowDays = (int) ($validated['window_days'] ?? 30);
        $department = $validated['department'] ?? null;
        $managerUserId = isset($validated['manager_user_id']) ? (int) $validated['manager_user_id'] : null;
        $companyId = app(CompanyScopeService::class)->selectedCompanyId($request);

        $data = $this->peopleInsightService->buildTrends($windowDays, $department, $managerUserId, $companyId);

        return ApiResponse::success('People insights trends retrieved successfully', $data);
    }

    public function detailedDashboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'window_days' => 'sometimes|integer|min:7|max:90',
            'department' => 'sometimes|string|max:100',
            'manager_user_id' => 'sometimes|integer|exists:users,id',
            'expiring_days' => 'sometimes|integer|min:1|max:365',
        ]);

        $windowDays = (int) ($validated['window_days'] ?? 30);
        $department = $validated['department'] ?? null;
        $managerUserId = isset($validated['manager_user_id']) ? (int) $validated['manager_user_id'] : null;
        $expiringDays = (int) ($validated['expiring_days'] ?? 30);
        $companyId = app(CompanyScopeService::class)->selectedCompanyId($request);

        $data = $this->peopleInsightService->buildDetailedDashboard($windowDays, $department, $managerUserId, $expiringDays, $companyId);

        return ApiResponse::success('Detailed people insights dashboard retrieved successfully', $data);
    }

    public function teamHealth(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'window_days' => 'sometimes|integer|min:7|max:90',
            'manager_user_id' => 'sometimes|integer|exists:users,id',
        ]);

        $windowDays = (int) ($validated['window_days'] ?? 30);
        $managerUserId = isset($validated['manager_user_id']) ? (int) $validated['manager_user_id'] : null;
        $companyId = app(CompanyScopeService::class)->selectedCompanyId($request);

        $data = $this->peopleInsightService->buildTeamHealth($windowDays, $managerUserId, $companyId);

        return ApiResponse::success('Team health insight retrieved successfully', $data);
    }

    public function employeeRiskDetail(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'window_days' => 'sometimes|integer|min:7|max:90',
        ]);

        $windowDays = (int) ($validated['window_days'] ?? 30);
        $companyId = app(CompanyScopeService::class)->selectedCompanyId($request);

        try {
            $data = $this->peopleInsightService->buildEmployeeRiskDetail($userId, $windowDays, $companyId);

            return ApiResponse::success('Employee risk detail retrieved successfully', $data);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Not found', $e->getMessage(), 404);
        }
    }
}
