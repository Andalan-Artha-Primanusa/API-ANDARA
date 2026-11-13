<?php

namespace App\Modules\Attendance\Models;

use App\Modules\User\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $appends = [
        'employee',
        'employee_name',
        'employee_id',
        'department_name',
        'position_name',
    ];

    protected $fillable = [
        'user_id',
        'company_id',
        'qr_token_id',
        'location_id',
        'date',
        'check_in',
        'check_out',
        'latitude',
        'longitude',
        'status',
        'validation_status',
    ];

    protected $casts = [
        'date'      => 'date',
        'check_in'  => 'datetime',
        'check_out' => 'datetime',
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
        'status'    => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getEmployeeNameAttribute(): ?string
    {
        return $this->user?->name;
    }

    public function getEmployeeIdAttribute(): int|string|null
    {
        return $this->user?->employee?->id ?? $this->user_id;
    }

    public function getDepartmentNameAttribute(): ?string
    {
        $department = $this->user?->employee?->department;

        if (is_object($department) && isset($department->name)) {
            return $department->name;
        }

        return is_string($department) && $department !== '' ? $department : null;
    }

    public function getPositionNameAttribute(): ?string
    {
        $position = $this->user?->employee?->position;

        if (is_object($position) && isset($position->name)) {
            return $position->name;
        }

        return is_string($position) && $position !== '' ? $position : null;
    }

    public function getEmployeeAttribute(): ?array
    {
        $user = $this->user;

        if (!$user) {
            return null;
        }

        $employee = $user->employee;
        $department = $employee?->department;
        $position = $employee?->position;

        return [
            'id' => $employee?->id,
            'employee_code' => $employee?->employee_code,
            'full_name' => $user->name,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'department' => is_object($department) ? [
                'id' => $department->id ?? null,
                'name' => $department->name ?? null,
            ] : null,
            'department_name' => $this->department_name,
            'position' => is_object($position) ? [
                'id' => $position->id ?? null,
                'name' => $position->name ?? null,
            ] : null,
            'position_name' => $this->position_name,
        ];
    }
}

