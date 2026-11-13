<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    protected $fillable = [
        'user_id',
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'type',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'approval_note',
        'approval_flow_id',
        'current_step',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'approved_at' => 'datetime',
        'status'      => LeaveStatus::class,
    ];

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    const TYPE_ANNUAL = 'annual';
    const TYPE_SICK   = 'sick';
    const TYPE_UNPAID = 'unpaid';
    const TYPE_MARRIAGE = 'marriage';
    const TYPE_MATERNITY = 'maternity';
    const TYPE_PATERNITY = 'paternity';
    const TYPE_COMPASSIONATE = 'compassionate';
    const TYPE_SPECIAL = 'special';

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withDefault();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withDefault();
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(EmployeeLeaveBalance::class, 'employee_id', 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class)->withDefault();
    }

    // =========================================================================
    // STATUS HELPERS
    // =========================================================================

    public function isPending(): bool
    {
        return $this->status === LeaveStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === LeaveStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === LeaveStatus::Rejected;
    }

    // =========================================================================
    // HELPER CALCULATION
    // =========================================================================

    public static function calculateDays(string $start, string $end): int
    {
        return Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
    }

    // =========================================================================
    // APPROVAL ACTIONS
    // =========================================================================

    public function approve(int $userId, ?string $note = null): void
    {
        $this->update([
            'status'        => LeaveStatus::Approved,
            'approved_by'   => $userId,
            'approved_at'   => now(),
            'approval_note' => $note,
        ]);
    }

    public function reject(int $userId, ?string $note = null): void
    {
        $this->update([
            'status'        => LeaveStatus::Rejected,
            'approved_by'   => $userId,
            'approved_at'   => now(),
            'approval_note' => $note,
        ]);
    }
}
