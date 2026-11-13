<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_paid',
        'is_active'
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
    ];
}
