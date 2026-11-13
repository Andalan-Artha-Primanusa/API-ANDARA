<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AndalanWorkforceController extends Controller
{
    public function payroll(string $user): JsonResponse
    {
        $identity = $this->identity($user);
        if (!$identity) return ApiResponse::error('Legacy user not found', null, 404);

        $records = DB::table('andalan_payroll_records as records')
            ->join('andalan_payroll_periods as periods', 'periods.id', '=', 'records.period_id')
            ->where('records.employee_id', $identity['employee_id'])
            ->orderByDesc('periods.period_end')
            ->select(['records.*', 'periods.period_code', 'periods.period_start', 'periods.period_end', 'periods.status as period_status'])
            ->get();

        return ApiResponse::success('ANDALAN payroll records', $records);
    }

    public function createPayrollPeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_code' => ['required', 'string', 'max:50'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'created_by_user_id' => ['required', 'string', 'max:255'],
        ]);
        if (!$this->workforceAdminAccount($validated['created_by_user_id'])) {
            return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        }

        $id = DB::table('andalan_payroll_periods')->insertGetId([
            ...$validated, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return ApiResponse::success('Payroll period ANDARA berhasil dibuat', DB::table('andalan_payroll_periods')->find($id), 201);
    }

    public function updatePayrollPeriod(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'period_code' => ['required', 'string', 'max:50'], 'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'actor_user_id' => ['required', 'string', 'max:255'],
        ]);
        if (!$this->workforceAdminAccount($validated['actor_user_id'])) return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        $period = DB::table('andalan_payroll_periods')->where('id', $id)->first();
        if (!$period) return ApiResponse::error('Payroll period tidak ditemukan', null, 404);
        if (!in_array($period->status, ['draft', 'rejected'], true)) return ApiResponse::error('Payroll yang sudah diproses tidak dapat diedit.', null, 409);
        DB::table('andalan_payroll_periods')->where('id', $id)->update([
            'period_code' => $validated['period_code'], 'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'], 'updated_at' => now(),
        ]);
        return ApiResponse::success('Payroll period ANDARA berhasil diperbarui', DB::table('andalan_payroll_periods')->find($id));
    }

    public function createPayrollRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => ['required', 'integer', 'exists:andalan_payroll_periods,id'],
            'user_id' => ['required', 'string', 'max:255'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'deduction_amount' => ['nullable', 'numeric', 'min:0'],
            'source_ref' => ['nullable', 'string', 'max:255'],
            'created_by_user_id' => ['required', 'string', 'max:255'],
        ]);
        if (!$this->workforceAdminAccount($validated['created_by_user_id'])) {
            return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        }
        $identity = $this->identity($validated['user_id']);
        if (!$identity) return ApiResponse::error('Legacy user not found', null, 404);

        $gross = (float) $validated['gross_amount'];
        $deduction = (float) ($validated['deduction_amount'] ?? 0);
        $id = DB::table('andalan_payroll_records')->insertGetId([
            'period_id' => $validated['period_id'], 'employee_id' => $identity['employee_id'],
            'salary_number' => $identity['salary_number'], 'employee_name' => $identity['employee_name'],
            'gross_amount' => $gross, 'deduction_amount' => $deduction, 'net_amount' => $gross - $deduction,
            'status' => 'draft', 'source_ref' => $validated['source_ref'] ?? null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return ApiResponse::success('Payroll record ANDARA berhasil dibuat', DB::table('andalan_payroll_records')->find($id), 201);
    }

    public function approvePayrollPeriod(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['action' => ['required', 'in:approved,rejected'], 'actor_user_id' => ['required', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:2000']]);
        if (!$this->workforceAdminAccount($validated['actor_user_id'])) {
            return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        }
        $period = DB::table('andalan_payroll_periods')->where('id', $id)->first();
        if (!$period) return ApiResponse::error('Payroll period tidak ditemukan', null, 404);
        DB::transaction(function () use ($validated, $id) {
            DB::table('andalan_payroll_periods')->where('id', $id)->update(['status' => $validated['action'], 'updated_at' => now()]);
            DB::table('andalan_payroll_records')->where('period_id', $id)->update(['status' => $validated['action'], 'updated_at' => now()]);
            DB::table('andalan_payroll_approvals')->insert([
                'period_id' => $id, 'approval_role' => 'payroll', 'actor_user_id' => $validated['actor_user_id'] ?? null,
                'action' => $validated['action'], 'note' => $validated['note'] ?? null, 'acted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return ApiResponse::success('Approval payroll berhasil diproses', DB::table('andalan_payroll_periods')->find($id));
    }

    public function trainingPrograms(Request $request): JsonResponse
    {
        $query = DB::table('andalan_training_programs')->orderByDesc('start_date')->orderByDesc('id');
        if ($request->boolean('published_only', false)) $query->where('status', 'published');
        return ApiResponse::success('ANDALAN training programs', $query->limit(100)->get());
    }

    public function createTrainingProgram(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'provider' => ['nullable', 'string', 'max:255'], 'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'], 'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:draft,published,closed'], 'created_by_user_id' => ['required', 'string', 'max:255'],
        ]);
        if (!$this->workforceAdminAccount($validated['created_by_user_id'])) {
            return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        }
        $id = DB::table('andalan_training_programs')->insertGetId([
            ...$validated, 'status' => $validated['status'] ?? 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return ApiResponse::success('Program training ANDARA berhasil dibuat', DB::table('andalan_training_programs')->find($id), 201);
    }

    public function updateTrainingProgram(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'provider' => ['nullable', 'string', 'max:255'], 'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'], 'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:draft,published,closed'], 'actor_user_id' => ['required', 'string', 'max:255'],
        ]);
        if (!$this->workforceAdminAccount($validated['actor_user_id'])) return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        if (!DB::table('andalan_training_programs')->where('id', $id)->exists()) return ApiResponse::error('Program training tidak ditemukan', null, 404);
        unset($validated['actor_user_id']);
        DB::table('andalan_training_programs')->where('id', $id)->update([...$validated, 'updated_at' => now()]);
        return ApiResponse::success('Program training ANDARA berhasil diperbarui', DB::table('andalan_training_programs')->find($id));
    }

    public function trainingEnrollments(string $user): JsonResponse
    {
        $identity = $this->identity($user);
        if (!$identity) return ApiResponse::error('Legacy user not found', null, 404);
        $items = DB::table('andalan_training_enrollments as enrollments')
            ->join('andalan_training_programs as programs', 'programs.id', '=', 'enrollments.program_id')
            ->where('enrollments.employee_id', $identity['employee_id'])
            ->orderByDesc('programs.start_date')
            ->select(['enrollments.*', 'programs.name', 'programs.provider', 'programs.start_date', 'programs.end_date'])
            ->get();
        return ApiResponse::success('ANDALAN training enrollments', $items);
    }

    public function enrollTraining(Request $request, int $programId): JsonResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'string', 'max:255']]);
        $identity = $this->identity($validated['user_id']);
        if (!$identity) return ApiResponse::error('Legacy user not found', null, 404);
        $program = DB::table('andalan_training_programs')->where('id', $programId)->where('status', 'published')->first();
        if (!$program) return ApiResponse::error('Training program tidak tersedia', null, 404);
        $id = DB::table('andalan_training_enrollments')->insertGetId([
            'program_id' => $programId, 'employee_id' => $identity['employee_id'], 'salary_number' => $identity['salary_number'],
            'employee_name' => $identity['employee_name'], 'status' => 'requested', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->notify($identity['user_id'], 'Pendaftaran training diterima', 'Pendaftaran ke ' . $program->name . ' menunggu approval.', 'training_enrollment', $id);
        return ApiResponse::success('Pendaftaran training berhasil dibuat', DB::table('andalan_training_enrollments')->find($id), 201);
    }

    public function approveTraining(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['action' => ['required', 'in:approved,rejected'], 'actor_user_id' => ['required', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:2000']]);
        if (!$this->workforceAdminAccount($validated['actor_user_id'])) {
            return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        }
        $item = DB::table('andalan_training_enrollments')->where('id', $id)->first();
        if (!$item) return ApiResponse::error('Pendaftaran training tidak ditemukan', null, 404);
        DB::transaction(function () use ($validated, $id) {
            DB::table('andalan_training_enrollments')->where('id', $id)->update(['status' => $validated['action'], 'updated_at' => now()]);
            DB::table('andalan_training_approvals')->insert([
                'enrollment_id' => $id, 'approval_role' => 'training', 'actor_user_id' => $validated['actor_user_id'] ?? null,
                'action' => $validated['action'], 'note' => $validated['note'] ?? null, 'acted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return ApiResponse::success('Approval training berhasil diproses', DB::table('andalan_training_enrollments')->find($id));
    }

    public function assets(string $user): JsonResponse
    {
        $identity = $this->identity($user);
        if (!$identity) return ApiResponse::error('Legacy user not found', null, 404);
        $items = DB::table('andalan_asset_assignments as assignments')
            ->join('andalan_assets as assets', 'assets.id', '=', 'assignments.asset_id')
            ->where('assignments.employee_id', $identity['employee_id'])
            ->orderByDesc('assignments.created_at')
            ->select(['assignments.*', 'assets.asset_code', 'assets.name', 'assets.category', 'assets.serial_number', 'assets.status as asset_status'])
            ->get();
        return ApiResponse::success('ANDALAN employee assets', $items);
    }

    public function createAsset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_code' => ['required', 'string', 'max:100'], 'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'], 'serial_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $id = DB::table('andalan_assets')->insertGetId([...$validated, 'status' => 'available', 'created_at' => now(), 'updated_at' => now()]);
        return ApiResponse::success('Asset ANDARA berhasil dibuat', DB::table('andalan_assets')->find($id), 201);
    }

    /**
     * Asset inventory reserved for the MINEPRO Super Admin (RoleID 1).
     * The inventory itself remains in ANDARA tables; MINEPRO is only used
     * to verify the actor and resolve employee references.
     */
    public function updateAsset(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'asset_code' => ['required', 'string', 'max:100'], 'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'], 'serial_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:available,assigned,maintenance,retired'], 'notes' => ['nullable', 'string', 'max:2000'],
            'actor_user_id' => ['required', 'string', 'max:255'],
        ]);
        if (!$this->superAdminAccount($validated['actor_user_id'])) return ApiResponse::error('Akses khusus Super Admin.', null, 403);
        if (!DB::table('andalan_assets')->where('id', $id)->exists()) return ApiResponse::error('Asset tidak ditemukan', null, 404);
        unset($validated['actor_user_id']);
        DB::table('andalan_assets')->where('id', $id)->update([...$validated, 'updated_at' => now()]);
        return ApiResponse::success('Asset ANDARA berhasil diperbarui', DB::table('andalan_assets')->find($id));
    }

    public function superAdminAssets(string $user): JsonResponse
    {
        if (!$this->superAdminAccount($user)) {
            return ApiResponse::error('Akses khusus Super Admin.', null, 403);
        }

        $items = DB::table('andalan_assets as assets')
            ->leftJoin('andalan_asset_assignments as assignments', function ($join) {
                $join->on('assignments.asset_id', '=', 'assets.id')
                    ->where('assignments.status', '=', 'assigned');
            })
            ->orderByDesc('assets.created_at')
            ->select([
                'assets.*',
                'assignments.employee_id as assigned_employee_id',
                'assignments.employee_name as assigned_employee_name',
                'assignments.salary_number as assigned_salary_number',
            ])
            ->limit(500)
            ->get()
            ->map(function ($asset) {
                $asset->barcode_value = 'ANDARA-ASSET:' . $asset->asset_code;
                return $asset;
            });

        return ApiResponse::success('Inventaris asset ANDARA', $items);
    }

    public function createSuperAdminAsset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'max:255'],
            'asset_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:andalan_assets,asset_code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (!$this->superAdminAccount($validated['user_id'])) {
            return ApiResponse::error('Akses khusus Super Admin.', null, 403);
        }

        $assetCode = trim((string) ($validated['asset_code'] ?? ''));
        if ($assetCode === '') {
            do {
                $assetCode = 'AND-' . now()->format('YmdHis') . '-' . random_int(100, 999);
            } while (DB::table('andalan_assets')->where('asset_code', $assetCode)->exists());
        }

        $id = DB::table('andalan_assets')->insertGetId([
            'asset_code' => $assetCode,
            'name' => $validated['name'],
            'category' => $validated['category'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $asset = DB::table('andalan_assets')->find($id);
        return ApiResponse::success('Asset ANDARA berhasil dibuat', [
            'asset' => $asset,
            'barcode_value' => 'ANDARA-ASSET:' . $assetCode,
            'barcode_format' => 'QR',
        ], 201);
    }

    public function scanSuperAdminAsset(string $user, string $assetCode): JsonResponse
    {
        if (!$this->superAdminAccount($user)) {
            return ApiResponse::error('Akses khusus Super Admin.', null, 403);
        }

        $assetCode = trim($assetCode);
        if (str_starts_with($assetCode, 'ANDARA-ASSET:')) {
            $assetCode = substr($assetCode, strlen('ANDARA-ASSET:'));
        }

        $asset = DB::table('andalan_assets')->where('asset_code', $assetCode)->first();
        if (!$asset) return ApiResponse::error('Barcode asset tidak ditemukan.', null, 404);

        $assignment = DB::table('andalan_asset_assignments')
            ->where('asset_id', $asset->id)
            ->whereIn('status', ['assigned', 'requested'])
            ->orderByDesc('id')
            ->first();

        return ApiResponse::success('Detail asset ANDARA', [
            'asset' => $asset,
            'assignment' => $assignment,
            'barcode_value' => 'ANDARA-ASSET:' . $asset->asset_code,
        ]);
    }

    public function assignAsset(Request $request, int $assetId): JsonResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $identity = $this->identity($validated['user_id']);
        $asset = DB::table('andalan_assets')->where('id', $assetId)->where('status', 'available')->first();
        if (!$identity) return ApiResponse::error('Legacy user not found', null, 404);
        if (!$asset) return ApiResponse::error('Asset tidak tersedia', null, 404);
        $id = DB::table('andalan_asset_assignments')->insertGetId([
            'asset_id' => $assetId, 'employee_id' => $identity['employee_id'], 'salary_number' => $identity['salary_number'],
            'employee_name' => $identity['employee_name'], 'status' => 'requested', 'notes' => $validated['notes'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->notify($identity['user_id'], 'Permintaan asset diterima', 'Permintaan asset ' . $asset->name . ' menunggu approval.', 'asset_assignment', $id);
        return ApiResponse::success('Permintaan asset berhasil dibuat', DB::table('andalan_asset_assignments')->find($id), 201);
    }

    public function approveAsset(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['action' => ['required', 'in:approved,rejected,returned'], 'actor_user_id' => ['required', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:2000']]);
        if (!$this->workforceAdminAccount($validated['actor_user_id'])) {
            return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        }
        $item = DB::table('andalan_asset_assignments')->where('id', $id)->first();
        if (!$item) return ApiResponse::error('Assignment asset tidak ditemukan', null, 404);
        DB::transaction(function () use ($validated, $id, $item) {
            $assignmentStatus = $validated['action'] === 'approved' ? 'assigned' : $validated['action'];
            DB::table('andalan_asset_assignments')->where('id', $id)->update(['status' => $assignmentStatus, 'assigned_at' => $validated['action'] === 'approved' ? now()->toDateString() : null, 'returned_at' => $validated['action'] === 'returned' ? now()->toDateString() : null, 'updated_at' => now()]);
            DB::table('andalan_assets')->where('id', $item->asset_id)->update(['status' => $validated['action'] === 'returned' ? 'available' : ($validated['action'] === 'approved' ? 'assigned' : 'available'), 'updated_at' => now()]);
            DB::table('andalan_asset_approvals')->insert([
                'assignment_id' => $id, 'approval_role' => 'asset', 'actor_user_id' => $validated['actor_user_id'] ?? null,
                'action' => $validated['action'], 'note' => $validated['note'] ?? null, 'acted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return ApiResponse::success('Approval asset berhasil diproses', DB::table('andalan_asset_assignments')->find($id));
    }

    public function overtime(string $user): JsonResponse
    {
        $identity = $this->identity($user);
        if (!$identity) return ApiResponse::error('Legacy user not found', null, 404);
        $items = DB::table('andalan_overtime_requests')->where('employee_id', $identity['employee_id'])->orderByDesc('work_date')->orderByDesc('id')->limit(100)->get();
        return ApiResponse::success('ANDALAN overtime requests', $items);
    }

    public function createOvertime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'max:255'], 'work_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $identity = $this->identity($validated['user_id']);
        if (!$identity) return ApiResponse::error('Legacy user not found', null, 404);
        $start = Carbon::createFromFormat('H:i', $validated['start_time']);
        $end = Carbon::createFromFormat('H:i', $validated['end_time']);
        if ($end->lessThanOrEqualTo($start)) $end->addDay();
        $hours = round($start->diffInMinutes($end) / 60, 2);
        $id = DB::table('andalan_overtime_requests')->insertGetId([
            'employee_id' => $identity['employee_id'], 'salary_number' => $identity['salary_number'], 'employee_name' => $identity['employee_name'],
            'work_date' => $validated['work_date'], 'start_time' => $validated['start_time'], 'end_time' => $validated['end_time'],
            'total_hours' => $hours, 'reason' => $validated['reason'], 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->notify($identity['user_id'], 'Pengajuan lembur dibuat', 'Pengajuan lembur ' . $hours . ' jam menunggu approval.', 'overtime', $id);
        return ApiResponse::success('Pengajuan lembur berhasil dibuat', DB::table('andalan_overtime_requests')->find($id), 201);
    }

    public function updateOvertime(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'work_date' => ['required', 'date_format:Y-m-d'], 'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'], 'reason' => ['required', 'string', 'max:2000'],
            'actor_user_id' => ['required', 'string', 'max:255'],
        ]);
        if (!$this->workforceAdminAccount($validated['actor_user_id'])) return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        $item = DB::table('andalan_overtime_requests')->where('id', $id)->first();
        if (!$item) return ApiResponse::error('Pengajuan lembur tidak ditemukan', null, 404);
        if (!in_array($item->status, ['draft', 'rejected'], true)) return ApiResponse::error('Lembur yang sudah diproses tidak dapat diedit.', null, 409);
        $start = Carbon::parse($validated['work_date'] . ' ' . $validated['start_time']);
        $end = Carbon::parse($validated['work_date'] . ' ' . $validated['end_time']);
        if ($end->lessThanOrEqualTo($start)) $end->addDay();
        DB::table('andalan_overtime_requests')->where('id', $id)->update([
            'work_date' => $validated['work_date'], 'start_time' => $validated['start_time'], 'end_time' => $validated['end_time'],
            'total_hours' => round($start->diffInMinutes($end) / 60, 2), 'reason' => $validated['reason'], 'updated_at' => now(),
        ]);
        return ApiResponse::success('Pengajuan lembur ANDARA berhasil diperbarui', DB::table('andalan_overtime_requests')->find($id));
    }

    public function approveOvertime(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['action' => ['required', 'in:approved,rejected'], 'actor_user_id' => ['required', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:2000']]);
        if (!$this->workforceAdminAccount($validated['actor_user_id'])) {
            return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        }
        $item = DB::table('andalan_overtime_requests')->where('id', $id)->first();
        if (!$item) return ApiResponse::error('Pengajuan lembur tidak ditemukan', null, 404);
        DB::transaction(function () use ($validated, $id, $item) {
            DB::table('andalan_overtime_requests')->where('id', $id)->update(['status' => $validated['action'], 'updated_at' => now()]);
            DB::table('andalan_overtime_approvals')->insert([
                'overtime_request_id' => $id, 'approval_role' => 'supervisor', 'actor_user_id' => $validated['actor_user_id'] ?? null,
                'action' => $validated['action'], 'note' => $validated['note'] ?? null, 'acted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return ApiResponse::success('Approval lembur berhasil diproses', DB::table('andalan_overtime_requests')->where('id', $id)->first());
    }

    public function documents(string $user): JsonResponse
    {
        $identity = $this->identity($user);
        if (!$identity) return ApiResponse::error('Legacy user not found', null, 404);
        return ApiResponse::success('ANDALAN employee documents', DB::table('andalan_documents')->where('employee_id', $identity['employee_id'])->orderByDesc('issued_at')->orderByDesc('id')->limit(100)->get());
    }

    public function createDocument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'max:255'], 'document_type' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'], 'file_url' => ['nullable', 'url', 'max:1000'],
            'issued_at' => ['nullable', 'date_format:Y-m-d'], 'expires_at' => ['nullable', 'date_format:Y-m-d'],
            'uploaded_by_user_id' => ['required', 'string', 'max:255'],
        ]);
        if (!$this->workforceAdminAccount($validated['uploaded_by_user_id'])) {
            return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        }
        $identity = $this->identity($validated['user_id']);
        if (!$identity) return ApiResponse::error('Legacy user not found', null, 404);
        $id = DB::table('andalan_documents')->insertGetId([
            'employee_id' => $identity['employee_id'], 'salary_number' => $identity['salary_number'], 'employee_name' => $identity['employee_name'],
            'document_type' => $validated['document_type'], 'title' => $validated['title'], 'file_url' => $validated['file_url'] ?? null,
            'issued_at' => $validated['issued_at'] ?? null, 'expires_at' => $validated['expires_at'] ?? null, 'status' => 'active',
            'uploaded_by_user_id' => $validated['uploaded_by_user_id'] ?? null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return ApiResponse::success('Dokumen ANDARA berhasil dibuat', DB::table('andalan_documents')->find($id), 201);
    }

    public function updateDocument(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'max:255'], 'document_type' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'], 'file_url' => ['nullable', 'url', 'max:1000'],
            'issued_at' => ['nullable', 'date_format:Y-m-d'], 'expires_at' => ['nullable', 'date_format:Y-m-d'],
            'actor_user_id' => ['required', 'string', 'max:255'],
        ]);
        if (!$this->workforceAdminAccount($validated['actor_user_id'])) return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        $identity = $this->identity($validated['user_id']);
        if (!$identity) return ApiResponse::error('Legacy user not found', null, 404);
        if (!DB::table('andalan_documents')->where('id', $id)->exists()) return ApiResponse::error('Dokumen tidak ditemukan', null, 404);
        DB::table('andalan_documents')->where('id', $id)->update([
            'employee_id' => $identity['employee_id'], 'salary_number' => $identity['salary_number'], 'employee_name' => $identity['employee_name'],
            'document_type' => $validated['document_type'], 'title' => $validated['title'], 'file_url' => $validated['file_url'] ?? null,
            'issued_at' => $validated['issued_at'] ?? null, 'expires_at' => $validated['expires_at'] ?? null, 'updated_at' => now(),
        ]);
        return ApiResponse::success('Dokumen ANDARA berhasil diperbarui', DB::table('andalan_documents')->find($id));
    }

    public function reviewDocument(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['action' => ['required', 'in:verified,rejected'], 'actor_user_id' => ['required', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:2000']]);
        if (!$this->workforceAdminAccount($validated['actor_user_id'])) {
            return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        }
        $document = DB::table('andalan_documents')->where('id', $id)->first();
        if (!$document) return ApiResponse::error('Dokumen tidak ditemukan', null, 404);
        DB::transaction(function () use ($validated, $id) {
            DB::table('andalan_documents')->where('id', $id)->update(['status' => $validated['action'], 'updated_at' => now()]);
            DB::table('andalan_document_reviews')->insert([
                'document_id' => $id, 'review_role' => 'hr', 'actor_user_id' => $validated['actor_user_id'] ?? null,
                'action' => $validated['action'], 'note' => $validated['note'] ?? null, 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return ApiResponse::success('Review dokumen berhasil diproses', DB::table('andalan_documents')->find($id));
    }

    public function adminPayroll(string $user): JsonResponse
    {
        if (!$this->workforceAdminAccount($user)) return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        $periods = DB::table('andalan_payroll_periods as periods')
            ->leftJoin('andalan_payroll_records as records', 'records.period_id', '=', 'periods.id')
            ->groupBy('periods.id', 'periods.period_code', 'periods.period_start', 'periods.period_end', 'periods.status', 'periods.created_by_user_id', 'periods.created_at', 'periods.updated_at')
            ->orderByDesc('periods.period_end')
            ->select(['periods.*', DB::raw('COUNT(records.id) as record_count')])
            ->limit(100)->get();
        $records = DB::table('andalan_payroll_records')->orderByDesc('id')->limit(500)->get();
        return ApiResponse::success('ANDALAN admin payroll', ['periods' => $periods, 'records' => $records]);
    }

    public function adminTraining(string $user): JsonResponse
    {
        if (!$this->workforceAdminAccount($user)) return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        $programs = DB::table('andalan_training_programs')->orderByDesc('start_date')->orderByDesc('id')->limit(100)->get();
        $enrollments = DB::table('andalan_training_enrollments as enrollments')
            ->join('andalan_training_programs as programs', 'programs.id', '=', 'enrollments.program_id')
            ->orderByDesc('enrollments.id')
            ->select(['enrollments.*', 'programs.name as program_name', 'programs.provider', 'programs.start_date', 'programs.end_date'])
            ->limit(500)->get();
        return ApiResponse::success('ANDALAN admin training', ['programs' => $programs, 'enrollments' => $enrollments]);
    }

    public function adminAssets(string $user): JsonResponse
    {
        if (!$this->workforceAdminAccount($user)) return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        $items = DB::table('andalan_assets as assets')
            ->leftJoin('andalan_asset_assignments as assignments', function ($join) {
                $join->on('assignments.asset_id', '=', 'assets.id')->where('assignments.status', '=', 'assigned');
            })
            ->orderByDesc('assets.created_at')
            ->select(['assets.*', 'assignments.employee_id as assigned_employee_id', 'assignments.employee_name as assigned_employee_name', 'assignments.salary_number as assigned_salary_number'])
            ->limit(500)->get()
            ->map(function ($asset) { $asset->barcode_value = 'ANDARA-ASSET:' . $asset->asset_code; return $asset; });
        $assignments = DB::table('andalan_asset_assignments')->orderByDesc('id')->limit(500)->get();
        return ApiResponse::success('ANDALAN admin assets', ['assets' => $items, 'assignments' => $assignments]);
    }

    public function adminOvertime(string $user): JsonResponse
    {
        if (!$this->workforceAdminAccount($user)) return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        return ApiResponse::success('ANDALAN admin overtime', DB::table('andalan_overtime_requests')->orderByDesc('work_date')->orderByDesc('id')->limit(500)->get());
    }

    public function adminDocuments(string $user): JsonResponse
    {
        if (!$this->workforceAdminAccount($user)) return ApiResponse::error('Akses khusus admin workforce.', null, 403);
        return ApiResponse::success('ANDALAN admin documents', DB::table('andalan_documents')->orderByDesc('issued_at')->orderByDesc('id')->limit(500)->get());
    }

    private function identity(string $user): ?array
    {
        $login = trim($user);
        $account = DB::table('ms_sa_permission')->where(function ($query) use ($login) {
            $query->whereRaw('LOWER(LTRIM(RTRIM(UserID))) = LOWER(?)', [$login])
                ->orWhereRaw('LOWER(LTRIM(RTRIM(EmailAddress))) = LOWER(?)', [$login]);
        })->first();
        if (!$account) return null;
        $employee = DB::table('ms_hr_employee')->where(function ($query) use ($account, $login) {
            $query->whereRaw('LOWER(LTRIM(RTRIM(Salary_Number))) = LOWER(?)', [$login])
                ->orWhereRaw('LOWER(LTRIM(RTRIM(UserID))) = LOWER(?)', [(string) $account->UserID])
                ->orWhereRaw('LOWER(LTRIM(RTRIM(EmailAddress))) = LOWER(?)', [(string) ($account->EmailAddress ?? '')]);
        })->first();
        if (!$employee) return null;
        return [
            'user_id' => trim((string) $account->UserID), 'employee_id' => trim((string) $employee->Employee_ID),
            'salary_number' => trim((string) ($employee->Salary_Number ?? '')), 'employee_name' => trim((string) ($employee->CompleteName ?? $account->CompleteUserName ?? '')),
        ];
    }

    private function superAdminAccount(string $user): ?object
    {
        $account = $this->legacyAccount($user);
        return $account && (bool) $account->isActiveUser && (int) $account->UserRoleID === 1 ? $account : null;
    }

    private function workforceAdminAccount(string $user): ?object
    {
        $account = $this->legacyAccount($user);
        if (!$account || !(bool) $account->isActiveUser) return null;
        return in_array((int) $account->UserRoleID, [1, 76, 79, 89, 90], true) ? $account : null;
    }

    private function legacyAccount(string $user): ?object
    {
        return DB::table('ms_sa_permission')
            ->where(function ($query) use ($user) {
                $query->whereRaw('LOWER(LTRIM(RTRIM(UserID))) = LOWER(?)', [trim($user)])
                    ->orWhereRaw('LOWER(LTRIM(RTRIM(EmailAddress))) = LOWER(?)', [trim($user)]);
            })
            ->first();
    }

    private function notify(string $recipientKey, string $title, string $message, string $sourceType, int $sourceId): void
    {
        DB::table('andalan_notifications')->insert([
            'recipient_key' => $recipientKey, 'title' => $title, 'message' => $message, 'type' => 'workforce',
            'source_type' => $sourceType, 'source_id' => $sourceId, 'is_read' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
