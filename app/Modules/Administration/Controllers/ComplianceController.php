<?php

namespace App\Modules\Administration\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EmployeeDocument;
use App\Models\HrServiceRequest;
use App\Models\Leave;
use App\Models\Reimbursement;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
            'expiring_days' => 'sometimes|integer|min:1|max:365',
        ]);

        $days = (int) ($validated['days'] ?? 30);
        $expiringDays = (int) ($validated['expiring_days'] ?? 30);
        $fromDate = now()->subDays($days - 1)->startOfDay();
        $toDate = now()->endOfDay();
        $expiringUntil = now()->addDays($expiringDays)->endOfDay();

        $pendingLeaves = Leave::query()->where('status', 'pending')->count();
        $submittedReimbursements = Reimbursement::query()->where('status', Reimbursement::STATUS_SUBMITTED)->count();
        $openRequests = HrServiceRequest::query()
            ->whereIn('status', [
                HrServiceRequest::STATUS_OPEN,
                HrServiceRequest::STATUS_IN_PROGRESS,
                HrServiceRequest::STATUS_WAITING_FOR_EMPLOYEE,
            ])
            ->count();

        $pendingDocuments = EmployeeDocument::query()->where('status', EmployeeDocument::STATUS_PENDING)->count();
        $rejectedDocuments = EmployeeDocument::query()->where('status', EmployeeDocument::STATUS_REJECTED)->count();
        $expiringDocuments = EmployeeDocument::query()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', now()->toDateString())
            ->whereDate('expires_at', '<=', $expiringUntil->toDateString())
            ->count();

        $auditTotal = AuditLog::query()->whereBetween('created_at', [$fromDate, $toDate])->count();
        $auditErrors = AuditLog::query()
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->where('status_code', '>=', 400)
            ->count();

        return ApiResponse::success('Compliance overview retrieved successfully', [
            'window' => [
                'days' => $days,
                'from' => $fromDate->toDateTimeString(),
                'to' => $toDate->toDateTimeString(),
                'expiring_days' => $expiringDays,
            ],
            'approvals' => [
                'pending_leaves' => $pendingLeaves,
                'submitted_reimbursements' => $submittedReimbursements,
                'open_service_requests' => $openRequests,
            ],
            'documents' => [
                'pending_review' => $pendingDocuments,
                'rejected' => $rejectedDocuments,
                'expiring_soon' => $expiringDocuments,
            ],
            'audit' => [
                'total_logs' => $auditTotal,
                'error_logs' => $auditErrors,
            ],
        ]);
    }

    public function auditSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
        ]);

        $days = (int) ($validated['days'] ?? 7);
        $fromDate = now()->subDays($days - 1)->startOfDay();
        $toDate = now()->endOfDay();

        $baseQuery = AuditLog::query()->whereBetween('created_at', [$fromDate, $toDate]);

        $total = (clone $baseQuery)->count();
        $successCount = (clone $baseQuery)->where('status_code', '<', 400)->count();
        $errorCount = (clone $baseQuery)->where('status_code', '>=', 400)->count();

        $byModule = (clone $baseQuery)
            ->selectRaw('COALESCE(module, ?) as module, COUNT(*) as total', ['unknown'])
            ->groupBy('module')
            ->orderByDesc('total')
            ->get();

        $topActions = (clone $baseQuery)
            ->selectRaw('action, method, COUNT(*) as total')
            ->groupBy('action', 'method')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $daily = (clone $baseQuery)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return ApiResponse::success('Audit summary retrieved successfully', [
            'window' => [
                'days' => $days,
                'from' => $fromDate->toDateTimeString(),
                'to' => $toDate->toDateTimeString(),
            ],
            'summary' => [
                'total' => $total,
                'success' => $successCount,
                'error' => $errorCount,
                'error_rate_percent' => $total > 0 ? round(($errorCount / $total) * 100, 2) : 0,
            ],
            'by_module' => $byModule,
            'top_actions' => $topActions,
            'daily' => $daily,
        ]);
    }

    public function expiringDocuments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|string|in:pending,approved,rejected,expired,archived',
        ]);

        $days = (int) ($validated['days'] ?? 30);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $today = Carbon::today();
        $until = Carbon::today()->addDays($days);

        $query = EmployeeDocument::query()
            ->with(['employee.user.profile', 'reviewer:id,name,email'])
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', $today->toDateString())
            ->whereDate('expires_at', '<=', $until->toDateString())
            ->orderBy('expires_at');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $documents = $query->paginate($perPage)->withQueryString();

        return ApiResponse::success('Expiring documents retrieved successfully', [
            'days' => $days,
            'from' => $today->toDateString(),
            'to' => $until->toDateString(),
            'documents' => $documents,
        ]);
    }
}
