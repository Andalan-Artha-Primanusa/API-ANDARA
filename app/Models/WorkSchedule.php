<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Employee;

class WorkSchedule extends Model
{
    protected $fillable = [
        'name',
        'check_in_time',
        'grace_period',
        'check_out_time',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
