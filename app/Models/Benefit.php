<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\EmployeeBenefit;

class Benefit extends Model
{
    public const TYPE_ALLOWANCE = 'allowance';
    public const TYPE_INSURANCE = 'insurance';
    public const TYPE_REWARD = 'reward';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'code',
        'name',
        'type',
        'default_amount',
        'is_taxable',
        'is_active',
        'description',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function employeeBenefits(): HasMany
    {
        return $this->hasMany(EmployeeBenefit::class);
    }
}
