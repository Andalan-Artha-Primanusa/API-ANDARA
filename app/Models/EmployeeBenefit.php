<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBenefit extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'employee_id',
        'benefit_id',
        'effective_from',
        'effective_to',
        'custom_amount',
        'status',
        'notes',
        'assigned_by',
        'approval_flow_id',
        'current_step',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'custom_amount' => 'decimal:2',
        'current_step' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function approvalFlow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class);
    }
}
