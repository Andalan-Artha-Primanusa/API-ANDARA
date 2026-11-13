<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'employee_id',
        'uploaded_by',
        'reviewed_by',
        'title',
        'document_type',
        'category',
        'status',
        'file_name',
        'file_path',
        'file_mime',
        'file_size',
        'expires_at',
        'reviewed_at',
        'review_notes',
        'is_confidential',
        'approval_flow_id',
        'current_step',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'reviewed_at' => 'datetime',
        'is_confidential' => 'boolean',
        'file_size' => 'integer',
    ];

    protected $appends = [
        'file_url',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvalFlow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->file_path);
    }
}
