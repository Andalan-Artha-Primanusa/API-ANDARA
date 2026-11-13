<?php

namespace App\Modules\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('employee.create');
    }

    public function rules(): array
    {
        return [
            'user_id'       => ['required', 'exists:users,id', 'unique:employees,user_id'],
            'company_id'    => ['nullable', 'exists:companies,id'],
            'manager_id'    => ['nullable', 'exists:users,id'],
            'employee_code' => ['required', 'string', 'unique:employees,employee_code'],
            'position'      => ['required', 'string', 'max:255'],
            'department'    => ['required', 'string', 'max:255'],
            'status'        => ['sometimes', 'string', 'in:onboarding,active,probation,offboarding,inactive,terminated'],
            'hire_date'     => ['nullable', 'date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'salary'        => ['nullable', 'numeric', 'min:0'],
            'location_id' => ['required', 'exists:locations,id'],
            'work_schedule_id' => ['required', 'exists:work_schedules,id'],
        ];
    }
}
