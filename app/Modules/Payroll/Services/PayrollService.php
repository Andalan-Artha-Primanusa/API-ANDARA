<?php

namespace App\Modules\Payroll\Services;

use App\Models\Attendance;
use App\Modules\Employee\Models\Employee;
use App\Models\Leave;
use App\Models\Payroll;
use App\Models\Reimbursement;
use App\Models\OvertimeRequest;
use App\Enums\LeaveStatus;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayrollService
{
    /**
     * Calculate and generate payroll for a single employee.
     *
     * @throws \DomainException
     */
    public function calculateAndCreate(
        Employee $employee,
        string $period,
        float $allowance = 0,
        float $bonus = 0
    ): Payroll {
        $gaji = (float) $employee->salary;

        if (!$gaji) {
            throw new \DomainException('Salary employee belum di set');
        }

        // â”€â”€ Earnings components â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $overtimePay        = $this->calculateOvertimePay($employee, $period, $gaji);
        $paidLeaveData      = $this->calculatePaidLeave($employee, $period, $gaji);
        $paidLeaveDays      = $paidLeaveData['days'];
        $paidLeaveAmount    = $paidLeaveData['amount'];
        $reimbData          = $this->calculateReimbursement($employee, $period);
        $reimbursementAmount = $reimbData['amount'];
        $reimbIds            = $reimbData['ids'];

        $bruto = $gaji + $allowance + $bonus + $overtimePay + $paidLeaveAmount + $reimbursementAmount;

        // â”€â”€ Deduction components â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $lateData      = $this->calculateLateDeduction($employee, $period, $gaji);
        $lateDays      = $lateData['days'];
        $lateDeduction = $lateData['deduction'];

        $bpjs_kesehatan       = $gaji * 0.01;
        $bpjs_ketenagakerjaan = $gaji * 0.03; // JHT 2% + JP 1%

        // PPh21 progresif (biaya jabatan 5%)
        $netto_tahun  = ($bruto * 12) * 0.95;
        $ptkp         = 54_000_000;
        $pkp          = max(0, $netto_tahun - $ptkp);

        if ($pkp <= 60_000_000) {
            $pph21_tahun = $pkp * 0.05;
        } else {
            $pph21_tahun = (60_000_000 * 0.05) + (($pkp - 60_000_000) * 0.15);
        }

        $pph21          = $pph21_tahun / 12;
        $total_deduction = $bpjs_kesehatan + $bpjs_ketenagakerjaan + $pph21 + $lateDeduction;
        $take_home_pay   = $bruto - $total_deduction;

        // â”€â”€ Persist â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        return DB::transaction(function () use (
            $employee, $period, $gaji, $allowance, $bonus,
            $overtimePay, $paidLeaveDays, $paidLeaveAmount,
            $lateDays, $lateDeduction,
            $reimbursementAmount, $reimbIds,
            $bpjs_kesehatan, $bpjs_ketenagakerjaan, $pph21,
            $total_deduction, $take_home_pay
        ) {
            $payroll = Payroll::create([
                'employee_id'          => $employee->id,
                'period'               => $period,
                'basic_salary'         => $gaji,
                'allowance'            => $allowance,
                'bonus'                => $bonus,
                'overtime_pay'         => $overtimePay,
                'paid_leave_days'      => $paidLeaveDays,
                'paid_leave_amount'    => $paidLeaveAmount,
                'late_days'            => $lateDays,
                'late_deduction'       => $lateDeduction,
                'reimbursement_amount' => $reimbursementAmount,
                'bpjs_kesehatan'       => $bpjs_kesehatan,
                'bpjs_ketenagakerjaan' => $bpjs_ketenagakerjaan,
                'pph21'                => $pph21,
                'total_deduction'      => $total_deduction,
                'take_home_pay'        => $take_home_pay,
                'status'               => 'draft',
            ]);

            // Link approved reimbursements to this payroll
            if (!empty($reimbIds)) {
                Reimbursement::whereIn('id', $reimbIds)
                    ->update(['payroll_id' => $payroll->id]);
            }

            return $payroll;
        });
    }

    /**
     * Generate payrolls for all ACTIVE employees for a given period.
     */
    public function generateMonthlyBulk(string $period, ?int $companyId = null): array
    {
        $employees = Employee::whereNotNull('salary')
            ->where('status', 'active')
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->get();

        $result = [];

        DB::beginTransaction();
        try {
            foreach ($employees as $employee) {
                $exists = Payroll::where('employee_id', $employee->id)
                    ->where('period', $period)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $payroll  = $this->calculateAndCreate($employee, $period);
                $result[] = $payroll;
            }

            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // PRIVATE CALCULATION HELPERS
    // =========================================================================

    /**
     * Calculate overtime pay from approved OvertimeRequests in the period.
     */
    private function calculateOvertimePay(Employee $employee, string $period, float $monthlySalary): float
    {
        [$year, $month] = explode('-', $period);
        $startDate = "{$year}-{$month}-01";
        $endDate   = date('Y-m-t', strtotime($startDate));

        $approvedOvertimes = OvertimeRequest::with('overtimeRule')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        if ($approvedOvertimes->isEmpty()) {
            return 0.0;
        }

        $hourlyRate = $monthlySalary / 22 / 8;
        $totalPay   = 0.0;

        foreach ($approvedOvertimes as $ot) {
            $multiplier  = $ot->overtimeRule?->multiplier ?? 1.5;
            $hours       = $ot->overtime_minutes / 60;
            $totalPay   += $hours * $hourlyRate * $multiplier;
        }

        return round($totalPay, 2);
    }

    /**
     * Calculate paid leave amount from approved paid-leave requests in the period.
     *
     * @return array{days: float, amount: float}
     */
    private function calculatePaidLeave(Employee $employee, string $period, float $monthlySalary): array
    {
        [$year, $month] = explode('-', $period);
        $startDate = "{$year}-{$month}-01";
        $endDate   = date('Y-m-t', strtotime($startDate));

        $approvedLeaves = Leave::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::Approved)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            })
            ->get()
            ->filter(fn(Leave $leave) => $leave->leaveType?->is_paid);

        if ($approvedLeaves->isEmpty()) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $periodStart = Carbon::parse($startDate);
        $periodEnd   = Carbon::parse($endDate);
        $totalDays   = 0;

        foreach ($approvedLeaves as $leave) {
            $leaveStart   = Carbon::parse($leave->start_date);
            $leaveEnd     = Carbon::parse($leave->end_date);
            $overlapStart = $leaveStart->max($periodStart);
            $overlapEnd   = $leaveEnd->min($periodEnd);

            if ($overlapStart <= $overlapEnd) {
                $totalDays += $overlapStart->diffInDays($overlapEnd) + 1;
            }
        }

        if ($totalDays === 0) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $dailyRate = $monthlySalary / 22;

        return [
            'days'   => $totalDays,
            'amount' => round($dailyRate * $totalDays, 2),
        ];
    }

    /**
     * Calculate late attendance deduction using per-minute rate formula.
     * Formula: total_late_minutes Ã— (monthly_salary / 22 / 8 / 60)
     *
     * @return array{days: int, deduction: float}
     */
    private function calculateLateDeduction(Employee $employee, string $period, float $monthlySalary): array
    {
        [$year, $month] = explode('-', $period);
        $startDate = "{$year}-{$month}-01";
        $endDate   = date('Y-m-t', strtotime($startDate));

        $employee->loadMissing('workSchedule');

        if (!$employee->user_id || !$employee->workSchedule) {
            return ['days' => 0, 'deduction' => 0.0];
        }

        $lateAttendances = Attendance::where('user_id', $employee->user_id)
            ->where('status', 'late')
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        if ($lateAttendances->isEmpty()) {
            return ['days' => 0, 'deduction' => 0.0];
        }

        $perMinuteRate    = $monthlySalary / 22 / 8 / 60;
        $totalLateMinutes = 0;
        $schedCheckIn     = $employee->workSchedule->check_in_time; // "08:00:00"

        foreach ($lateAttendances as $att) {
            if (!$att->check_in) {
                continue;
            }

            $scheduledCheckIn = Carbon::parse($att->date . ' ' . $schedCheckIn);
            $actualCheckIn    = Carbon::parse($att->check_in);

            $lateMinutes = $actualCheckIn->gt($scheduledCheckIn)
                ? (int) $scheduledCheckIn->diffInMinutes($actualCheckIn)
                : 0;

            $totalLateMinutes += $lateMinutes;
        }

        return [
            'days'      => $lateAttendances->count(),
            'deduction' => round($perMinuteRate * $totalLateMinutes, 2),
        ];
    }

    /**
     * Calculate reimbursement amount from approved reimbursements in the period
     * that have not yet been assigned to a payroll.
     *
     * @return array{amount: float, ids: int[]}
     */
    private function calculateReimbursement(Employee $employee, string $period): array
    {
        [$year, $month] = explode('-', $period);
        $startDate = "{$year}-{$month}-01";
        $endDate   = date('Y-m-t', strtotime($startDate));

        $reimbursements = Reimbursement::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereNull('payroll_id') // not yet included in any payroll
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        if ($reimbursements->isEmpty()) {
            return ['amount' => 0.0, 'ids' => []];
        }

        return [
            'amount' => round((float) $reimbursements->sum('amount'), 2),
            'ids'    => $reimbursements->pluck('id')->toArray(),
        ];
    }
}

