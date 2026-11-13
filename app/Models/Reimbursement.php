<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reimbursement extends Model
{
    use HasFactory;

    protected $table = 'reimbursements';

    protected $fillable = [
        'employee_id',
        'payroll_id',
        'title',
        'description',
        'amount',
        'category',
        'status',
        'expense_date',
        'submitted_at',
        'approved_at',
        'paid_at',
        'approved_by',
        'approval_note',
        'receipt_path',
        'approval_flow_id',
        'current_step',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'submitted_at' => 'date',
        'approved_at' => 'date',
        'paid_at' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | 🔗 RELATIONSHIP
    |--------------------------------------------------------------------------
    */

    // 🔥 Reimbursement milik employee
    public function employee()
    {
        return $this->belongsTo(Employee::class)->withDefault();
    }

    // 🔥 Linked payroll
    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    // 🔥 Approved by user
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by')->withDefault();
    }

    // 🔥 Approval flow
    public function approvalFlow()
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    /*
    |--------------------------------------------------------------------------
    | 🔧 HELPER / LOGIC
    |--------------------------------------------------------------------------
    */

    // 🔒 cek kepemilikan reimbursement
    public function isOwnedBy($employeeId)
    {
        return $this->employee_id == $employeeId;
    }

    // 🔥 Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PAID = 'paid';

    // 🔥 Category constants
    const CATEGORY_TRAVEL = 'travel';
    const CATEGORY_MEDICAL = 'medical';
    const CATEGORY_OFFICE_SUPPLIES = 'office_supplies';
    const CATEGORY_TRAINING = 'training';
    const CATEGORY_MEAL = 'meal';
    const CATEGORY_ACCOMMODATION = 'accommodation';
    const CATEGORY_TRANSPORTATION = 'transportation';
    const CATEGORY_OTHER = 'other';

    /*
    |--------------------------------------------------------------------------
    | 📊 STATUS METHODS
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /*
    |--------------------------------------------------------------------------
    | 🔄 WORKFLOW METHODS
    |--------------------------------------------------------------------------
    */

    public function submit()
    {
        $this->update([
            'status' => self::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function approve($userId, $note = null)
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_note' => $note,
        ]);
    }

    public function reject($userId, $note = null)
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_note' => $note,
        ]);
    }

    public function markAsPaid()
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 📈 STATISTICS METHODS
    |--------------------------------------------------------------------------
    */

    public static function getTotalByStatus($employeeId = null, $status = null)
    {
        $query = self::query();

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->sum('amount');
    }

    public static function getCountByStatus($employeeId = null, $status = null)
    {
        $query = self::query();

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->count();
    }
}