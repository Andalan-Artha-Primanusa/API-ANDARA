<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardConfig extends Model
{
    protected $fillable = [
        'user_id',
        'role_id',
        'company_id',
        'name',
        'scope',
        'layout_json',
        'filters_json',
        'is_default',
    ];

    protected $casts = [
        'layout_json' => 'array',
        'filters_json' => 'array',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
