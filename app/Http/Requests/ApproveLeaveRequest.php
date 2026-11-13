<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('leave.approve');
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:approved,rejected'],
        ];
    }
}
