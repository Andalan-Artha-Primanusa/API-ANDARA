<?php

namespace App\Services;

use App\Modules\Employee\Models\Employee;
use App\Models\Company;
use App\Models\WorkSchedule;
use App\Models\Location;
use App\Services\CompanyScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class EmployeeService
{
    public function __construct(
        protected CompanyScopeService $companyScope
    ) {}

    /**
     * Get a filtered, searched, and sorted employee list.
     */
    public function getFilteredList(Request $request): LengthAwarePaginator
    {
        $query = Employee::with([
            'user:id,name,email',
            'user.profile:id,user_id',
            'company:id,name,code,status',
            'departmentRel:id,name',
            'positionRel:id,name',
            'location:id,name',
            'workSchedule:id,name,check_in_time,check_out_time',
            'manager:id,name',
            'manager.profile:id,user_id'
        ])->withCount(['documents as letter_count' => function ($q) {
            $q->where('category', 'letter');
        }]);

        $this->companyScope->applyEmployeeScope($query, $request);

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        // Search by name or email through user relation
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sort with whitelist
        $allowedSort = ['id', 'department', 'position', 'salary', 'hire_date', 'employee_code'];
        $sort = in_array($request->get('sort'), $allowedSort)
            ? $request->get('sort')
            : 'id';
        $order = $request->get('order') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $order);

        $perPage = $request->integer('per_page', 10);
        return $query->paginate($perPage);
    }

    /**
     * Find an employee with its user relation.
     */
    public function findWithUser(int|string $id): Employee
    {
        return Employee::with([
            'user:id,name,email',
            'user.profile:id,user_id,phone,address,gender',
            'company:id,name,code,status',
            'departmentRel:id,name',
            'positionRel:id,name',
            'location:id,name',
            'workSchedule:id,name,check_in_time,check_out_time',
            'manager:id,name',
            'manager.profile:id,user_id'
        ])->withCount(['documents as letter_count' => function ($q) {
            $q->where('category', 'letter');
        }])->findOrFail($id);
    }

    /**
     * Create a new employee record.
     * Validates that work_schedule_id and location_id exist.
     */
    public function create(array $data): Employee
    {
        // Validate work schedule
        if (isset($data['company_id']) && $data['company_id']) {
            if (!Company::find($data['company_id'])) {
                throw new \DomainException('Company not found with ID: ' . $data['company_id']);
            }
        }

        if (isset($data['work_schedule_id']) && $data['work_schedule_id']) {
            if (!WorkSchedule::find($data['work_schedule_id'])) {
                throw new \DomainException('Work schedule not found with ID: ' . $data['work_schedule_id']);
            }
        }

        // Validate location
        if (isset($data['location_id']) && $data['location_id']) {
            if (!Location::find($data['location_id'])) {
                throw new \DomainException('Location not found with ID: ' . $data['location_id']);
            }
        }

        return Employee::create($data)->load([
            'user:id,name,email',
            'user.profile:id,user_id',
            'company:id,name,code,status',
            'departmentRel:id,name',
            'positionRel:id,name',
            'location:id,name',
            'workSchedule:id,name,check_in_time,check_out_time',
            'manager:id,name',
            'manager.profile:id,user_id'
        ]);
    }

    /**
     * Update an existing employee record.
     * Validates that work_schedule_id and location_id exist.
     */
    public function update(int|string $id, array $data): Employee
    {
        // Validate work schedule
        if (isset($data['company_id']) && $data['company_id']) {
            if (!Company::find($data['company_id'])) {
                throw new \DomainException('Company not found with ID: ' . $data['company_id']);
            }
        }

        if (isset($data['work_schedule_id']) && $data['work_schedule_id']) {
            if (!WorkSchedule::find($data['work_schedule_id'])) {
                throw new \DomainException('Work schedule not found with ID: ' . $data['work_schedule_id']);
            }
        }

        // Validate location
        if (isset($data['location_id']) && $data['location_id']) {
            if (!Location::find($data['location_id'])) {
                throw new \DomainException('Location not found with ID: ' . $data['location_id']);
            }
        }

        $employee = Employee::findOrFail($id);
        $employee->update($data);

        return $employee->fresh([
            'user:id,name,email',
            'user.profile:id,user_id',
            'company:id,name,code,status',
            'departmentRel:id,name',
            'positionRel:id,name',
            'location:id,name',
            'workSchedule:id,name,check_in_time,check_out_time',
            'manager:id,name',
            'manager.profile:id,user_id'
        ]);
    }

    /**
     * Delete an employee record.
     */
    public function delete(int|string $id): void
    {
        Employee::findOrFail($id)->delete();
    }
}

