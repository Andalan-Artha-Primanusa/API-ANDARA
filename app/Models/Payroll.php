<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    protected $fillable = [
        'employee_id',
        'period',
        'basic_salary',
        'allowance',
        'bonus',
        'overtime_pay',
        'paid_leave_days',
        'paid_leave_amount',
        'late_days',
        'late_deduction',
        'reimbursement_amount',
        'bpjs_kesehatan',
        'bpjs_ketenagakerjaan',
        'pph21',
        'total_deduction',
        'take_home_pay',
        'status',
        // Audit fields
        'manager_approved_by',
        'manager_approved_at',
        'hr_approved_by',
        'hr_approved_at',
        'rejected_by',
        'rejected_reason',
    ];

    protected $casts = [
        'basic_salary'          => 'decimal:2',
        'allowance'             => 'decimal:2',
        'bonus'                 => 'decimal:2',
        'overtime_pay'          => 'decimal:2',
        'paid_leave_days'       => 'decimal:1',
        'paid_leave_amount'     => 'decimal:2',
        'late_days'             => 'integer',
        'late_deduction'        => 'decimal:2',
        'reimbursement_amount'  => 'decimal:2',
        'bpjs_kesehatan'        => 'decimal:2',
        'bpjs_ketenagakerjaan'  => 'decimal:2',
        'pph21'                 => 'decimal:2',
        'total_deduction'       => 'decimal:2',
        'take_home_pay'         => 'decimal:2',
        'manager_approved_at'   => 'datetime',
        'hr_approved_at'        => 'datetime',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayrollDetail::class);
    }

    public function reimbursements(): HasMany
    {
        return $this->hasMany(Reimbursement::class);
    }

    public function managerApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function hrApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['approved', 'paid']);
    }
}
