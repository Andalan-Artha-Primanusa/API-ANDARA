<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityPatrolScan extends Model
{
    protected $fillable = [
        'checkpoint_id',
        'user_id',
        'employee_id',
        'company_id',
        'scanned_at',
        'latitude',
        'longitude',
        'status',
        'notes',
        'metadata_json',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'metadata_json' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(SecurityPatrolCheckpoint::class, 'checkpoint_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
