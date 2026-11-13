<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingEnrollment extends Model
{
    protected $fillable = [
        'training_program_id',
        'employee_id',
        'status',
        'score',
        'certificate_path',
        'completed_at',
        'notes',
        'approval_flow_id',
        'current_step',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvalFlow()
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function progressHistories()
    {
        return $this->hasMany(TrainingProgressHistory::class, 'training_enrollment_id');
    }
}