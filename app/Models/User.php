<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Role name constants — hanya super_admin yg di-hardcode sebagai special case.
     * Untuk role lainnya, gunakan permission-based checking.
     */
    const ROLE_SUPER_ADMIN = 'super_admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
        'password_changed_at',
    ];

    /**
     * 🔥 SULTAN ACCESSORS
     */
    protected $appends = ['display_role', 'is_onboarded'];

    public function getDisplayRoleAttribute(): string
    {
        // Use the first role name or default to Guest
        $role = $this->roles->first()?->name ?? 'Guest';
        return ucwords(str_replace(['_', '-'], ' ', $role));
    }

    public function getIsOnboardedAttribute(): bool
    {
        // A user is considered onboarded if they have both profile and employee record
        if ($this->relationLoaded('profile') && $this->relationLoaded('employee')) {
            return $this->profile !== null && $this->employee !== null;
        }
        
        return $this->profile()->exists() && $this->employee()->exists();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Employees managed by this user (via employees.manager_id → users.id).
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function sentNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'sender_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function companyAccesses(): HasMany
    {
        return $this->hasMany(UserCompanyAccess::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_company_access')
            ->withPivot(['scope_role', 'is_default'])
            ->withTimestamps();
    }

    // =========================================================================
    // ROLE CHECKS (all pivot-based — reads from user_roles table)
    // =========================================================================

    /**
     * Check if the user has a specific role via the pivot table.
     * Uses eager-loaded data when available to avoid N+1 queries.
     */
    public function hasRole(string $roleName): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('name', $roleName);
        }

        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if the user has any of the given roles.
     */
    public function hasAnyRole(array $roleNames): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->whereIn('name', $roleNames)->isNotEmpty();
        }

        return $this->roles()->whereIn('name', $roleNames)->exists();
    }

    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $this->loadMissing('roles.permissions');

        $userPermNames = $this->roles
            ->flatMap(fn($role) => $role->permissions)
            ->pluck('name')
            ->toArray();

        return !empty(array_intersect($permissions, $userPermNames));
    }

    public function isEmployee(): bool
    {
        return $this->hasAnyPermission(config('rbac.level_indicators.employee', []));
    }

    public function isManager(): bool
    {
        return $this->isSuperAdmin() || $this->hasAnyPermission(config('rbac.level_indicators.manager', []));
    }

    public function isHR(): bool
    {
        return $this->isSuperAdmin() || $this->hasAnyPermission(config('rbac.level_indicators.hr', []));
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->hasAnyPermission(config('rbac.level_indicators.admin', []));
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    // =========================================================================
    // PERMISSION CHECK
    // =========================================================================

    /**
     * Check if the user has a specific permission through any of their roles.
     * Super admin always has all permissions.
     * Leverages eager-loaded relations to avoid N+1 queries.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn($role) => $role->permissions)
            ->contains('name', $permission);
    }
}
