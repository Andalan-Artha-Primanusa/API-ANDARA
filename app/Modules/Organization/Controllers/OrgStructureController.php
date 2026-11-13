<?php

namespace App\Modules\Organization\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Department;
use App\Modules\Employee\Models\Employee;
use App\Models\Location;
use App\Modules\Organization\Models\Position;
use App\Models\WorkSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgStructureController extends Controller
{
    private function canUseOrganization(Request $request, string $permission): bool
    {
        $user = $request->user();

        return $user && (
            $user->isAdmin()
            || $user->isHR()
            || $user->isManager()
            || $user->isSuperAdmin()
            || $user->hasPermission($permission)
        );
    }

    public function directory(Request $request): JsonResponse
    {
        if (!$this->canUseOrganization($request, 'organization.directory')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'search' => 'sometimes|string|max:255',
            'department' => 'sometimes|string|max:255',
            'position' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|max:50',
            'location_id' => 'sometimes|integer|exists:locations,id',
            'manager_user_id' => 'sometimes|integer|exists:users,id',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort' => 'sometimes|string|in:id,employee_code,department,position,status,hire_date',
            'order' => 'sometimes|string|in:asc,desc',
        ]);

        $query = Employee::with([
            'user:id,name,email',
            'user.profile:id,user_id,profile_photo_path',
            'department:id,name',
            'position:id,name',
            'location:id,name',
            'workSchedule:id,name,check_in_time,check_out_time',
            'manager:id,name',
            'manager.profile:id,user_id,profile_photo_path'
        ]);

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            })->orWhere('employee_code', 'like', '%' . $search . '%');
        }

        if (!empty($validated['department'])) {
            $query->where('department', $validated['department']);
        }

        if (!empty($validated['position'])) {
            $query->where('position', $validated['position']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['location_id'])) {
            $query->where('location_id', $validated['location_id']);
        }

        if (!empty($validated['manager_user_id'])) {
            $query->where('manager_id', $validated['manager_user_id']);
        }

        $sort = $validated['sort'] ?? 'id';
        $order = $validated['order'] ?? 'asc';

        $employees = $query->orderBy($sort, $order)
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();

        return ApiResponse::success('Employee directory retrieved successfully', $employees);
    }

    public function teamMembers(Request $request, int $managerUserId): JsonResponse
    {
        if (!$this->canUseOrganization($request, 'organization.team')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'department' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|max:50',
            'location_id' => 'sometimes|integer|exists:locations,id',
        ]);

        $query = Employee::with([
                'user:id,name,email',
                'user.profile:id,user_id,profile_photo_path',
                'department:id,name',
                'position:id,name',
                'location:id,name',
                'workSchedule:id,name,check_in_time,check_out_time',
                'manager:id,name',
                'manager.profile:id,user_id,profile_photo_path'
            ])
            ->where('manager_id', $managerUserId);

        if (!empty($validated['department'])) {
            $query->where('department', $validated['department']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['location_id'])) {
            $query->where('location_id', $validated['location_id']);
        }

        $teamMembers = $query->orderBy('department')->orderBy('position')->get();

        return ApiResponse::success('Team members retrieved successfully', [
            'manager_user_id' => $managerUserId,
            'count' => $teamMembers->count(),
            'members' => $teamMembers,
        ]);
    }

    public function orgChart(Request $request): JsonResponse
    {
        if (!$this->canUseOrganization($request, 'organization.chart')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'department' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|max:50',
            'location_id' => 'sometimes|integer|exists:locations,id',
        ]);

        $query = Employee::with([
            'user:id,name,email',
            'user.profile:id,user_id,profile_photo_path',
            'department:id,name',
            'position:id,name',
            'location:id,name',
            'workSchedule:id,name,check_in_time,check_out_time',
            'manager:id,name',
            'manager.profile:id,user_id,profile_photo_path'
        ]);

        if (!empty($validated['department'])) {
            $query->where('department', $validated['department']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['location_id'])) {
            $query->where('location_id', $validated['location_id']);
        }

        $employees = $query->get();
        $employeeIds = $employees->pluck('id')->all();

        $directReports = Employee::query()
            ->whereIn('manager_id', $employees->pluck('user_id')->all())
            ->select('manager_id')
            ->get()
            ->groupBy('manager_id')
            ->map->count();

        $nodes = $employees->map(function (Employee $employee) use ($directReports) {
            $displayName = $employee->user?->profile?->full_name
                ?? $employee->user?->name
                ?? 'Unknown';

            return [
                'id' => $employee->id,
                'user_id' => $employee->user_id,
                'name' => $displayName,
                'employee_code' => $employee->employee_code,
                'position' => $employee->position?->name ?? $employee->position,
                'department' => $employee->department?->name ?? $employee->department,
                'status' => $employee->status,
                'manager_user_id' => $employee->manager_id,
                'manager_name' => $employee->manager?->profile?->full_name
                    ?? $employee->manager?->name,
                'location' => $employee->location?->name,
                'work_schedule' => $employee->workSchedule?->name,
                'direct_reports_count' => (int) ($directReports[$employee->user_id] ?? 0),
            ];
        })->values();

        return ApiResponse::success('Organization chart retrieved successfully', [
            'filters' => $validated,
            'total_nodes' => $nodes->count(),
            'roots' => $nodes->whereNull('manager_user_id')->values(),
            'nodes' => $nodes,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        if (!$this->canUseOrganization($request, 'organization.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'department' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|max:50',
            'location_id' => 'sometimes|integer|exists:locations,id',
        ]);

        $query = Employee::with([
            'department:id,name',
            'position:id,name',
            'location:id,name',
            'workSchedule:id,name'
        ]);

        if (!empty($validated['department'])) {
            $query->where('department', $validated['department']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['location_id'])) {
            $query->where('location_id', $validated['location_id']);
        }

        $employees = $query->get();

        $departmentStats = $employees->groupBy(fn (Employee $employee) => $employee->department ?: 'Unassigned')
            ->map(fn ($group, $department) => [
                'department' => $department,
                'count' => $group->count(),
                'active_count' => $group->where('status', Employee::STATUS_ACTIVE)->count(),
            ])
            ->values();

        $positionStats = $employees->groupBy(fn (Employee $employee) => $employee->position ?: 'Unassigned')
            ->map(fn ($group, $position) => [
                'position' => $position,
                'count' => $group->count(),
            ])
            ->values();

        $statusStats = $employees->groupBy(fn (Employee $employee) => $employee->status ?: 'unknown')
            ->map(fn ($group, $status) => [
                'status' => $status,
                'count' => $group->count(),
            ])
            ->values();

        $locationStats = $employees->groupBy(fn (Employee $employee) => $employee->location?->name ?: 'Unassigned')
            ->map(fn ($group, $location) => [
                'location' => $location,
                'count' => $group->count(),
            ])
            ->values();

        $scheduleStats = $employees->groupBy(fn (Employee $employee) => $employee->workSchedule?->name ?: 'Unassigned')
            ->map(fn ($group, $schedule) => [
                'work_schedule' => $schedule,
                'count' => $group->count(),
            ])
            ->values();

        return ApiResponse::success('Organization summary retrieved successfully', [
            'filters' => $validated,
            'summary' => [
                'total_employees' => $employees->count(),
                'active_employees' => $employees->where('status', Employee::STATUS_ACTIVE)->count(),
                'inactive_employees' => $employees->where('status', Employee::STATUS_INACTIVE)->count(),
                'onboarding_employees' => $employees->where('status', Employee::STATUS_ONBOARDING)->count(),
                'probation_employees' => $employees->where('status', Employee::STATUS_PROBATION)->count(),
                'offboarding_employees' => $employees->where('status', Employee::STATUS_OFFBOARDING)->count(),
                'terminated_employees' => $employees->where('status', Employee::STATUS_TERMINATED)->count(),
            ],
            'by_department' => $departmentStats,
            'by_position' => $positionStats,
            'by_status' => $statusStats,
            'by_location' => $locationStats,
            'by_work_schedule' => $scheduleStats,
        ]);
    }

    public function masterData(Request $request): JsonResponse
    {
        if (!$this->canUseOrganization($request, 'organization.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        try {
            $departments = Department::orderBy('name')->get();
        } catch (\Exception) {
            $departments = Employee::query()
                ->select('department')
                ->whereNotNull('department')
                ->distinct()
                ->orderBy('department')
                ->pluck('department')
                ->values();
        }

        try {
            $positions = Position::orderBy('name')->get();
        } catch (\Exception) {
            $positions = Employee::query()
                ->select('position')
                ->whereNotNull('position')
                ->distinct()
                ->orderBy('position')
                ->pluck('position')
                ->values();
        }

        $statuses = [
            Employee::STATUS_ONBOARDING,
            Employee::STATUS_ACTIVE,
            Employee::STATUS_PROBATION,
            Employee::STATUS_OFFBOARDING,
            Employee::STATUS_INACTIVE,
            Employee::STATUS_TERMINATED,
        ];

        return ApiResponse::success('Master data retrieved successfully', [
            'departments' => $departments,
            'positions' => $positions,
            'statuses' => $statuses,
            'locations' => Location::orderBy('name')->get(),
            'work_schedules' => WorkSchedule::orderBy('name')->get(),
        ]);
    }
}

