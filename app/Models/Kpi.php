<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kpi extends Model
{
    use HasFactory;

    protected $table = 'kpis';

    protected $fillable = [
        'employee_id',
        'title',
        'description',
        'target',
        'achievement',
        'score',
        'status',
        'period',
    ];

    /*
    |--------------------------------------------------------------------------
    | 🔗 RELATIONSHIP
    |--------------------------------------------------------------------------
    */

    // 🔥 KPI milik employee
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /*
    |--------------------------------------------------------------------------
    | 🔧 HELPER / LOGIC
    |--------------------------------------------------------------------------
    */

    // 🔒 cek kepemilikan KPI
    public function isOwnedBy($employeeId)
    {
        return $this->employee_id == $employeeId;
    }

    // 🔥 auto hitung score
    public function calculateScore()
    {
        if ($this->target > 0) {
            $this->score = ($this->achievement / $this->target) * 100;
        } else {
            $this->score = 0;
        }

        return $this->score;
    }
}