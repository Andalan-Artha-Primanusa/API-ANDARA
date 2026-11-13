<?php

namespace App\Modules\Reimbursement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReimbursementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'category' => 'required|string|in:travel,medical,office_supplies,training,meal,accommodation,transportation,other',
            'expense_date' => 'required|date|before_or_equal:today',
            'receipt_path' => 'nullable|string'
        ];

        // If the admin is creating for an employee
        if (request()->routeIs('*.store') && !request()->routeIs('*createMyReimbursement*')) {
            $rules['employee_id'] = 'required|exists:employees,id';
        }

        return $rules;
    }
}
