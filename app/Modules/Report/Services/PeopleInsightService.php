<?php

namespace App\Modules\Report\Services;

use App\Models\Attendance;
use App\Modules\Employee\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\HrServiceRequest;
use App\Models\Kpi;
use App\Models\Leave;
use App\Models\Payroll;
use App\Models\Reimbursement;
use App\Models\TrainingEnrollment;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class PeopleInsightService
{
    /**
     * Build a summary dashboard for HR/Manager/Admin in a rolling time window.
     */
    public function buildDashboard(int $windowDays = 30, ?string $department = null, ?int $managerUserId = null, ?int $companyId = null): array
    {
        $windowDays = max(7, min(90, $windowDays));

        $toDate = now()->toDateString();
        $fromDate = now()->subDays($windowDays - 1)->toDateString();

        $scope = $this->resolveEmployeeScope($department, $managerUserId, $companyId);
        $employeeIds = $scope['employee_ids'];
        $userIds = $scope['user_ids'];

        $employeeCount = $employeeIds->count();
        $filters = $scope['filters'];

        if ($employeeCount === 0) {
            return [
                'window_days' => $windowDays,
                'period' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                ],
                'filters' => $filters,
                'summary' => [
                    'employee_count' => 0,
                    'attendance_rate_percent' => 0,
                    'late_rate_percent' => 0,
                    'late_count' => 0,
                    'absent_count' => 0,
                    'leave_pending_count' => 0,
                    'leave_approved_in_window' => 0,
                    'reimbursement_pending_count' => 0,
                    'reimbursement_pending_amount' => 0,
                    'average_kpi_score' => 0,
                ],
                'alerts' => [
                    'total' => 0,
                    'high' => 0,
                    'medium' => 0,
                    'items' => [],
                ],
                'recommended_actions' => [
                    'No employee data found for selected filter scope.',
                ],
            ];
        }

        $attendanceStats = Attendance::whereIn('user_id', $userIds)
            ->whereBetween('date', [$fromDate, $toDate])
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->first();

        $weekdayCount = $this->countWeekdays($fromDate, $toDate);
        $expectedAttendance = $employeeCount * $weekdayCount;

        $totalAttendance = (int) ($attendanceStats->total_records ?? 0);
        $lateCount = (int) ($attendanceStats->late_count ?? 0);
        $absentCount = (int) ($attendanceStats->absent_count ?? 0);

        $attendanceRate = $expectedAttendance > 0
            ? round(($totalAttendance / $expectedAttendance) * 100, 2)
            : 0;

        $lateRate = $totalAttendance > 0
            ? round(($lateCount / $totalAttendance) * 100, 2)
            : 0;

        $leavePending = Leave::whereIn('employee_id', $employeeIds)
            ->where('status', 'pending')
            ->count();

        $leaveApprovedWindow = Leave::whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->whereBetween('updated_at', [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()])
            ->count();

        $reimbursementPendingCount = Reimbursement::whereIn('employee_id', $employeeIds)
            ->where('status', Reimbursement::STATUS_SUBMITTED)
            ->count();

        $reimbursementPendingAmount = (float) Reimbursement::whereIn('employee_id', $employeeIds)
            ->where('status', Reimbursement::STATUS_SUBMITTED)
            ->sum('amount');

        $averageKpiScore = round((float) (Kpi::whereIn('employee_id', $employeeIds)->whereNotNull('score')->avg('score') ?? 0), 2);

        $riskAlerts = $this->buildRiskAlerts($fromDate, $toDate, $userIds, $scope['employee_map']);

        return [
            'window_days' => $windowDays,
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'filters' => $filters,
            'summary' => [
                'employee_count' => $employeeCount,
                'attendance_rate_percent' => $attendanceRate,
                'late_rate_percent' => $lateRate,
                'late_count' => $lateCount,
                'absent_count' => $absentCount,
                'leave_pending_count' => $leavePending,
                'leave_approved_in_window' => $leaveApprovedWindow,
                'reimbursement_pending_count' => $reimbursementPendingCount,
                'reimbursement_pending_amount' => round($reimbursementPendingAmount, 2),
                'average_kpi_score' => $averageKpiScore,
            ],
            'alerts' => [
                'total' => $riskAlerts->count(),
                'high' => $riskAlerts->where('risk_level', 'high')->count(),
                'medium' => $riskAlerts->where('risk_level', 'medium')->count(),
                'items' => $riskAlerts->values()->all(),
            ],
            'recommended_actions' => $this->buildRecommendedActions(
                $attendanceRate,
                $lateRate,
                $leavePending,
                $reimbursementPendingCount
            ),
        ];
    }

    public function buildDetailedDashboard(int $windowDays = 30, ?string $department = null, ?int $managerUserId = null, int $expiringDays = 30, ?int $companyId = null): array
    {
        $windowDays = max(7, min(90, $windowDays));
        $expiringDays = max(1, min(365, $expiringDays));

        $toDate = now()->toDateString();
        $fromDate = now()->subDays($windowDays - 1)->toDateString();

        $scope = $this->resolveEmployeeScope($department, $managerUserId, $companyId);
        $employees = $scope['employees'];
        $employeeIds = $scope['employee_ids'];
        $userIds = $scope['user_ids'];

        if ($employeeIds->isEmpty()) {
            return [
                'window_days' => $windowDays,
                'expiring_days' => $expiringDays,
                'period' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                ],
                'filters' => $scope['filters'],
                'headcount' => [
                    'total' => 0,
                    'by_department' => [],
                    'by_status' => [],
                ],
                'attendance' => [
                    'total_records' => 0,
                    'late_count' => 0,
                    'absent_count' => 0,
                    'overtime_minutes' => 0,
                    'overtime_hours' => 0,
                ],
                'leave' => [
                    'by_status' => [],
                    'by_type' => [],
                ],
                'payroll' => [
                    'records_count' => 0,
                    'total_take_home_pay' => 0,
                    'total_deduction' => 0,
                    'by_status' => [],
                ],
                'reimbursement' => [
                    'records_count' => 0,
                    'pending_count' => 0,
                    'total_amount' => 0,
                    'pending_amount' => 0,
                ],
                'training' => [
                    'enrollment_count' => 0,
                    'completed_count' => 0,
                    'completion_rate_percent' => 0,
                ],
                'helpdesk' => [
                    'ticket_count' => 0,
                    'open_ticket_count' => 0,
                    'avg_resolution_hours' => 0,
                    'by_status' => [],
                    'by_priority' => [],
                ],
                'documents' => [
                    'total_documents' => 0,
                    'expiring_soon_count' => 0,
                ],
            ];
        }

        $headcountByDepartment = $employees
            ->groupBy(fn ($item) => $item->department ?: 'Unassigned')
            ->map(fn (Collection $items) => $items->count())
            ->sortDesc()
            ->all();

        $headcountByStatus = $employees
            ->groupBy(fn ($item) => $item->status ?: 'unknown')
            ->map(fn (Collection $items) => $items->count())
            ->sortDesc()
            ->all();

        $attendanceRows = Attendance::whereIn('user_id', $userIds)
            ->whereBetween('date', [$fromDate, $toDate])
            ->get(['user_id', 'date', 'status', 'check_out']);

        $scheduleByUserId = Employee::with('workSchedule:id,check_out_time')
            ->whereIn('id', $employeeIds)
            ->get(['id', 'user_id', 'work_schedule_id'])
            ->keyBy('user_id');

        $overtimeMinutes = 0;
        foreach ($attendanceRows as $row) {
            if (!$row->check_out) {
                continue;
            }

            $employee = $scheduleByUserId->get($row->user_id);
            $checkOutTime = $employee?->workSchedule?->check_out_time;

            if (!$checkOutTime) {
                continue;
            }

            $attendanceDate = $row->date instanceof \Carbon\Carbon
                ? $row->date->toDateString()
                : Carbon::parse($row->date)->toDateString();

            $scheduled = Carbon::parse($attendanceDate . ' ' . $checkOutTime);
            $actual = $row->check_out instanceof \Carbon\Carbon
                ? $row->check_out
                : Carbon::parse($row->check_out);

            if ($actual->gt($scheduled)) {
                $overtimeMinutes += $actual->diffInMinutes($scheduled);
            }
        }

        $leaveByStatus = Leave::whereIn('employee_id', $employeeIds)
            ->whereBetween('created_at', [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value)
            ->all();

        $leaveByType = Leave::whereIn('employee_id', $employeeIds)
            ->whereBetween('created_at', [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()])
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->map(fn ($value) => (int) $value)
            ->all();

        $payrollBaseQuery = Payroll::whereIn('employee_id', $employeeIds)
            ->whereBetween('created_at', [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()]);

        $payrollSummary = (clone $payrollBaseQuery)
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('COALESCE(SUM(take_home_pay), 0) as total_take_home')
            ->selectRaw('COALESCE(SUM(total_deduction), 0) as total_deduction')
            ->first();

        $payrollByStatus = (clone $payrollBaseQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value)
            ->all();

        $reimbursementBaseQuery = Reimbursement::whereIn('employee_id', $employeeIds)
            ->whereBetween('created_at', [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()]);

        $reimbursementSummary = (clone $reimbursementBaseQuery)
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count", [Reimbursement::STATUS_SUBMITTED])
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as pending_amount", [Reimbursement::STATUS_SUBMITTED])
            ->first();

        $trainingBaseQuery = TrainingEnrollment::whereIn('employee_id', $employeeIds)
            ->whereBetween('created_at', [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()]);

        $trainingSummary = (clone $trainingBaseQuery)
            ->selectRaw('COUNT(*) as enrollment_count')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->first();

        $helpdeskBaseQuery = HrServiceRequest::whereIn('employee_id', $employeeIds)
            ->whereBetween('created_at', [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()]);

        $helpdeskRecords = (clone $helpdeskBaseQuery)->get(['status', 'priority', 'created_at', 'resolved_at']);
        $resolvedRecords = $helpdeskRecords->filter(fn ($row) => $row->resolved_at !== null);
        $avgResolutionHours = $resolvedRecords->isNotEmpty()
            ? round($resolvedRecords->avg(fn ($row) => Carbon::parse($row->created_at)->diffInHours(Carbon::parse($row->resolved_at))), 2)
            : 0;

        $helpdeskByStatus = $helpdeskRecords
            ->groupBy('status')
            ->map(fn (Collection $items) => $items->count())
            ->all();

        $helpdeskByPriority = $helpdeskRecords
            ->groupBy('priority')
            ->map(fn (Collection $items) => $items->count())
            ->all();

        $documentBaseQuery = EmployeeDocument::whereIn('employee_id', $employeeIds);
        $expiringSoonCount = (clone $documentBaseQuery)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->startOfDay(), now()->addDays($expiringDays)->endOfDay()])
            ->count();

        return [
            'window_days' => $windowDays,
            'expiring_days' => $expiringDays,
            'generated_at' => now()->toDateTimeString(),
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'filters' => $scope['filters'],
            'headcount' => [
                'total' => $employeeIds->count(),
                'by_department' => $headcountByDepartment,
                'by_status' => $headcountByStatus,
            ],
            'attendance' => [
                'total_records' => $attendanceRows->count(),
                'late_count' => $attendanceRows->where('status', 'late')->count(),
                'absent_count' => $attendanceRows->where('status', 'absent')->count(),
                'overtime_minutes' => $overtimeMinutes,
                'overtime_hours' => round($overtimeMinutes / 60, 2),
            ],
            'leave' => [
                'by_status' => $leaveByStatus,
                'by_type' => $leaveByType,
            ],
            'payroll' => [
                'records_count' => (int) ($payrollSummary->total_records ?? 0),
                'total_take_home_pay' => round((float) ($payrollSummary->total_take_home ?? 0), 2),
                'total_deduction' => round((float) ($payrollSummary->total_deduction ?? 0), 2),
                'by_status' => $payrollByStatus,
            ],
            'reimbursement' => [
                'records_count' => (int) ($reimbursementSummary->total_records ?? 0),
                'pending_count' => (int) ($reimbursementSummary->pending_count ?? 0),
                'total_amount' => round((float) ($reimbursementSummary->total_amount ?? 0), 2),
                'pending_amount' => round((float) ($reimbursementSummary->pending_amount ?? 0), 2),
            ],
            'training' => [
                'enrollment_count' => (int) ($trainingSummary->enrollment_count ?? 0),
                'completed_count' => (int) ($trainingSummary->completed_count ?? 0),
                'completion_rate_percent' => (int) ($trainingSummary->enrollment_count ?? 0) > 0
                    ? round(((int) $trainingSummary->completed_count / (int) $trainingSummary->enrollment_count) * 100, 2)
                    : 0,
            ],
            'helpdesk' => [
                'ticket_count' => $helpdeskRecords->count(),
                'open_ticket_count' => $helpdeskRecords->whereIn('status', ['open', 'in_progress', 'waiting_for_employee'])->count(),
                'avg_resolution_hours' => $avgResolutionHours,
                'by_status' => $helpdeskByStatus,
                'by_priority' => $helpdeskByPriority,
            ],
            'documents' => [
                'total_documents' => (clone $documentBaseQuery)->count(),
                'expiring_soon_count' => $expiringSoonCount,
            ],
        ];
    }

    public function buildTrends(int $windowDays = 30, ?string $department = null, ?int $managerUserId = null, ?int $companyId = null): array
    {
        $windowDays = max(7, min(90, $windowDays));

        $toDate = now()->toDateString();
        $fromDate = now()->subDays($windowDays - 1)->toDateString();

        $scope = $this->resolveEmployeeScope($department, $managerUserId, $companyId);
        $employeeIds = $scope['employee_ids'];
        $userIds = $scope['user_ids'];

        if ($employeeIds->isEmpty()) {
            return [
                'window_days' => $windowDays,
                'period' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                ],
                'filters' => $scope['filters'],
                'series' => [],
            ];
        }

        $attendanceRows = Attendance::whereIn('user_id', $userIds)
            ->whereBetween('date', [$fromDate, $toDate])
            ->selectRaw('DATE(date) as day')
            ->selectRaw('COUNT(*) as attendance_total')
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $leaveRows = Leave::whereIn('employee_id', $employeeIds)
            ->whereBetween('created_at', [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()])
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as leave_submitted')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $reimbursementRows = Reimbursement::whereIn('employee_id', $employeeIds)
            ->whereBetween('created_at', [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()])
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as reimbursement_submitted')
            ->selectRaw('COALESCE(SUM(amount), 0) as reimbursement_amount')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $series = [];

        foreach (CarbonPeriod::create($fromDate, $toDate) as $date) {
            $day = $date->toDateString();

            $attendance = $attendanceRows->get($day);
            $leave = $leaveRows->get($day);
            $reimbursement = $reimbursementRows->get($day);

            $attendanceTotal = (int) ($attendance->attendance_total ?? 0);
            $lateCount = (int) ($attendance->late_count ?? 0);

            $series[] = [
                'date' => $day,
                'attendance_total' => $attendanceTotal,
                'late_count' => $lateCount,
                'absent_count' => (int) ($attendance->absent_count ?? 0),
                'late_rate_percent' => $attendanceTotal > 0 ? round(($lateCount / $attendanceTotal) * 100, 2) : 0,
                'leave_submitted' => (int) ($leave->leave_submitted ?? 0),
                'reimbursement_submitted' => (int) ($reimbursement->reimbursement_submitted ?? 0),
                'reimbursement_amount' => round((float) ($reimbursement->reimbursement_amount ?? 0), 2),
            ];
        }

        return [
            'window_days' => $windowDays,
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'filters' => $scope['filters'],
            'series' => $series,
        ];
    }

    public function buildTeamHealth(int $windowDays = 30, ?int $managerUserId = null, ?int $companyId = null): array
    {
        $windowDays = max(7, min(90, $windowDays));

        $toDate = now()->toDateString();
        $fromDate = now()->subDays($windowDays - 1)->toDateString();
        $weekdayCount = $this->countWeekdays($fromDate, $toDate);

        $employees = Employee::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($managerUserId, fn ($q) => $q->where('manager_id', $managerUserId))
            ->get(['id', 'user_id', 'department']);

        if ($employees->isEmpty()) {
            return [
                'window_days' => $windowDays,
                'period' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                ],
                'scope' => [
                    'manager_user_id' => $managerUserId,
                ],
                'departments' => [],
            ];
        }

        $employeeByDept = $employees->groupBy(fn ($row) => $row->department ?: 'Unassigned');
        $userIds = $employees->pluck('user_id')->filter()->values();
        $employeeIdByUser = $employees->pluck('id', 'user_id');

        $attendanceRows = Attendance::whereIn('user_id', $userIds)
            ->whereBetween('date', [$fromDate, $toDate])
            ->get(['user_id', 'status']);

        $kpiRows = Kpi::whereIn('employee_id', $employees->pluck('id')->values())
            ->whereNotNull('score')
            ->get(['employee_id', 'score']);

        $departmentHealth = $employeeByDept->map(function (Collection $deptEmployees, string $deptName) use ($attendanceRows, $employeeIdByUser, $kpiRows, $weekdayCount) {
            $deptUserIds = $deptEmployees->pluck('user_id')->filter()->values();
            $deptEmployeeIds = $deptEmployees->pluck('id')->values();

            $attendanceInDept = $attendanceRows->whereIn('user_id', $deptUserIds);

            $attendanceCount = $attendanceInDept->count();
            $lateCount = $attendanceInDept->where('status', 'late')->count();
            $absentCount = $attendanceInDept->where('status', 'absent')->count();

            $expected = $deptEmployees->count() * $weekdayCount;
            $attendanceRate = $expected > 0 ? round(($attendanceCount / $expected) * 100, 2) : 0;
            $lateRate = $attendanceCount > 0 ? round(($lateCount / $attendanceCount) * 100, 2) : 0;

            $kpiInDept = $kpiRows->whereIn('employee_id', $deptEmployeeIds);
            $avgKpi = round((float) ($kpiInDept->avg('score') ?? 0), 2);

            $riskLabel = 'low';
            if ($attendanceRate < 80 || $lateRate > 25) {
                $riskLabel = 'high';
            } elseif ($attendanceRate < 90 || $lateRate > 15) {
                $riskLabel = 'medium';
            }

            return [
                'department' => $deptName,
                'employee_count' => $deptEmployees->count(),
                'attendance_rate_percent' => $attendanceRate,
                'late_rate_percent' => $lateRate,
                'late_count' => $lateCount,
                'absent_count' => $absentCount,
                'average_kpi_score' => $avgKpi,
                'risk_level' => $riskLabel,
            ];
        })->values()->all();

        return [
            'window_days' => $windowDays,
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'scope' => [
                'manager_user_id' => $managerUserId,
            ],
            'departments' => Arr::sortDesc($departmentHealth, fn ($row) => $row['risk_level'] === 'high' ? 2 : ($row['risk_level'] === 'medium' ? 1 : 0)),
        ];
    }

    public function buildEmployeeRiskDetail(int $userId, int $windowDays = 30, ?int $companyId = null): array
    {
        $windowDays = max(7, min(90, $windowDays));

        $employee = Employee::with('user:id,name,email')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('user_id', $userId)
            ->first();

        if (!$employee) {
            throw new ModelNotFoundException('Employee not found for this user.');
        }

        $toDate = now()->toDateString();
        $fromDate = now()->subDays($windowDays - 1)->toDateString();

        $attendanceRows = Attendance::where('user_id', $userId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->get(['id', 'date', 'status', 'check_in', 'check_out']);

        $lateCount = $attendanceRows->where('status', 'late')->count();
        $absentCount = $attendanceRows->where('status', 'absent')->count();
        $attendanceCount = $attendanceRows->count();

        $riskScore = min(100, ($lateCount * 15) + ($absentCount * 25));
        $riskLevel = $riskScore >= 60 ? 'high' : ($riskScore >= 35 ? 'medium' : 'low');

        $kpiAverage = round((float) (Kpi::where('employee_id', $employee->id)->whereNotNull('score')->avg('score') ?? 0), 2);
        $leavePending = Leave::where('employee_id', $employee->id)->where('status', 'pending')->count();
        $leaveRejected = Leave::where('employee_id', $employee->id)->where('status', 'rejected')->count();

        $reimbursementPending = Reimbursement::where('employee_id', $employee->id)
            ->where('status', Reimbursement::STATUS_SUBMITTED)
            ->count();

        $signals = [];

        if ($lateCount >= 3) {
            $signals[] = "High lateness pattern ({$lateCount} late records).";
        }
        if ($absentCount >= 2) {
            $signals[] = "High absence pattern ({$absentCount} absent records).";
        }
        if ($leaveRejected >= 2) {
            $signals[] = "Repeated leave rejection ({$leaveRejected} times).";
        }
        if ($kpiAverage > 0 && $kpiAverage < 70) {
            $signals[] = "KPI score below target threshold ({$kpiAverage}).";
        }
        if ($reimbursementPending >= 3) {
            $signals[] = "Reimbursement backlog detected ({$reimbursementPending} pending).";
        }

        if (empty($signals)) {
            $signals[] = 'No strong negative signal detected in current window.';
        }

        return [
            'window_days' => $windowDays,
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'employee' => [
                'employee_id' => $employee->id,
                'user_id' => $employee->user_id,
                'name' => $employee->user?->name,
                'email' => $employee->user?->email,
                'department' => $employee->department,
                'position' => $employee->position,
                'manager_user_id' => $employee->manager_id,
            ],
            'risk' => [
                'level' => $riskLevel,
                'score' => $riskScore,
                'late_count' => $lateCount,
                'absent_count' => $absentCount,
                'attendance_records' => $attendanceCount,
                'kpi_average_score' => $kpiAverage,
                'leave_pending_count' => $leavePending,
                'leave_rejected_count' => $leaveRejected,
                'reimbursement_pending_count' => $reimbursementPending,
                'signals' => $signals,
            ],
            'recent_attendance' => $attendanceRows
                ->sortByDesc('date')
                ->take(15)
                ->values()
                ->all(),
        ];
    }

    private function buildRiskAlerts(string $fromDate, string $toDate, Collection $userIds, Collection $employeeMap): Collection
    {
        $rows = Attendance::whereIn('user_id', $userIds)
            ->whereBetween('date', [$fromDate, $toDate])
            ->select('user_id')
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->groupBy('user_id')
            ->havingRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) >= 3 OR SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) >= 2")
            ->get();

        $userIds = $rows->pluck('user_id')->filter()->values();
        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'email'])->keyBy('id');

        return $rows->map(function ($row) use ($users, $employeeMap) {
            $lateCount = (int) $row->late_count;
            $absentCount = (int) $row->absent_count;

            $riskScore = min(100, ($lateCount * 15) + ($absentCount * 25));
            $riskLevel = $riskScore >= 60 ? 'high' : 'medium';

            $user = $users->get($row->user_id);

            return [
                'user_id' => (int) $row->user_id,
                'name' => $user?->name,
                'email' => $user?->email,
                'department' => $employeeMap->get($row->user_id)?->department,
                'manager_user_id' => $employeeMap->get($row->user_id)?->manager_id,
                'risk_level' => $riskLevel,
                'risk_score' => $riskScore,
                'late_count' => $lateCount,
                'absent_count' => $absentCount,
                'total_records' => (int) $row->total_records,
                'reason' => "{$lateCount}x late, {$absentCount}x absent in rolling period",
            ];
        })->sortByDesc('risk_score')->take(10)->values();
    }

    private function buildRecommendedActions(
        float $attendanceRate,
        float $lateRate,
        int $leavePending,
        int $reimbursementPendingCount
    ): array {
        $actions = [];

        if ($attendanceRate < 85) {
            $actions[] = 'Attendance rate below 85%. Run manager check-in and workload review this week.';
        }

        if ($lateRate > 20) {
            $actions[] = 'Late rate above 20%. Audit schedule suitability and commute/location constraints.';
        }

        if ($leavePending >= 10) {
            $actions[] = 'Pending leaves are high. Add temporary approver delegation to prevent SLA breach.';
        }

        if ($reimbursementPendingCount >= 10) {
            $actions[] = 'Pending reimbursements are high. Trigger finance follow-up and approval batching.';
        }

        if (empty($actions)) {
            $actions[] = 'Current people indicators are stable. Maintain weekly monitoring and manager coaching cadence.';
        }

        return $actions;
    }

    private function countWeekdays(string $fromDate, string $toDate): int
    {
        $count = 0;

        foreach (CarbonPeriod::create($fromDate, $toDate) as $date) {
            if (!$date->isWeekend()) {
                $count++;
            }
        }

        return $count;
    }

    private function resolveEmployeeScope(?string $department = null, ?int $managerUserId = null, ?int $companyId = null): array
    {
        $query = Employee::query();

        $query->when($companyId, fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('company_id', $companyId));

        if ($department) {
            $query->where('department', $department);
        }

        if ($managerUserId) {
            $query->where('manager_id', $managerUserId);
        }

        $employees = $query->get(['id', 'user_id', 'department', 'manager_id', 'status']);

        return [
            'employees' => $employees,
            'employee_ids' => $employees->pluck('id')->filter()->values(),
            'user_ids' => $employees->pluck('user_id')->filter()->values(),
            'employee_map' => $employees->keyBy('user_id'),
            'filters' => [
                'department' => $department,
                'manager_user_id' => $managerUserId,
            ],
        ];
    }
}

