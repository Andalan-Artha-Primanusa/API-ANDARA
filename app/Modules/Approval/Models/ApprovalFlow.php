<?php

namespace App\Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalFlow extends Model
{
    protected $fillable = [
        'name',
        'module',
        'company_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // 🔥 relasi ke steps
    public function steps()
    {
        return $this->hasMany(ApprovalStep::class)
            ->orderBy('step_order');
    }
}
