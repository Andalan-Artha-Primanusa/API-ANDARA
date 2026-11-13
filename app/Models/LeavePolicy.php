<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeavePolicy extends Model
{
    protected $fillable = [
        'name',
        'policy_code',
        'entitlement_type',
        'entitlement_value',
        'max_carryover_days',
        'is_paid',
        'year',
        'annual_allowance',
        'carry_over_allowance',
        'max_pending_days',
        'active',
        'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_paid' => 'boolean',
    ];
}