<?php

namespace App\Traits;

use App\Modules\Employee\Models\Employee;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\ApiResponse;

trait HasEmployee
{
    /**
     * Get the authenticated user's associated Employee record.
     *
     * @return Employee
     * @throws HttpResponseException
     */
    protected function getAuthenticatedEmployee(): Employee
    {
        $user = auth()->user();

        if (!$user) {
            throw new HttpResponseException(
                ApiResponse::error('Unauthenticated.', null, 401)
            );
        }

        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            throw new HttpResponseException(
                ApiResponse::error('Forbidden: User is not an employee', null, 403)
            );
        }

        return $employee;
    }
}

