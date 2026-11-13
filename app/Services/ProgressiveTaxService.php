<?php

namespace App\Services;

class ProgressiveTaxService
{
    /**
     * Hitung pajak progresif berdasarkan penghasilan bruto tahunan.
     *
     * @param float $annualIncome
     * @return array [tax, breakdown]
     */
    public function calculate(float $annualIncome): array
    {
        // Tarif pajak progresif Indonesia 2026 (asumsi, update jika ada perubahan)
        $brackets = [
            [0, 60000000, 0.05],
            [60000000, 250000000, 0.15],
            [250000000, 500000000, 0.25],
            [500000000, 5000000000, 0.30],
            [5000000000, INF, 0.35],
        ];
        $remaining = $annualIncome;
        $tax = 0;
        $breakdown = [];
        foreach ($brackets as [$min, $max, $rate]) {
            if ($annualIncome > $min) {
                $amount = min($remaining, $max - $min);
                $tierTax = $amount * $rate;
                $tax += $tierTax;
                $breakdown[] = [
                    'range' => [$min, $max],
                    'rate' => $rate,
                    'amount' => $amount,
                    'tax' => $tierTax,
                ];
                $remaining -= $amount;
                if ($remaining <= 0) break;
            }
        }
        return [
            'tax' => $tax,
            'breakdown' => $breakdown,
        ];
    }
}
