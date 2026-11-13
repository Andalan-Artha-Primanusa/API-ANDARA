<?php

namespace App\Modules\Attendance\Services;

use App\Models\Attendance;
use App\Modules\User\Models\User;
use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AttendanceService
{
    /**
     * Process a check-in request with geofencing validation.
     *
     * Uses unique constraint on [user_id, date] to prevent race-condition duplicates.
     *
     * @throws \DomainException for business rule violations
     * @return array{attendance: Attendance, location: string, distance: int}
     */
    public function checkIn(User $user, float $latitude, float $longitude): array
    {

        $existing = Attendance::where('user_id', $user->id)
        ->where('date', now()->toDateString())
        ->first();

        if ($existing) {
            throw new \DomainException('Already checked in today.');
        }
        // ðŸ”¥ LOAD RELATION (ANTI N+1)
        $user->loadMissing('employee.location', 'employee.workSchedule');

        $employee = $user->employee;

        if (!$employee) {
            throw new \DomainException('Employee data not found.');
        }

        // =========================
        // ðŸ”¥ VALIDASI LOCATION
        // =========================
        if (!$employee->location) {
            throw new \DomainException('No assigned location for this employee.');
        }

        $location = $employee->location;

        $distance = $this->haversineDistance(
            $location->latitude,
            $location->longitude,
            $latitude,
            $longitude
        );

        if ($distance > $location->radius) {
            throw new \DomainException(
                'Outside your assigned location area. Distance: ' . round($distance) . ' m'
            );
        }

        // =========================
        // ðŸ”¥ VALIDASI SCHEDULE
        // =========================
        if (!$employee->workSchedule) {
            throw new \DomainException('No work schedule assigned.');
        }

        $schedule = $employee->workSchedule;

        $now = now();
        $checkInTime = now()->setTimeFromTimeString($schedule->check_in_time);
        $graceLimit = $checkInTime->copy()->addMinutes($schedule->grace_period);

        // =========================
        // ðŸ”¥ STATUS LOGIC (INI YANG BARU)
        // =========================
        $status = 'on_time';

        if ($now->gt($checkInTime) && $now->lte($graceLimit)) {
            $status = 'late';
        }

        if ($now->gt($graceLimit)) {
            $status = 'absent';
        }

        // =========================
        // CREATE ATTENDANCE
        // =========================
        try {
            $attendance = Attendance::create([
                'user_id'   => $user->id,
                'date'      => now()->toDateString(),
                'check_in'  => now(),
                'latitude'  => $latitude,
                'longitude' => $longitude,
                'status'    => $status, // ðŸ”¥ TAMBAHAN PENTING
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062) {
                throw new \DomainException('Already checked in today.');
            }
            throw $e;
        }

        return [
            'attendance' => $attendance->load('user.profile'),
            'location'   => $location->name,
            'distance'   => round($distance),
        ];
    }

    /**
     * Process a check-out request.
     *
     * @throws \DomainException if not checked in or already checked out
     */
    public function checkOut(User $user): array
    {

        $user->loadMissing('employee.workSchedule'); // ðŸ”¥ TAMBAH INI

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        if (!$attendance) {
            throw new \DomainException('Not checked in today.');
        }

        if ($attendance->check_out) {
            throw new \DomainException('Already checked out.');
        }

        $employee = $user->employee;

        if (!$employee || !$employee->workSchedule) {
            throw new \DomainException('No work schedule assigned.');
        }

        $schedule = $employee->workSchedule;

        $now = now();
        $checkOutTime = now()->setTimeFromTimeString($schedule->check_out_time);

        if ($now->lt($checkOutTime)) {
        throw new \DomainException('Cannot check out before minimum time.');
        }

        $attendance->update(['check_out' => now()]);

        // ðŸ”¥ AUTO-CREATE OVERTIME REQUEST
        $overtimeRequest = null;
        
        // Parse scheduled checkout time from today
        $scheduledCheckoutTime = \Carbon\Carbon::parse($schedule->check_out_time, config('app.timezone'));
        $actualCheckoutTime = now();

        $overtimeSeconds = $scheduledCheckoutTime->diffInSeconds($actualCheckoutTime, false);

        if ($overtimeSeconds > 0) {
            $overtimeMinutes = (int) ceil($overtimeSeconds / 60);
            
            \Log::info('OVERTIME DETECTED', [
                'employee_id' => $employee->id,
                'scheduled_time' => $schedule->check_out_time,
                'actual_time' => $actualCheckoutTime->format('H:i:s'),
                'overtime_minutes' => $overtimeMinutes,
            ]);

            if ($overtimeMinutes >= 1) {
                try {
                    $overtimeRequest = OvertimeRequest::create([
                        'employee_id' => $employee->id,
                        'attendance_id' => $attendance->id,
                        'date' => $attendance->date,
                        'scheduled_checkout' => $schedule->check_out_time,
                        'actual_checkout' => $actualCheckoutTime->format('H:i:s'),
                        'overtime_minutes' => $overtimeMinutes,
                        'status' => 'pending',
                    ]);

                    try {
                        $approvalService = app(ApprovalFlowService::class);
                        $approvalService->applyToModel('overtime', $overtimeRequest);
                    } catch (\RuntimeException $e) {
                        // No approval flow configured, continue without flow
                    }

                    \Log::info('âœ… OVERTIME CREATED', ['id' => $overtimeRequest->id, 'minutes' => $overtimeMinutes]);
                } catch (\Exception $e) {
                    \Log::error('âŒ OVERTIME ERROR', ['error' => $e->getMessage()]);
                }
            }
        }

        return [
            'attendance' => $attendance->fresh(['user.profile']),
            'overtime_request' => $overtimeRequest,
        ];
    }

    /**
     * Get attendance history for a specific user.
     */
    public function getHistory(User $user): LengthAwarePaginator
    {
        return Attendance::with('user.profile')
            ->with(['user.employee.department', 'user.employee.position'])
            ->where('user_id', $user->id)
            ->latest('date')
            ->paginate(request()->integer('per_page', 10));
    }

    /**
     * Get today's attendance record for a user.
     */
    public function getToday(User $user): ?Attendance
    {
        return Attendance::with(['user.profile', 'user.employee.department', 'user.employee.position'])
            ->where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();
    }

    /**
     * Get all attendance records (admin view).
     */
    public function getAll(): LengthAwarePaginator
    {
        return Attendance::with(['user.profile', 'user.employee.department', 'user.employee.position'])
            ->latest('date')
            ->paginate(request()->integer('per_page', 10));
    }

    /**
     * Get attendance intelligence summary for the authenticated user's employee record.
     */
    public function getMyIntelligence(User $user, int $days = 30): array
    {
        $user->loadMissing('employee.workSchedule');

        if (!$user->employee || !$user->employee->workSchedule) {
            return [
                'present_days' => 0,
                'total_working_hours' => 0,
                'overtime_hours' => 0,
                'overtime_minutes' => 0,
                'late_count' => 0,
                'early_checkout_count' => 0,
                'records' => []
            ];
        }

        $schedule = $user->employee->workSchedule;

        $fromDate = now()->subDays(max(1, $days - 1))->startOfDay();
        $records = Attendance::where('user_id', $user->id)
            ->with(['user.profile', 'user.employee.department', 'user.employee.position'])
            ->where('date', '>=', $fromDate->toDateString())
            ->orderBy('date')
            ->get();

        return $this->buildAttendanceInsight($records, $schedule->check_out_time);
    }

    /**
     * Get overtime report for the authenticated user's employee record.
     */
    public function getMyOvertime(User $user, int $days = 30): array
    {
        $summary = $this->getMyIntelligence($user, $days);

        return [
            'window_days' => $days,
            'overtime_hours' => $summary['overtime_hours'],
            'overtime_minutes' => $summary['overtime_minutes'],
            'early_checkout_count' => $summary['early_checkout_count'],
            'late_count' => $summary['late_count'],
            'records' => $summary['records'],
        ];
    }

    /**
     * Get attendance intelligence for admin/manager use by user id.
     */
    public function getEmployeeIntelligence(int $userId, int $days = 30): array
    {
        $user = User::with('employee.workSchedule')->find($userId);

        if (!$user || !$user->employee || !$user->employee->workSchedule) {
            return [
                'employee' => $user ? [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'summary' => [
                    'present_count' => 0,
                    'absent_count' => 0,
                    'late_count' => 0,
                    'early_checkout_count' => 0,
                    'attendance_rate' => 0,
                ],
                'records' => [],
            ];
        }

        $fromDate = now()->subDays(max(1, $days - 1))->startOfDay();
        $records = Attendance::where('user_id', $userId)
            ->with(['user.profile', 'user.employee.department', 'user.employee.position'])
            ->where('date', '>=', $fromDate->toDateString())
            ->orderBy('date')
            ->get();

        $summary = $this->buildAttendanceInsight($records, $user->employee->workSchedule->check_out_time);

        return [
            'employee' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_code' => $user->employee->employee_code,
                'department' => $user->employee->department,
                'position' => $user->employee->position,
            ],
            'window_days' => $days,
            'summary' => $summary,
        ];
    }

    /**
     * Calculate the distance between two GPS coordinates using the Haversine formula.
     *
     * @return float Distance in meters
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Build attendance insight metrics from a collection of records.
     */
    private function buildAttendanceInsight($records, string $scheduledCheckOutTime): array
    {
        $scheduleCheckOut = Carbon::today()->setTimeFromTimeString($scheduledCheckOutTime);

        $overtimeMinutes = 0;
        $earlyCheckoutCount = 0;
        $lateCount = 0;
        $absentCount = 0;
        $onTimeCount = 0;

        $resultRows = $records->map(function (Attendance $attendance) use ($scheduleCheckOut, &$overtimeMinutes, &$earlyCheckoutCount, &$lateCount, &$absentCount, &$onTimeCount) {
            $checkIn = $attendance->check_in ? Carbon::parse($attendance->check_in) : null;
            $checkOut = $attendance->check_out ? Carbon::parse($attendance->check_out) : null;

            if ($attendance->status === 'late') {
                $lateCount++;
            } elseif ($attendance->status === 'absent') {
                $absentCount++;
            } else {
                $onTimeCount++;
            }

            $rowOvertimeMinutes = 0;
            $earlyCheckout = false;

            if ($checkOut) {
                $rowOvertimeMinutes = max(0, $checkOut->diffInMinutes($scheduleCheckOut, false) * -1);

                if ($checkOut->lt($scheduleCheckOut)) {
                    $earlyCheckout = true;
                    $earlyCheckoutCount++;
                }

                if ($checkOut->gt($scheduleCheckOut)) {
                    $overtimeMinutes += $checkOut->diffInMinutes($scheduleCheckOut);
                }
            }

            return [
                'id' => $attendance->id,
                'date' => $attendance->date,
                'status' => $attendance->status,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'scheduled_check_out' => $scheduleCheckOut,
                'overtime_minutes' => max(0, $rowOvertimeMinutes),
                'early_checkout' => $earlyCheckout,
            ];
        });

        return [
            'total_days' => $records->count(),
            'on_time_count' => $onTimeCount,
            'late_count' => $lateCount,
            'absent_count' => $absentCount,
            'early_checkout_count' => $earlyCheckoutCount,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_hours' => round($overtimeMinutes / 60, 2),
            'records' => $resultRows,
        ];
    }
}

