<?php

namespace App\Modules\Attendance\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiometricIntegrationController extends Controller
{
    public function deviceIndex(Request $request): JsonResponse
    {
        return ApiResponse::success('Biometric devices retrieved successfully', DB::table('biometric_devices')->orderByDesc('id')->paginate($request->integer('per_page', 10)));
    }

    public function deviceStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'device_type' => 'sometimes|string|in:fingerprint,face,mobile,other',
            'vendor' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'endpoint_url' => 'nullable|string|max:1000',
            'api_key' => 'nullable|string|max:255',
            'active' => 'sometimes|boolean',
            'location_id' => 'nullable|integer|exists:locations,id',
        ]);

        $id = DB::table('biometric_devices')->insertGetId([
            ...$validated,
            'device_type' => $validated['device_type'] ?? 'fingerprint',
            'active' => $validated['active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Biometric device created successfully', DB::table('biometric_devices')->where('id', $id)->first(), 201);
    }

    public function syncAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'biometric_device_id' => 'nullable|integer|exists:biometric_devices,id',
            'external_reference' => 'nullable|string|max:255',
            'user_id' => 'required|integer|exists:users,id',
            'attendance_date' => 'required|date',
            'check_in' => 'nullable|date',
            'check_out' => 'nullable|date|after_or_equal:check_in',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'status' => 'sometimes|string|in:on_time,late,absent',
            'payload' => 'nullable|array',
        ]);

        $attendance = Attendance::where('user_id', $validated['user_id'])
            ->where('date', $validated['attendance_date'])
            ->first();

        if (!$attendance) {
            $attendance = Attendance::create([
                'user_id' => $validated['user_id'],
                'date' => $validated['attendance_date'],
                'check_in' => $validated['check_in'] ?? null,
                'check_out' => $validated['check_out'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'status' => $validated['status'] ?? 'on_time',
            ]);
        } else {
            $attendance->update([
                'check_in' => $validated['check_in'] ?? $attendance->check_in,
                'check_out' => $validated['check_out'] ?? $attendance->check_out,
                'latitude' => $validated['latitude'] ?? $attendance->latitude,
                'longitude' => $validated['longitude'] ?? $attendance->longitude,
                'status' => $validated['status'] ?? $attendance->status,
            ]);
        }

        DB::table('biometric_sync_logs')->insert([
            'biometric_device_id' => $validated['biometric_device_id'] ?? null,
            'external_reference' => $validated['external_reference'] ?? null,
            'user_id' => $validated['user_id'],
            'attendance_date' => $validated['attendance_date'],
            'check_in' => $validated['check_in'] ?? null,
            'check_out' => $validated['check_out'] ?? null,
            'status' => 'synced',
            'payload' => isset($validated['payload']) ? json_encode($validated['payload']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Biometric attendance synced successfully', $attendance->fresh());
    }
}
