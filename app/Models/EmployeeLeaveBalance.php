<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveBalance extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_policy_id',
        'year',
        'leave_type',
        'allocated_days',
        'carry_over_days',
        'used_days',
        'pending_days',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }

    public function availableDays(): int
    {
        return max(0, ($this->allocated_days + $this->carry_over_days) - $this->used_days - $this->pending_days);
    }
}