<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiItem extends Model
{
    protected $fillable = [
        'kpi_period_id',
        'indicator',
        'description',
        'category',
        'measurement_method',
        'formula_type',
        'weight',
        'target',
        'achievement',
        'score',
        'source',
        'status',
    ];

    protected $casts = [
        'target' => 'decimal:2',
        'achievement' => 'decimal:2',
        'score' => 'decimal:2',
        'weight' => 'integer',
    ];

    public const CATEGORIES = [
        'financial' => 'Financial',
        'customer' => 'Customer',
        'operational' => 'Operational',
        'employee' => 'Employee',
        'sales' => 'Sales',
        'project' => 'Project',
        'quality' => 'Quality',
        'compliance' => 'Compliance',
    ];

    public const FORMULAS = [
        'standard' => '(Achievement / Target) × 100',
        'growth' => '((Current - Previous) / Previous) × 100',
        'efficiency' => '(Output / Input) × 100',
        'quality' => '(Good Units / Total Units) × 100',
        'timeliness' => '(On-time / Total) × 100',
        'completion' => '(Completed / Total) × 100',
        'capped' => 'Min(Achievement / Target, 1) × 100',
        'manual' => 'Manual Input',
    ];

    public const MEASUREMENT_METHODS = [
        'direct' => 'Direct Value',
        'formula' => 'Formula Based',
        'survey' => 'Survey Result',
        'manual' => 'Manual Assessment',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function calculateScore(): float
    {
        if ($this->target <= 0) {
            $this->score = 0;
            return 0;
        }

        $this->score = match ($this->formula_type) {
            'capped' => min(($this->achievement / $this->target) * 100, 100),
            'growth' => $this->target > 0 ? (($this->achievement - $this->target) / abs($this->target)) * 100 : 0,
            default => ($this->achievement / $this->target) * 100,
        };

        $this->score = round(max($this->score, 0), 2);
        return $this->score;
    }
}
