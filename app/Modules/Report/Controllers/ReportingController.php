<?php

namespace App\Modules\Report\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CompanyScopeService;
use App\Services\ReportingService;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportingController extends Controller
{
    public function __construct(
        protected ReportingService $reportingService
    ) {}

    /**
     * Get comprehensive HR dashboard summary
     */
    public function dashboardSummary(Request $request): JsonResponse
    {
        $this->authorizeReporting($request);

        try {
            $filters = $request->only(['department', 'month', 'year', 'start_date', 'end_date']);
            $companyId = app(CompanyScopeService::class)->selectedCompanyId($request);
            $data = $this->reportingService->getDashboardSummary($filters, $companyId);

            return ApiResponse::success('Dashboard summary retrieved successfully', $data);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve dashboard summary', $e->getMessage(), 500);
        }
    }

    /**
     * Get detailed attendance analytics
     */
    public function attendanceAnalytics(Request $request): JsonResponse
    {
        $this->authorizeReporting($request);

        try {
            $filters = $request->only(['start_date', 'end_date']);
            $companyId = app(CompanyScopeService::class)->selectedCompanyId($request);
            $data = $this->reportingService->getAttendanceAnalytics($filters, $companyId);

            return ApiResponse::success('Attendance analytics retrieved successfully', $data);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve attendance analytics', $e->getMessage(), 500);
        }
    }

    /**
     * Get leave utilization analytics
     */
    public function leaveAnalytics(Request $request): JsonResponse
    {
        $this->authorizeReporting($request);

        try {
            $filters = $request->only(['year']);
            $companyId = app(CompanyScopeService::class)->selectedCompanyId($request);
            $data = $this->reportingService->getLeaveAnalytics($filters, $companyId);

            return ApiResponse::success('Leave analytics retrieved successfully', $data);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve leave analytics', $e->getMessage(), 500);
        }
    }

    /**
     * Get payroll analytics
     */
    public function payrollAnalytics(Request $request): JsonResponse
    {
        $this->authorizeReporting($request);

        try {
            $filters = $request->only(['start_date', 'end_date', 'month', 'year']);
            $companyId = app(CompanyScopeService::class)->selectedCompanyId($request);
            $data = $this->reportingService->getPayrollAnalytics($filters, $companyId);

            return ApiResponse::success('Payroll analytics retrieved successfully', $data);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve payroll analytics', $e->getMessage(), 500);
        }
    }

    /**
     * Get competency and training analytics
     */
    public function competencyAnalytics(Request $request): JsonResponse
    {
        $this->authorizeReporting($request);

        try {
            $companyId = app(CompanyScopeService::class)->selectedCompanyId($request);
            $data = $this->reportingService->getCompetencyAnalytics($companyId);

            return ApiResponse::success('Competency analytics retrieved successfully', $data);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve competency analytics', $e->getMessage(), 500);
        }
    }

    /**
     * Get employee lifecycle analytics
     */
    public function employeeLifecycleAnalytics(Request $request): JsonResponse
    {
        $this->authorizeReporting($request);

        try {
            $filters = $request->only(['year']);
            $companyId = app(CompanyScopeService::class)->selectedCompanyId($request);
            $data = $this->reportingService->getEmployeeLifecycleAnalytics($filters, $companyId);

            return ApiResponse::success('Employee lifecycle analytics retrieved successfully', $data);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve employee lifecycle analytics', $e->getMessage(), 500);
        }
    }

    /**
     * Get asset management analytics
     */
    public function assetAnalytics(Request $request): JsonResponse
    {
        $this->authorizeReporting($request);

        try {
            $companyId = app(CompanyScopeService::class)->selectedCompanyId($request);
            $data = $this->reportingService->getAssetAnalytics($companyId);

            return ApiResponse::success('Asset analytics retrieved successfully', $data);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve asset analytics', $e->getMessage(), 500);
        }
    }

    /**
     * Authorize user for reporting access
     */
    private function authorizeReporting(Request $request): void
    {
        $user = $request->user();

        if (!$user->hasPermission('reporting.dashboard')) {
            abort(403, 'Unauthorized to access reporting');
        }
    }
}
