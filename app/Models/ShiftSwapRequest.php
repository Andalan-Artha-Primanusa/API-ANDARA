<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSwapRequest extends Model
{
    protected $table = 'shift_swap_requests';

    protected $fillable = [
        'requester_employee_id',
        'target_employee_id',
        'swap_date',
        'status',
        'reason',
        'approved_by',
        'approved_at',
        'approval_flow_id',
        'current_step',
    ];

    protected $casts = [
        'swap_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'target_employee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvalFlow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }
}
