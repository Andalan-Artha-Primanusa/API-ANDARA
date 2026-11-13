<?php

namespace App\Services;

use App\Modules\Employee\Models\Employee;

class SeveranceService
{
    /**
     * Hitung pesangon sesuai PP 35/2021 (sederhana, bisa dikembangkan sesuai kebutuhan)
     *
     * @param Employee $employee
     * @param int $masaKerjaBulan
     * @return array
     */
    public function hitungPesangon(Employee $employee, int $masaKerjaBulan): array
    {
        $gaji = (float) $employee->salary;
        $masaKerjaTahun = $masaKerjaBulan / 12;
        $pesangon = 0;
        $uangPenghargaan = 0;
        $uangPenggantian = 0;

        // Pesangon utama (PP 35/2021 Pasal 40)
        if ($masaKerjaTahun < 1) {
            $pesangon = 1 * $gaji;
        } elseif ($masaKerjaTahun < 2) {
            $pesangon = 2 * $gaji;
        } elseif ($masaKerjaTahun < 3) {
            $pesangon = 3 * $gaji;
        } elseif ($masaKerjaTahun < 4) {
            $pesangon = 4 * $gaji;
        } elseif ($masaKerjaTahun < 5) {
            $pesangon = 5 * $gaji;
        } elseif ($masaKerjaTahun < 6) {
            $pesangon = 6 * $gaji;
        } elseif ($masaKerjaTahun < 7) {
            $pesangon = 7 * $gaji;
        } else {
            $pesangon = 8 * $gaji;
        }

        // Uang Penghargaan Masa Kerja (PP 35/2021 Pasal 40 ayat 3)
        if ($masaKerjaTahun >= 3 && $masaKerjaTahun < 6) {
            $uangPenghargaan = 2 * $gaji;
        } elseif ($masaKerjaTahun >= 6 && $masaKerjaTahun < 9) {
            $uangPenghargaan = 3 * $gaji;
        } elseif ($masaKerjaTahun >= 9) {
            $uangPenghargaan = 4 * $gaji;
        }

        // Uang Penggantian Hak (misal: cuti belum diambil, dsb, diisi manual)
        $uangPenggantian = 0; // Default, bisa diisi dari input

        return [
            'pesangon' => $pesangon,
            'uang_penghargaan' => $uangPenghargaan,
            'uang_penggantian' => $uangPenggantian,
            'total' => $pesangon + $uangPenghargaan + $uangPenggantian,
        ];
    }
}

