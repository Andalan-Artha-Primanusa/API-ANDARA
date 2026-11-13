<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'address',
        'birth_date',
        'gender',
        'marital_status',
        'religion',
        'nationality',
        'id_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'current_address',
        'permanent_address',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'tax_number',
        'last_education',
        'institution_name',
        'graduation_year',
        'profile_photo_path',
        'custom_formula',
    ];

    /**
     * 🔥 SULTAN ACCESSORS
     * Resolves the conflict between 'profile_photo_path' in DB and 'avatar' in API.
     */
    protected $appends = ['avatar', 'avatar_url'];

    public function getAvatarAttribute(): ?string
    {
        return $this->profile_photo_path;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->profile_photo_path) {
            return 'https://ui-avatars.com/api/?name=' . urlencode($this->user?->name ?? 'User') . '&color=7F9CF5&background=EBF4FF';
        }

        if (filter_var($this->profile_photo_path, FILTER_VALIDATE_URL)) {
            return $this->profile_photo_path;
        }

        return asset('storage/' . $this->profile_photo_path);
    }

    /**
     * Evaluate custom formula (simple PHP eval, only for trusted input!)
     * Example: "(base_salary * 0.1) + 50000"
     * @param array $variables
     * @return float|int|null
     */
    public function evaluateCustomFormula(array $variables = [])
    {
        if (!$this->custom_formula) return null;
        extract($variables);
        try {
            // Only allow math expressions, never user input directly!
            // Example: $custom_formula = "(base_salary * 0.1) + 50000";
            return eval('return ' . $this->custom_formula . ';');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected $casts = [
        'birth_date' => 'date',
        'graduation_year' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Employee record linked to the same user.
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id', 'user_id');
    }

    /**
     * User roles through pivot table user_roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id', 'user_id', 'id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'user_id', 'user_id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class, 'user_id', 'user_id');
    }

    public function kpis(): HasManyThrough
    {
        return $this->hasManyThrough(
            Kpi::class,
            Employee::class,
            'user_id',
            'employee_id',
            'user_id',
            'id'
        );
    }

    public function reimbursements(): HasManyThrough
    {
        return $this->hasManyThrough(
            Reimbursement::class,
            Employee::class,
            'user_id',
            'employee_id',
            'user_id',
            'id'
        );
    }

    public function payrolls(): HasManyThrough
    {
        return $this->hasManyThrough(
            Payroll::class,
            Employee::class,
            'user_id',
            'employee_id',
            'user_id',
            'id'
        );
    }
}
