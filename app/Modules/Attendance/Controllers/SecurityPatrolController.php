<?php

namespace App\Modules\Attendance\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SecurityPatrolCheckpoint;
use App\Models\SecurityPatrolScan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SecurityPatrolController extends Controller
{
    public function checkpoints(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('patrol.view') && !$request->user()->hasPermission('patrol.manage')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $query = SecurityPatrolCheckpoint::query()
            ->with('company:id,name,code,status')
            ->latest();

        $companyScope = app(\App\Services\CompanyScopeService::class);
        if (!$companyScope->canViewAll($request->user())) {
            $query->where(function ($q) use ($companyScope, $request) {
                $q->whereIn('company_id', $companyScope->availableCompanyIds($request->user()))
                  ->orWhereNull('company_id');
            });
        }

        $selectedCompanyId = $companyScope->selectedCompanyId($request);
        if ($selectedCompanyId) {
            $query->where('company_id', $selectedCompanyId);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return ApiResponse::success('Patrol checkpoints retrieved', $query->paginate($request->integer('per_page', 50)));
    }

    public function storeCheckpoint(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('patrol.manage')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'name' => 'required|string|max:255',
            'area' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:100',
            'room' => 'nullable|string|max:255',
            'starts_at' => 'nullable|date_format:H:i',
            'ends_at' => 'nullable|date_format:H:i',
            'tolerance_minutes' => 'nullable|integer|min:0|max:240',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $checkpoint = SecurityPatrolCheckpoint::create([
            ...$validated,
            'qr_code' => 'PATROL-' . Str::upper(Str::random(48)),
            'starts_at' => $validated['starts_at'] ?? '20:00',
            'ends_at' => $validated['ends_at'] ?? '06:00',
            'tolerance_minutes' => $validated['tolerance_minutes'] ?? 15,
            'status' => $validated['status'] ?? 'active',
        ]);

        return ApiResponse::success('Patrol checkpoint created', $this->checkpointPayload($checkpoint), 201);
    }

    public function updateCheckpoint(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('patrol.manage')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $query = SecurityPatrolCheckpoint::query();
        $companyScope = app(\App\Services\CompanyScopeService::class);
        if (!$companyScope->canViewAll($request->user())) {
            $query->whereIn('company_id', $companyScope->availableCompanyIds($request->user()));
        }
        $checkpoint = $query->findOrFail($id);

        $validated = $request->validate([
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'name' => 'sometimes|string|max:255',
            'area' => 'sometimes|nullable|string|max:255',
            'floor' => 'sometimes|nullable|string|max:100',
            'room' => 'sometimes|nullable|string|max:255',
            'starts_at' => 'sometimes|nullable|date_format:H:i',
            'ends_at' => 'sometimes|nullable|date_format:H:i',
            'tolerance_minutes' => 'sometimes|nullable|integer|min:0|max:240',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        $checkpoint->update($validated);

        return ApiResponse::success('Patrol checkpoint updated', $this->checkpointPayload($checkpoint));
    }

    public function destroyCheckpoint(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('patrol.manage')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $query = SecurityPatrolCheckpoint::query();
        $companyScope = app(\App\Services\CompanyScopeService::class);
        if (!$companyScope->canViewAll($request->user())) {
            $query->whereIn('company_id', $companyScope->availableCompanyIds($request->user()));
        }
        $query->findOrFail($id)->delete();

        return ApiResponse::success('Patrol checkpoint deleted');
    }

    public function regenerateQr(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('patrol.manage')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $query = SecurityPatrolCheckpoint::query();
        $companyScope = app(\App\Services\CompanyScopeService::class);
        if (!$companyScope->canViewAll($request->user())) {
            $query->whereIn('company_id', $companyScope->availableCompanyIds($request->user()));
        }
        $checkpoint = $query->findOrFail($id);
        $checkpoint->update(['qr_code' => 'PATROL-' . Str::upper(Str::random(48))]);

        return ApiResponse::success('Patrol QR regenerated', $this->checkpointPayload($checkpoint));
    }

    public function scan(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('patrol.scan')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $validated = $request->validate([
            'qr_code' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:1000',
        ]);

        $qrCode = $this->extractQrCode($validated['qr_code']);
        $checkpoint = SecurityPatrolCheckpoint::where('qr_code', $qrCode)
            ->where('status', 'active')
            ->first();

        if (!$checkpoint) {
            return ApiResponse::error('Invalid patrol QR checkpoint', null, 422);
        }

        $employee = $request->user()->employee;
        if (!$employee) {
            return ApiResponse::error('Employee data not found', null, 400);
        }

        if ($checkpoint->company_id && $employee->company_id && (int) $checkpoint->company_id !== (int) $employee->company_id) {
            return ApiResponse::error('Checkpoint belongs to another company', null, 403);
        }

        $scan = SecurityPatrolScan::create([
            'checkpoint_id' => $checkpoint->id,
            'user_id' => $request->user()->id,
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id ?? $checkpoint->company_id,
            'scanned_at' => now(),
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'status' => $this->resolveScanStatus($checkpoint),
            'notes' => $validated['notes'] ?? null,
            'metadata_json' => [
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ],
        ]);

        return ApiResponse::success('Patrol checkpoint scanned', $scan->load(['checkpoint.company', 'user:id,name,email', 'employee:id,employee_code']));
    }

    public function scans(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('patrol.view') && !$request->user()->hasPermission('patrol.report')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $query = SecurityPatrolScan::query()
            ->with(['checkpoint.company:id,name,code', 'user:id,name,email', 'employee:id,employee_code'])
            ->latest('scanned_at');

        if (!$request->user()->hasPermission('company.view_all')) {
            $allowedCompanyIds = $request->user()->companies()->pluck('companies.id');
            $employeeCompanyId = $request->user()->employee?->company_id;
            if ($employeeCompanyId) {
                $allowedCompanyIds->push($employeeCompanyId);
            }
            $query->whereIn('company_id', $allowedCompanyIds->unique()->values());
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('checkpoint_id')) {
            $query->where('checkpoint_id', $request->integer('checkpoint_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('scanned_at', $request->date('date'));
        }

        return ApiResponse::success('Patrol scans retrieved', $query->paginate($request->integer('per_page', 50)));
    }

    private function checkpointPayload(SecurityPatrolCheckpoint $checkpoint): array
    {
        $checkpoint->loadMissing('company:id,name,code,status');

        return [
            'checkpoint' => $checkpoint,
            'qr_payload' => [
                'type' => 'hris-security-patrol',
                'qr_code' => $checkpoint->qr_code,
                'checkpoint_id' => $checkpoint->id,
            ],
        ];
    }

    private function extractQrCode(string $raw): string
    {
        $trimmed = trim($raw);
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return (string) ($decoded['qr_code'] ?? $decoded['code'] ?? $decoded['token'] ?? $trimmed);
        }

        return $trimmed;
    }

    private function resolveScanStatus(SecurityPatrolCheckpoint $checkpoint): string
    {
        $now = Carbon::now();
        $start = Carbon::createFromFormat('H:i:s', strlen($checkpoint->starts_at) === 5 ? $checkpoint->starts_at . ':00' : $checkpoint->starts_at);
        $end = Carbon::createFromFormat('H:i:s', strlen($checkpoint->ends_at) === 5 ? $checkpoint->ends_at . ':00' : $checkpoint->ends_at);

        $currentMinutes = ($now->hour * 60) + $now->minute;
        $startMinutes = ($start->hour * 60) + $start->minute;
        $endMinutes = ($end->hour * 60) + $end->minute;
        $tolerance = (int) $checkpoint->tolerance_minutes;

        if ($startMinutes <= $endMinutes) {
            return $currentMinutes >= ($startMinutes - $tolerance) && $currentMinutes <= ($endMinutes + $tolerance)
                ? 'on_time'
                : 'outside_window';
        }

        return $currentMinutes >= ($startMinutes - $tolerance) || $currentMinutes <= ($endMinutes + $tolerance)
            ? 'on_time'
            : 'outside_window';
    }
}
