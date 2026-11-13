<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimeRule extends Model
{
    protected $table = 'overtime_rules';

    protected $fillable = [
        'name',
        'department',
        'location_id',
        'min_minutes',
        'multiplier',
        'requires_approval',
        'active',
    ];

    protected $casts = [
        'min_minutes' => 'integer',
        'multiplier' => 'float',
        'requires_approval' => 'boolean',
        'active' => 'boolean',
    ];

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class, 'overtime_rule_id');
    }
}
