<?php

namespace App\Modules\Attendance\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\QrAttendanceToken;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QrAttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    public function generate(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('attendance.qr.generate')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'location_id' => 'nullable|exists:locations,id',
            'work_schedule_id' => 'nullable|exists:work_schedules,id',
            'purpose' => 'nullable|string|in:attendance,check_in,check_out',
            'ttl_seconds' => 'nullable|integer|min:30|max:3600',
        ]);

        $token = Str::random(64);
        $ttl = $validated['ttl_seconds'] ?? 120;

        $record = QrAttendanceToken::create([
            'company_id' => $validated['company_id'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'work_schedule_id' => $validated['work_schedule_id'] ?? null,
            'generated_by' => $request->user()->id,
            'purpose' => $validated['purpose'] ?? 'attendance',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addSeconds($ttl),
            'status' => 'active',
        ]);

        return ApiResponse::success('QR attendance token generated', [
            'token' => $token,
            'qr_payload' => [
                'type' => 'hris-attendance',
                'token' => $token,
            ],
            'expires_at' => $record->expires_at,
            'record' => $record->load(['company', 'location', 'workSchedule']),
        ], 201);
    }

    public function validateToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $token = $this->resolveToken($validated['token']);
        if (!$token) {
            return ApiResponse::error('Invalid QR token', null, 422);
        }

        return ApiResponse::success('QR token valid', $token->load(['company', 'location', 'workSchedule']));
    }

    public function checkIn(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('attendance.qr.scan')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $validated = $request->validate([
            'token' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $qrToken = $this->resolveToken($validated['token']);
        if (!$qrToken) {
            return ApiResponse::error('Invalid or expired QR token', null, 422);
        }

        $employee = $request->user()->employee;
        if (!$employee) {
            return ApiResponse::error('Employee data not found', null, 400);
        }

        if ($qrToken->company_id && $employee->company_id && (int) $qrToken->company_id !== (int) $employee->company_id) {
            return ApiResponse::error('QR token belongs to another company', null, 403);
        }

        try {
            $result = $this->attendanceService->checkIn(
                $request->user(),
                (float) $validated['latitude'],
                (float) $validated['longitude']
            );

            $result['attendance']->update([
                'company_id' => $employee->company_id ?? $qrToken->company_id,
                'qr_token_id' => $qrToken->id,
                'location_id' => $qrToken->location_id ?? $employee->location_id,
                'validation_status' => 'qr',
            ]);

            return ApiResponse::success('QR check-in successful', [
                'attendance' => $result['attendance']->fresh(['user.profile']),
                'qr' => $qrToken,
            ]);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::error('QR check-in failed', null, 500);
        }
    }

    public function checkOut(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('attendance.qr.scan')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $qrToken = $this->resolveToken($validated['token']);
        if (!$qrToken) {
            return ApiResponse::error('Invalid or expired QR token', null, 422);
        }

        $employee = $request->user()->employee;
        if (!$employee) {
            return ApiResponse::error('Employee data not found', null, 400);
        }

        if ($qrToken->company_id && $employee->company_id && (int) $qrToken->company_id !== (int) $employee->company_id) {
            return ApiResponse::error('QR token belongs to another company', null, 403);
        }

        try {
            $result = $this->attendanceService->checkOut($request->user());
            $result['attendance']->update([
                'company_id' => $employee->company_id ?? $qrToken->company_id,
                'qr_token_id' => $qrToken->id,
                'location_id' => $qrToken->location_id ?? $employee->location_id,
                'validation_status' => 'qr',
            ]);

            return ApiResponse::success('QR check-out successful', [
                'attendance' => $result['attendance']->fresh(['user.profile']),
                'overtime_request' => $result['overtime_request'],
                'qr' => $qrToken,
            ]);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::error('QR check-out failed', null, 500);
        }
    }

    private function resolveToken(string $rawToken): ?QrAttendanceToken
    {
        $token = QrAttendanceToken::where('token_hash', hash('sha256', $rawToken))
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        return $token;
    }
}
