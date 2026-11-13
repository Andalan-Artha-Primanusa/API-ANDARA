<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Location;
use App\Models\WorkSchedule;
use App\Models\AssetAssignment;
use App\Models\EmployeeDocument;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'manager_id',
        'employee_code',
        'position',
        'department',
        'position_id',
        'department_id',
        'status',
        'hire_date',
        'probation_end_date',
        'termination_date',
        'termination_reason',
        'salary',
        'location_id',
        'work_schedule_id',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'probation_end_date' => 'date',
        'termination_date' => 'date',
        'salary'    => 'decimal:2', // Matches migration decimal(12,2)
    ];

    protected $appends = ['has_letter'];

    public function getHasLetterAttribute(): bool
    {
        if ($this->relationLoaded('documents')) {
            return $this->documents->where('category', 'letter')->isNotEmpty();
        }
        
        return ($this->letter_count ?? 0) > 0;
    }

    public const STATUS_ONBOARDING = 'onboarding';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PROBATION = 'probation';
    public const STATUS_OFFBOARDING = 'offboarding';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_TERMINATED = 'terminated';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    /**
     * The manager (User) of this employee.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id')->withDefault();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function departmentRel(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function positionRel(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function department(): BelongsTo
    {
        return $this->departmentRel();
    }

    public function position(): BelongsTo
    {
        return $this->positionRel();
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function assetAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }


    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function shiftSwapRequests(): HasMany
    {
        return $this->hasMany(\App\Models\ShiftSwapRequest::class, 'requester_employee_id');
    }

    public function shiftSwapTargets(): HasMany
    {
        return $this->hasMany(\App\Models\ShiftSwapRequest::class, 'target_employee_id');
    }
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
