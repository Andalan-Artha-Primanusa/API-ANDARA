<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLifecycleEvent extends Model
{
    protected $fillable = [
        'employee_id',
        'event_type',
        'event_date',
        'from_value',
        'to_value',
        'reason',
        'supporting_documents',
        'initiated_by_id',
        'approved_by_id',
        'approval_date',
        'effective_date',
        'status',
        'remarks',
        'activity_report',
        'report_status',
        'report_approved_by_id',
        'report_approved_at',
        'report_rejection_reason',
        'approval_flow_id',
        'current_step',
    ];

    protected $casts = [
        'event_date' => 'date',
        'approval_date' => 'date',
        'effective_date' => 'date',
        'supporting_documents' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'initiated_by_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function reportApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'report_approved_by_id');
    }

    public function approvalFlow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }
}
