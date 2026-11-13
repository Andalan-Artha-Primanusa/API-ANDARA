<?php

namespace App\Modules\Competency\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\User\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCompetency extends Model
{
    protected $fillable = [
        'employee_id',
        'competency_id',
        'proficiency_level',
        'assessed_by',
        'assessed_at',
        'notes',
        'status',
    ];

    protected $casts = [
        'assessed_at' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}

