<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityPatrolCheckpoint extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'area',
        'floor',
        'room',
        'qr_code',
        'starts_at',
        'ends_at',
        'tolerance_minutes',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(SecurityPatrolScan::class, 'checkpoint_id');
    }
}
