<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership check is done in the controller
        return true;
    }

    public function rules(): array
    {
        // Get profile ID from route parameter (apiResource uses 'profile' as parameter name)
        $profileId = $this->route('profile');
        
        // If not found, try common alternatives
        if (!$profileId) {
            $profileId = $this->route('id');
        }

        return [
            'phone'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'address'    => ['sometimes', 'nullable', 'string', 'max:500'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female,other'],
            'marital_status' => ['sometimes', 'nullable', 'string', 'in:single,married,divorced,widowed'],
            'religion' => ['sometimes', 'nullable', 'string', 'max:50'],
            'nationality' => ['sometimes', 'nullable', 'string', 'max:100'],
            'id_number' => ['sometimes', 'nullable', 'string', 'max:100', 'unique:user_profiles,id_number,' . ($profileId ?? 'NULL')],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['sometimes', 'nullable', 'string', 'max:100'],
            'current_address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'permanent_address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tax_number' => ['required', 'string', 'max:100'], // NPWP wajib diisi saat update
            'last_education' => ['sometimes', 'nullable', 'string', 'max:100'],
            'institution_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'graduation_year' => ['sometimes', 'nullable', 'integer', 'digits:4', 'min:1950', 'max:' . date('Y')],
            'profile_photo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
