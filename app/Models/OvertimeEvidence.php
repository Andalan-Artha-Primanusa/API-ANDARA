<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class OvertimeEvidence extends Model
{
    protected $table = 'overtime_evidences';
    
    protected $fillable = [
        'overtime_request_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'file_mime',
        'file_size',
        'status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    protected $appends = [
        'file_url',
    ];

    public function overtimeRequest(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return URL::temporarySignedRoute('overtime.evidences.file', now()->addMinutes(15), [
            'id' => $this->id,
        ]);
    }
}
