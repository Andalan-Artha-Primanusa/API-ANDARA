<?php

namespace App\Services;

use Carbon\Carbon;
use App\Modules\Employee\Models\Employee;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\Payroll;
use App\Models\TrainingProgram;
use App\Models\EmployeeCompetency;
use App\Models\Asset;
use App\Models\AssetAssignment;

class ReportingService
{
    public function getDashboardSummary(?array $filters = null)
    {
        $filters = $filters ?? [];

        return [
            'headcount' => $this->getHeadcountMetrics($filters),
            'attendance' => $this->getAttendanceSummary(),
            'leave' => $this->getLeaveSummary($filters),
            'payroll' => $this->getPayrollSummary($filters),
            'training' => $this->getTrainingSummary(),
            'assets' => $this->getAssetSummary(),
        ];
    }

    public function getAttendanceAnalytics(?array $filters = null)
    {
        $filters = $filters ?? [];

        $startDate = isset($filters['start_date'])
            ? Carbon::parse($filters['start_date'])
            : Carbon::now()->startOfMonth();

        $endDate = isset($filters['end_date'])
            ? Carbon::parse($filters['end_date'])
            : Carbon::now()->endOfMonth();

        $attendanceData = Attendance::whereBetween('date', [$startDate, $endDate])
            ->with(['user.employee', 'user.profile'])
            ->get();

        $activeEmployeeCount = Employee::active()->count();

        // Some parts of the app use 'on_time' while reporting expects 'present'.
        // Treat both as present for analytics.
        $presentStatuses = ['present', 'on_time'];

        $summary = [
            'total_working_days' => $this->calculateWorkingDays($startDate, $endDate),
            'present_count' => $attendanceData->whereIn('status', $presentStatuses)->count(),
            'absent_count' => $attendanceData->where('status', 'absent')->count(),
            'late_count' => $attendanceData->where('status', 'late')->count(),
            'permission_count' => $attendanceData->where('status', 'permission')->count(),
        ];

        $summary['attendance_rate'] =
            $summary['total_working_days'] > 0 && $activeEmployeeCount > 0
                ? round(
                    ($summary['present_count'] /
                        ($summary['total_working_days'] * $activeEmployeeCount)) * 100,
                    2
                )
                : 0;

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => $summary,
            'by_employee' => $this->getAttendanceByEmployee($attendanceData),
            'trends' => $this->getAttendanceTrends($startDate, $endDate),
        ];
    }

    public function getLeaveAnalytics(?array $filters = null)
    {
        $filters = $filters ?? [];
        $year = $filters['year'] ?? Carbon::now()->year;

        $leaves = Leave::where('status', 'approved')
            ->whereYear('start_date', $year)
            ->with(['employee.user.profile'])
            ->get();

        return [
            'year' => $year,
            'summary' => [
                'total_leaves_taken' => $leaves->count(),
                'total_days_used' => $leaves->sum('duration'),
                'by_type' => $leaves->groupBy('type')->map(function ($group) {
                    return [
                        'type' => $group->first()->type ?? 'Unknown',
                        'count' => $group->count(),
                        'days' => $group->sum('duration'),
                    ];
                })->values(),
            ],
            'by_employee' => $this->getLeaveByEmployee($leaves),
            'pending' => Leave::where('status', 'pending')->count(),
        ];
    }

    public function getPayrollAnalytics(?array $filters = null)
    {
        $filters = $filters ?? [];

        $startDate = isset($filters['start_date'])
            ? Carbon::parse($filters['start_date'])
            : Carbon::now()->startOfMonth();

        $endDate = isset($filters['end_date'])
            ? Carbon::parse($filters['end_date'])
            : Carbon::now()->endOfMonth();

        $payrolls = Payroll::whereBetween('period', [$startDate, $endDate])
            ->with(['employee.user.profile'])
            ->get();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_payroll_count' => $payrolls->count(),
                'total_salary' => $payrolls->sum('basic_salary'),
                'total_allowance' => $payrolls->sum('allowance'),
                'total_deduction' => $payrolls->sum('total_deduction'),
                'total_bonus' => $payrolls->sum('bonus'),
                'total_net_pay' => $payrolls->sum('take_home_pay'),
                'average_salary' => $payrolls->avg('take_home_pay') ?? 0,
            ],
            'by_status' => $payrolls->groupBy('status')->map(fn($group) => [
                'status' => $group->first()->status,
                'count' => $group->count(),
                'total' => $group->sum('take_home_pay'),
            ])->values(),
        ];
    }

    public function getCompetencyAnalytics()
    {
        $data = EmployeeCompetency::with(['employee', 'competency'])->get();

        return [
            'competencies' => [
                'total_assignments' => $data->count(),
                'unique_employees' => $data->groupBy('employee_id')->count(),
                'top_competencies' => $data->groupBy('competency_id')
                    ->map(fn($group) => [
                        'name' => $group->first()->competency->name ?? 'Unknown',
                        'count' => $group->count(),
                    ])
                    ->sortByDesc('count')
                    ->take(10)
                    ->values(),
            ],
            'training' => [
                'total_programs' => TrainingProgram::count(),
                'completed' => TrainingProgram::where('status', 'completed')->count(),
            ],
        ];
    }

    public function getAssetAnalytics()
    {
        $assets = Asset::with('assignments')->get();
        $assigned = AssetAssignment::where('status', 'active')->count();

        return [
            'total_assets' => $assets->count(),
            'assigned_assets' => $assigned,
            'available_assets' => $assets->count() - $assigned,
        ];
    }

    private function getHeadcountMetrics(?array $filters = null): array
    {
        $query = Employee::query();

        if (!empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        $all = $query->get();
        $active = (clone $query)->active()->get();

        return [
            'total' => $all->count(),
            'active' => $active->count(),
            'inactive' => $all->count() - $active->count(),
        ];
    }

    private function getAttendanceSummary(): array
    {
        $today = Carbon::today();
        $data = Attendance::whereDate('date', $today)->get();

        $presentStatuses = ['present', 'on_time'];

        return [
            'present' => $data->whereIn('status', $presentStatuses)->count(),
            'absent' => $data->where('status', 'absent')->count(),
            'late' => $data->where('status', 'late')->count(),
            'permission' => $data->where('status', 'permission')->count(),
        ];
    }

    private function getLeaveSummary(?array $filters = null): array
    {
        $year = $filters['year'] ?? Carbon::now()->year;

        return [
            'approved' => Leave::where('status', 'approved')->whereYear('start_date', $year)->count(),
            'pending' => Leave::where('status', 'pending')->count(),
            'rejected' => Leave::where('status', 'rejected')->whereYear('start_date', $year)->count(),
        ];
    }

    private function getPayrollSummary(?array $filters = null): array
    {
        $month = $filters['month'] ?? Carbon::now()->month;
        $year = $filters['year'] ?? Carbon::now()->year;

        $data = Payroll::whereMonth('period', $month)
            ->whereYear('period', $year)
            ->get();

        return [
            'total_amount' => $data->sum('take_home_pay'),
            'approved' => $data->where('status', 'approved')->count(),
            'paid' => $data->where('status', 'paid')->count(),
            'pending' => $data->where('status', 'draft')->count(),
        ];
    }

    private function getTrainingSummary(): array
    {
        return [
            'total_programs' => TrainingProgram::count(),
            'active' => TrainingProgram::where('status', 'active')->count(),
            'completed' => TrainingProgram::where('status', 'completed')->count(),
        ];
    }

    private function getAssetSummary(): array
    {
        $total = Asset::count();
        $assigned = AssetAssignment::where('status', 'active')->count();

        return [
            'total' => $total,
            'assigned' => $assigned,
            'available' => $total - $assigned,
        ];
    }

    private function calculateWorkingDays(Carbon $start, Carbon $end): int
    {
        $days = 0;

        while ($start <= $end) {
            if (!in_array($start->dayOfWeek, [0, 6])) {
                $days++;
            }
            $start->addDay();
        }

        return $days;
    }

    private function getAttendanceByEmployee($data): array
    {
        return $data->groupBy('user_id')->map(function ($records) {

            $user = $records->first()->user;
            $employee = $user?->employee;
            $profile = $user?->profile;

            $presentStatuses = ['present', 'on_time'];

            return [
                'employee_id' => $employee?->id,
                'name' => $profile?->full_name ?? $user?->name ?? 'Unknown',
                'present' => $records->whereIn('status', $presentStatuses)->count(),
                'absent' => $records->where('status', 'absent')->count(),
                'late' => $records->where('status', 'late')->count(),
                'permission' => $records->where('status', 'permission')->count(),
            ];
        })->values()->toArray();
    }

    private function getAttendanceTrends(Carbon $start, Carbon $end): array
    {
        $trends = [];

        while ($start <= $end) {
            $data = Attendance::whereDate('date', $start)->get();

            $presentStatuses = ['present', 'on_time'];

            $trends[] = [
                'date' => $start->toDateString(),
                'present' => $data->whereIn('status', $presentStatuses)->count(),
                'absent' => $data->where('status', 'absent')->count(),
            ];

            $start->addDay();
        }

        return $trends;
    }

    private function getLeaveByEmployee($leaves): array
    {
        return $leaves->groupBy('employee_id')->map(function ($records) {

            $employee = $records->first()->employee;

            return [
                'employee_id' => $employee?->id,
                'name' => $employee?->user?->profile?->full_name
                    ?? $employee?->user?->name
                    ?? 'Unknown',
                'total_days' => $records->sum('duration'),
                'count' => $records->count(),
            ];
        })->values()->toArray();
    }

    public function getEmployeeLifecycleAnalytics(?array $filters = null)
{
    $filters = $filters ?? [];
    $year = $filters['year'] ?? Carbon::now()->year;

    $employees = Employee::whereYear('created_at', $year)->get();

    return [
        'year' => $year,
        'new_hires' => $employees->count(),

        'by_department' => $employees->groupBy('department')
            ->map(function ($group) {
                return [
                    'department' => $group->first()->department ?? 'Unknown',
                    'count' => $group->count(),
                ];
            })->values(),

        'by_position' => $employees->groupBy('position')
            ->map(function ($group) {
                return [
                    'position' => $group->first()->position ?? 'Unknown',
                    'count' => $group->count(),
                ];
            })->values(),
    ];
}
}

