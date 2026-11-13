<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('leave.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'start_date'    => ['required', 'date', 'after_or_equal:today'],
            'end_date'      => ['required', 'date', 'after_or_equal:start_date'],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'type'          => ['nullable', 'string', 'max:50'],
            'reason'        => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->all();
            if (empty($data['leave_type_id']) && empty($data['type'])) {
                $validator->errors()->add('leave_type_id', 'Either leave type or type field is required.');
            }
        });
    }
}
