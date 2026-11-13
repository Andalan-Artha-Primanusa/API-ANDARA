<?php

namespace App\Modules\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('employee.update');
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee') ?? $this->route('id');

        return [
            'name'          => ['sometimes', 'string', 'max:255'],
            'user_id'       => ['sometimes', 'nullable', 'exists:users,id'],
            'company_id'    => ['sometimes', 'nullable', 'exists:companies,id'],
            'employee_code' => [
                'sometimes', 'string',
                Rule::unique('employees', 'employee_code')->ignore($employeeId),
            ],
            'manager_id'    => ['sometimes', 'nullable', 'exists:users,id'],
            'position'      => ['sometimes', 'string', 'max:255'],
            'department'    => ['sometimes', 'string', 'max:255'],
            'status'        => ['sometimes', 'string', 'in:onboarding,active,probation,offboarding,inactive,terminated'],
            'hire_date'     => ['sometimes', 'date'],
            'probation_end_date' => ['sometimes', 'nullable', 'date'],
            'termination_date' => ['sometimes', 'nullable', 'date'],
            'termination_reason' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'salary'        => ['sometimes', 'numeric', 'min:0'],
            'location_id' => ['sometimes', 'exists:locations,id'],
            'work_schedule_id' => ['sometimes', 'exists:work_schedules,id'],
        ];
    }
}
