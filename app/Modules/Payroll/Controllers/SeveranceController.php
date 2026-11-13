<?php

namespace App\Modules\Payroll\Controllers;

use App\Helpers\ApiResponse;
use App\Modules\Employee\Models\Employee;
use App\Services\SeveranceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

class SeveranceController
{
    public function calculate(Request $request, int $employeeId): JsonResponse
    {
        $query = Employee::with('user.profile');
        app(\App\Services\CompanyScopeService::class)->applyEmployeeScope($query, $request);
        $employee = $query->findOrFail($employeeId);
        $terminationDate = $request->input('termination_date') ?? $employee->termination_date;
        $hireDate = $employee->hire_date;
        if (!$hireDate || !$terminationDate) {
            return ApiResponse::error('Tanggal masuk dan tanggal keluar harus diisi', null, 422);
        }
        $masaKerjaBulan = Date::parse($hireDate)->diffInMonths(Date::parse($terminationDate));
        $service = new SeveranceService();
        $result = $service->hitungPesangon($employee, $masaKerjaBulan);
        return ApiResponse::success('Perhitungan pesangon PP 35/2021', [
            'employee' => $employee,
            'masa_kerja_bulan' => $masaKerjaBulan,
            'pesangon' => $result
        ]);
    }

    public function exportExcel(Request $request, int $employeeId)
    {
        $query = Employee::with('user.profile');
        app(\App\Services\CompanyScopeService::class)->applyEmployeeScope($query, $request);
        $employee = $query->findOrFail($employeeId);
        $terminationDate = $request->input('termination_date') ?? $employee->termination_date;
        $hireDate = $employee->hire_date;
        if (!$hireDate || !$terminationDate) {
            return ApiResponse::error('Tanggal masuk dan tanggal keluar harus diisi', null, 422);
        }
        $masaKerjaBulan = Date::parse($hireDate)->diffInMonths(Date::parse($terminationDate));
        $service = new SeveranceService();
        $result = $service->hitungPesangon($employee, $masaKerjaBulan);
        $filename = 'pesangon-pp35-employee-' . $employee->id . '.csv';
        $rows = [
            ['Nama', $employee->user->name],
            ['NIK', $employee->employee_code],
            ['Tanggal Masuk', $hireDate],
            ['Tanggal Keluar', $terminationDate],
            ['Masa Kerja (bulan)', $masaKerjaBulan],
            ['Gaji Pokok', $employee->salary],
            ['Pesangon', $result['pesangon']],
            ['Uang Penghargaan', $result['uang_penghargaan']],
            ['Uang Penggantian', $result['uang_penggantian']],
            ['Total', $result['total']],
        ];
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

