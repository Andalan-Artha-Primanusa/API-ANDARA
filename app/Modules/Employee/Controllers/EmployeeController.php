<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\UserNotification;
use App\Services\EmployeeService;
use App\Services\CompanyScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService,
        protected CompanyScopeService $companyScope
    ) {}

    /**
     * Temporary read-only bridge for the legacy SQL Server employee table.
     * This route is public only while the frontend is running without a token.
     */
    public function legacyIndex(Request $request): JsonResponse
    {
        $query = DB::connection()->table('ms_hr_employee')->select([
            'Employee_ID',
            'Salary_Number',
            'CompleteName',
            'NickName',
            'PlaceOfBirth',
            'DateOfBirth',
            'Religion',
            'Citizenship',
            'Sex',
            'MaritalStatus',
            'BloodGroup',
            'Height',
            'Weight',
            'CellPhoneNumber',
            'EmailAddress',
            'InputLocation',
            'ProjectID',
            'ServerID',
            'PlaceOfApp',
            'InputDate',
            'LastModifyAt',
            'status_employee',
            'status_hire',
            'tgl_hire',
            'tgl_resign',
            'tgl_AktifKerja',
            'UserID',
            'PositionRef',
            'PositionDesc',
            'Department',
        ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($search) {
                $like = '%' . $search . '%';
                $builder->where('Employee_ID', 'like', $like)
                    ->orWhere('CompleteName', 'like', $like)
                    ->orWhere('EmailAddress', 'like', $like)
                    ->orWhere('Department', 'like', $like)
                    ->orWhere('PositionDesc', 'like', $like);
            });
        }

        if ($request->filled('status_employee')) {
            $query->where('status_employee', $request->query('status_employee'));
        }

        if ($request->filled('department')) {
            $query->where('Department', $request->query('department'));
        }

        if ($request->filled('position')) {
            $query->where('PositionDesc', $request->query('position'));
        }

        if ($request->filled('status')) {
            $status = strtolower(trim((string) $request->query('status')));
            if ($status === 'active') {
                $query->whereRaw("LOWER(LTRIM(RTRIM(status_employee))) LIKE '%aktif%'")
                    ->whereRaw("LOWER(LTRIM(RTRIM(status_employee))) NOT LIKE '%non%'");
            } elseif (in_array($status, ['inactive', 'resigned'], true)) {
                $query->whereRaw("LOWER(LTRIM(RTRIM(status_employee))) LIKE '%non%'");
            } elseif ($status === 'pending') {
                $query->whereRaw("LOWER(LTRIM(RTRIM(status_employee))) LIKE '%probation%'");
            }
        }

        $query->orderBy('CompleteName');
        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $results = $query->paginate($perPage);
        $results->getCollection()->transform(fn ($employee) => $this->normalizeLegacyEmployee($employee));

        return ApiResponse::success('Legacy employees', $results);
    }

    /** Aggregate counts for the legacy employee directory cards. */
    public function legacySummary(): JsonResponse
    {
        $row = DB::connection()->selectOne(<<<'SQL'
            SELECT
                COUNT(*) AS total,
                SUM(CASE
                    WHEN LOWER(LTRIM(RTRIM(status_employee))) LIKE '%aktif%'
                     AND LOWER(LTRIM(RTRIM(status_employee))) NOT LIKE '%non%'
                    THEN 1 ELSE 0 END) AS active,
                COUNT(DISTINCT NULLIF(LTRIM(RTRIM(Department)), '')) AS departments,
                COUNT(DISTINCT NULLIF(LTRIM(RTRIM(PositionDesc)), '')) AS positions
            FROM ms_hr_employee
            SQL);

        $departmentBreakdown = DB::connection()->table('ms_hr_employee')
            ->selectRaw("NULLIF(LTRIM(RTRIM(Department)), '') AS name, COUNT(*) AS count")
            ->whereNotNull('Department')
            ->whereRaw("NULLIF(LTRIM(RTRIM(Department)), '') IS NOT NULL")
            ->groupByRaw("NULLIF(LTRIM(RTRIM(Department)), '')")
            ->orderByDesc('count')
            ->get()
            ->map(fn ($item) => [
                'name' => (string) $item->name,
                'count' => (int) $item->count,
            ])
            ->values()
            ->all();

        return ApiResponse::success('Legacy employee summary', [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
            'departments' => (int) ($row->departments ?? 0),
            'positions' => (int) ($row->positions ?? 0),
            'department_breakdown' => $departmentBreakdown,
        ]);
    }

    /**
     * Read one employee from the legacy SQL Server table.
     */
    public function legacyShow(string $employee): JsonResponse
    {
        $record = DB::connection()->table('ms_hr_employee')
            ->where('Employee_ID', $employee)
            ->first();

        if (!$record) {
            return ApiResponse::error('Employee not found', null, 404);
        }

        $detail = DB::connection()->table('ms_hr_employee_detail')
            ->where('Employee_ID', $employee)
            ->first();

        return ApiResponse::success('Legacy employee detail', [
            'employee' => $this->normalizeLegacyEmployee($record),
            'employee_detail' => $detail ? (array) $detail : [],
        ]);
    }

    private function normalizeLegacyEmployee(object $employee): array
    {
        $data = (array) $employee;
        $status = strtolower(trim((string) ($employee->status_employee ?? '')));

        return array_merge($data, [
            'id' => $employee->Employee_ID,
            'user_id' => $employee->UserID,
            'employee_code' => $employee->Employee_ID,
            'position' => $employee->PositionDesc,
            'department' => $employee->Department,
            'hire_date' => $employee->tgl_hire,
            'termination_date' => $employee->tgl_resign,
            'status' => str_contains($status, 'aktif') && !str_contains($status, 'non')
                ? 'active'
                : 'inactive',
            'salary' => null,
            'user' => [
                'id' => $employee->UserID,
                'name' => $employee->CompleteName,
                'email' => $employee->EmailAddress,
            ],
            'detail' => isset($employee->detail_Grade) || isset($employee->detail_KelompokKaryawan)
                ? [
                    'kode_pos' => $employee->detail_KodePos,
                    'grade' => $employee->detail_Grade,
                    'tanggal_grade' => $employee->detail_TglGrade,
                    'tanggal_masuk_group' => $employee->detail_TglMasukGroup,
                    'kelompok_karyawan' => $employee->detail_KelompokKaryawan,
                    'cost_center' => $employee->detail_CostCenter,
                    'cost_center_text' => $employee->detail_CostCenterText,
                    'atasan_langsung' => $employee->detail_AtasanLangsung,
                ]
                : null,
        ]);
    }

    /**
     * List employees with filtering, search, and sorting.
     * Authorization order: admin/HR → manager → employee (most privileged first).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Admin/HR — full list with filtering
        if ($user->hasPermission('employee.view')) {
            $data = $this->employeeService->getFilteredList($request);
            return ApiResponse::success('All employees', $data);
        }

        // Employee — own data only (fallback)
        if (!$user->employee) {
            return ApiResponse::error(
                'Employee record not found',
                'No employee data available for this user',
                404
            );
        }

        return ApiResponse::success('Own employee data', $user->employee->load([
            'user:id,name,email',
            'user.profile:id,user_id,profile_photo_path,phone,address,gender',
            'departmentRel:id,name',
            'positionRel:id,name',
            'location:id,name',
            'workSchedule:id,name,check_in_time,check_out_time',
            'manager:id,name',
            'manager.profile:id,user_id,profile_photo_path'
        ]));
    }

    /**
     * Create a new employee.
     * Authorization handled by StoreEmployeeRequest.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            if (empty($data['company_id'])) {
                $data['company_id'] = $this->companyScope->selectedCompanyId($request);
            }

            $employee = $this->employeeService->create($data);
            return ApiResponse::success('Employee created successfully', $employee, 201);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }

    /**
     * Show employee detail.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $employee = $this->employeeService->findWithUser($id);

        // Non-owner must have employee.view permission
        if ($employee->user_id !== $user->id && !$user->hasPermission('employee.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        if (!$this->canAccessEmployeeCompany($request, $employee)) {
            return ApiResponse::error('Forbidden', 'Cannot access employee from another company', 403);
        }

        return ApiResponse::success('Employee detail', $employee);
    }

    /**
     * Update employee data.
     * Authorization handled by UpdateEmployeeRequest.
     */
    public function update(UpdateEmployeeRequest $request, $id): JsonResponse
    {
        try {
            $employee = $this->employeeService->findWithUser($id);

            if (!$this->canAccessEmployeeCompany($request, $employee)) {
                return ApiResponse::error('Forbidden', 'Cannot update employee from another company', 403);
            }

            $employee = $this->employeeService->update($id, $request->validated());
            return ApiResponse::success('Employee updated successfully', $employee);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }

    /**
     * Delete an employee.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('employee.delete')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $employee = $this->employeeService->findWithUser($id);

        if (!$this->canAccessEmployeeCompany($request, $employee)) {
            return ApiResponse::error('Forbidden', 'Cannot delete employee from another company', 403);
        }

        $deleted = $employee->toArray();

        $this->employeeService->delete($id);

        return ApiResponse::success('Employee deleted successfully', $deleted);
    }

    /**
     * Start employee onboarding.
     */
    public function startOnboarding(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('employee.update')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $employee = $this->employeeService->findWithUser($id);

        $employee->update([
            'status' => \App\Models\Employee::STATUS_ONBOARDING,
            'probation_end_date' => $validated['probation_end_date'] ?? null,
            'termination_date' => null,
            'termination_reason' => null,
        ]);

        UserNotification::create([
            'user_id' => $employee->user_id,
            'sender_user_id' => $request->user()->id,
            'title' => 'Onboarding started',
            'message' => 'Your onboarding process has been started. Please complete the required steps.',
            'type' => 'employee.onboarding.started',
            'category' => 'employee_lifecycle',
            'data' => [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'status' => $employee->status,
            ],
        ]);

        return ApiResponse::success('Employee onboarding started successfully', $employee->fresh([
            'user:id,name,email',
            'user.profile:id,user_id,profile_photo_path,phone,address,gender',
            'departmentRel:id,name',
            'positionRel:id,name',
            'location:id,name',
            'workSchedule:id,name,check_in_time,check_out_time',
            'manager:id,name',
            'manager.profile:id,user_id,profile_photo_path'
        ]));
    }

    protected function canAccessEmployeeCompany(Request $request, $employee): bool
    {
        $user = $request->user();

        if ($employee->user_id === $user->id || $this->companyScope->canViewAll($user)) {
            return true;
        }

        if (!$employee->company_id) {
            return false;
        }

        $selectedCompanyId = $this->companyScope->selectedCompanyId($request);

        if ($selectedCompanyId) {
            return (int) $employee->company_id === $selectedCompanyId;
        }

        return $this->companyScope->availableCompanyIds($user)->contains((int) $employee->company_id);
    }

    /**
     * Mark employee as active after onboarding.
     */
    public function completeOnboarding(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('employee.update')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $employee = $this->employeeService->findWithUser($id);

        $employee->update([
            'status' => \App\Models\Employee::STATUS_ACTIVE,
        ]);

        UserNotification::create([
            'user_id' => $employee->user_id,
            'sender_user_id' => $request->user()->id,
            'title' => 'Onboarding completed',
            'message' => 'Your onboarding has been completed. Your employee account is now active.',
            'type' => 'employee.onboarding.completed',
            'category' => 'employee_lifecycle',
            'data' => [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'status' => $employee->status,
            ],
        ]);

        return ApiResponse::success('Employee onboarding completed successfully', $employee->fresh([
            'user:id,name,email',
            'user.profile:id,user_id,profile_photo_path,phone,address,gender',
            'departmentRel:id,name',
            'positionRel:id,name',
            'location:id,name',
            'workSchedule:id,name,check_in_time,check_out_time',
            'manager:id,name',
            'manager.profile:id,user_id,profile_photo_path'
        ]));
    }

    /**
     * Start employee offboarding.
     */
    public function offboard(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('employee.update')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'termination_date' => ['required', 'date'],
            'termination_reason' => ['required', 'string', 'max:5000'],
        ]);

        $employee = $this->employeeService->findWithUser($id);

        $employee->update([
            'status' => \App\Models\Employee::STATUS_OFFBOARDING,
            'termination_date' => $validated['termination_date'],
            'termination_reason' => $validated['termination_reason'],
        ]);

        UserNotification::create([
            'user_id' => $employee->user_id,
            'sender_user_id' => $request->user()->id,
            'title' => 'Offboarding started',
            'message' => 'Your offboarding process has been started. Please follow the clearance checklist.',
            'type' => 'employee.offboarding.started',
            'category' => 'employee_lifecycle',
            'data' => [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'termination_date' => $employee->termination_date,
            ],
        ]);

        return ApiResponse::success('Employee offboarding started successfully', $employee->fresh([
            'user:id,name,email',
            'user.profile:id,user_id,profile_photo_path,phone,address,gender',
            'departmentRel:id,name',
            'positionRel:id,name',
            'location:id,name',
            'workSchedule:id,name,check_in_time,check_out_time',
            'manager:id,name',
            'manager.profile:id,user_id,profile_photo_path'
        ]));
    }

    /**
     * Mark employee as terminated/inactive after clearance.
     */
    public function completeOffboarding(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('employee.update')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:inactive,terminated'],
        ]);

        $employee = $this->employeeService->findWithUser($id);

        $employee->update([
            'status' => $validated['status'] ?? \App\Models\Employee::STATUS_TERMINATED,
        ]);

        UserNotification::create([
            'user_id' => $employee->user_id,
            'sender_user_id' => $request->user()->id,
            'title' => 'Offboarding completed',
            'message' => 'Your offboarding has been completed and your employee account status has been updated.',
            'type' => 'employee.offboarding.completed',
            'category' => 'employee_lifecycle',
            'data' => [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'status' => $employee->status,
            ],
        ]);

        return ApiResponse::success('Employee offboarding completed successfully', $employee->fresh([
            'user:id,name,email',
            'user.profile:id,user_id,profile_photo_path,phone,address,gender',
            'departmentRel:id,name',
            'positionRel:id,name',
            'location:id,name',
            'workSchedule:id,name,check_in_time,check_out_time',
            'manager:id,name',
            'manager.profile:id,user_id,profile_photo_path'
        ]));
    }
}
