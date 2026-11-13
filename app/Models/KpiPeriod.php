<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiPeriod extends Model
{
    protected $fillable = [
        'employee_id',
        'period_type',
        'period_label',
        'start_date',
        'end_date',
        'overall_score',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'overall_score' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KpiItem::class, 'kpi_period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function calculateOverallScore(): void
    {
        $items = $this->items()->where('status', 'approved')->get();
        $totalWeight = $items->sum('weight');

        if ($totalWeight > 0) {
            $weightedSum = $items->sum(fn($i) => $i->score * $i->weight);
            $this->overall_score = round($weightedSum / $totalWeight, 2);
        } else {
            $this->overall_score = 0;
        }
    }

    public static function generateLabel(string $periodType, string $date): string
    {
        $year = date('Y', strtotime($date));
        return match ($periodType) {
            'quarterly' => 'Q' . ceil(date('n', strtotime($date)) / 3) . " {$year}",
            'semi_annual' => (date('n', strtotime($date)) <= 6 ? 'H1' : 'H2') . " {$year}",
            'annual' => (string) $year,
            default => $year,
        };
    }

    public static function getDateRange(string $periodType, string $periodDate): array
    {
        $year = date('Y', strtotime($periodDate));
        $month = (int) date('n', strtotime($periodDate));

        return match ($periodType) {
            'quarterly' => [
                'start' => date('Y-m-d', strtotime($year . '-' . (floor(($month - 1) / 3) * 3 + 1) . '-01')),
                'end' => date('Y-m-t', strtotime($year . '-' . (floor(($month - 1) / 3) * 3 + 3) . '-01')),
            ],
            'semi_annual' => [
                'start' => $month <= 6 ? "{$year}-01-01" : "{$year}-07-01",
                'end' => $month <= 6 ? "{$year}-06-30" : "{$year}-12-31",
            ],
            'annual' => [
                'start' => "{$year}-01-01",
                'end' => "{$year}-12-31",
            ],
            default => [
                'start' => date('Y-m-d', strtotime($periodDate)),
                'end' => date('Y-m-d', strtotime($periodDate)),
            ],
        };
    }
}
