<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

/**
 * Read-only adapter for the existing MINEPRO SQL Server database.
 *
 * The legacy tables do not match the application's Laravel schemas, so this
 * controller deliberately exposes a presentation contract without writes.
 */
class MineproLegacyController extends Controller
{
    public function reimbursements(Request $request): JsonResponse
    {
        $query = DB::table('tr_hr_benefit_claim')->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->toString() . '%';
            $query->where(function ($builder) use ($search) {
                $builder->where('numberbenefit', 'like', $search)
                    ->orWhere('typeclaim', 'like', $search)
                    ->orWhere('project', 'like', $search)
                    ->orWhere('createby', 'like', $search);
            });
        }

        $paginator = $query->paginate($request->integer('per_page', 10))->withQueryString();
        $paginator->getCollection()->transform(function ($claim) {
            return [
                'id' => (string) $claim->id,
                'title' => trim((string) ($claim->typeclaim ?: $claim->numberbenefit)),
                'description' => trim((string) ($claim->remarks ?? '')) ?: null,
                'amount' => (float) ($claim->totalamount ?? $claim->invoiceamount ?? 0),
                'invoice_amount' => (float) ($claim->invoiceamount ?? 0),
                'category' => trim((string) ($claim->typeclaim ?? 'other')) ?: 'other',
                'expense_date' => $claim->invoicedate,
                'status' => strtolower(trim((string) ($claim->status ?? 'open'))),
                'employee_id' => (string) ($claim->createby ?? ''),
                'employee_name' => (string) ($claim->createby ?? 'Legacy employee'),
                'project' => trim((string) ($claim->project ?? '')) ?: null,
                'number_benefit' => $claim->numberbenefit,
                'relationship' => trim((string) ($claim->relationship ?? '')) ?: null,
                'patient_name' => trim((string) ($claim->patientname ?? '')) ?: null,
                'hospital' => trim((string) ($claim->hospital ?? '')) ?: null,
                'disease' => trim((string) ($claim->disease ?? '')) ?: null,
                'start_date' => $claim->startdate,
                'end_date' => $claim->enddate,
                'created_at' => $claim->createdate,
                'updated_at' => $claim->updatedate,
                'legacy_source' => 'tr_hr_benefit_claim',
            ];
        });

        return ApiResponse::success('Legacy MINEPRO benefit claims', $paginator);
    }

    public function reimbursementStatistics(Request $request): JsonResponse
    {
        $baseQuery = DB::table('tr_hr_benefit_claim');

        if ($request->filled('employee_id')) {
            $baseQuery->where('createby', $request->string('employee_id')->toString());
        }

        $count = fn (?string $status = null): int => (clone $baseQuery)
            ->when($status !== null, fn ($query) => $query->whereRaw('LOWER(LTRIM(RTRIM(status))) = ?', [$status]))
            ->count();

        $amount = fn (?string $status = null): float => (float) ((clone $baseQuery)
            ->when($status !== null, fn ($query) => $query->whereRaw('LOWER(LTRIM(RTRIM(status))) = ?', [$status]))
            ->sum(DB::raw('COALESCE(totalamount, invoiceamount, 0)')));

        $stats = [
            'total_count' => $count(),
            'total_amount' => $amount(),
            'draft_count' => $count('draft'),
            'draft_amount' => $amount('draft'),
            'submitted_count' => $count('submitted'),
            'submitted_amount' => $amount('submitted'),
            'approved_count' => $count('approved'),
            'approved_amount' => $amount('approved'),
            'paid_count' => $count('paid'),
            'paid_amount' => $amount('paid'),
        ];

        return ApiResponse::success('Legacy MINEPRO reimbursement statistics', $stats);
    }

    public function entitlements(Request $request): JsonResponse
    {
        $query = DB::table('tr_hr_benefit_entitlement')->orderByDesc('id');

        if ($request->filled('employee_id')) {
            $query->where('sn', $request->string('employee_id')->toString());
        }

        $paginator = $query->paginate($request->integer('per_page', 20))->withQueryString();
        $paginator->getCollection()->transform(fn ($item) => [
            'id' => (string) $item->id,
            'employee_id' => trim((string) $item->sn),
            'benefit_type' => trim((string) $item->typebenefit),
            'start_date' => $item->startdate,
            'end_date' => $item->enddate,
            'amount' => (float) $item->amount,
            'amount_used' => (float) $item->amountused,
            'amount_balance' => (float) $item->amountbalance,
            'legacy_source' => 'tr_hr_benefit_entitlement',
        ]);

        return ApiResponse::success('Legacy MINEPRO benefit entitlements', $paginator);
    }

    public function ees(Request $request): JsonResponse
    {
        $periods = DB::table('ms_ees_period')->orderByDesc('periode_start')->get()->map(fn ($period) => [
            'id' => (string) $period->PeriodeID,
            'title' => $period->Title,
            'start_date' => $period->periode_start,
            'end_date' => $period->periode_end,
            'is_active' => (bool) $period->isActive,
        ])->values();

        $questions = DB::table('ms_ees_questions')->orderBy('QuestionID')->get()->map(fn ($question) => [
            'id' => (string) $question->QuestionID,
            'title' => $question->Title,
            'description' => $question->Description,
            'is_active' => $question->IsActive === null ? true : (bool) $question->IsActive,
        ])->values();

        $details = DB::table('tr_ees_results_detail')->orderBy('ResultDetailID')->get();
        $detailsByResult = $details->groupBy(fn ($detail) => (string) $detail->IDResult);

        $results = DB::table('tr_ees_results')->orderByDesc('ResultDate')->get()->map(function ($result) use ($detailsByResult, $periods) {
            $resultDetails = $detailsByResult->get((string) $result->ResultID, collect());
            $scoreValues = $resultDetails->map(fn ($detail) => (float) $detail->ScoreValue)->filter(fn ($score) => $score > 0);
            $score = $scoreValues->count() > 0 ? round(($scoreValues->avg() / 5) * 100, 2) : 0;
            $period = $periods->firstWhere('id', (string) $result->IDPeriode);

            return [
                'id' => (string) $result->ResultID,
                'employee_id' => (string) $result->IDEmployee,
                'title' => (string) ($result->CategoryName ?: 'EES'),
                'description' => 'Employee Engagement Survey',
                'target' => 100,
                'achievement' => $score,
                'score' => $score,
                'period' => $period['title'] ?? (string) $result->IDPeriode,
                'period_id' => (string) $result->IDPeriode,
                'status' => ((string) $result->IsCompleted === '1') ? 'completed' : 'in_progress',
                'result_date' => $result->ResultDate,
                'completed_at' => $result->CompletedAt,
                'details' => $resultDetails->map(fn ($detail) => [
                    'id' => (string) $detail->ResultDetailID,
                    'question_id' => (string) $detail->IDQuestion,
                    'question' => $detail->QuestionDescription,
                    'score' => (float) $detail->ScoreValue,
                    'score_description' => $detail->ScoreDescription,
                ])->values()->all(),
                'legacy_source' => 'tr_ees_results',
            ];
        })->values();

        return ApiResponse::success('Legacy MINEPRO KPI/EES', [
            'items' => $results,
            'periods' => $periods,
            'questions' => $questions,
            'results' => $results,
        ]);
    }

    /**
     * Read the currently logged-in legacy user's account and employee data.
     *
     * Legacy presentation login has no Laravel/Sanctum token, so the
     * self-service /my/profile endpoint cannot resolve the user through
     * request authentication. This read-only bridge uses the UserID that was
     * returned by the legacy login and never exposes the stored password.
     */
    public function profile(string $user): JsonResponse
    {
        $account = DB::table('ms_sa_permission')
            ->select([
                'UserID',
                'EmailAddress',
                'CompleteUserName',
                'UserRoleID',
                'isActiveUser',
                'isFirstLogin',
            ])
            ->whereRaw('LOWER(LTRIM(RTRIM(UserID))) = LOWER(?)', [$user])
            ->first();

        if (!$account) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $employee = DB::table('ms_hr_employee')
            ->select([
                'Employee_ID',
                'Salary_Number',
                'CompleteName',
                'NickName',
                'PlaceOfBirth',
                'DateOfBirth',
                'Religion',
                'Citizenship',
                'Sex',
                'MaritalStatus',
                'BloodGroup',
                'Height',
                'Weight',
                'CellPhoneNumber',
                'EmailAddress',
                'InputLocation',
                'ProjectID',
                'ServerID',
                'PlaceOfApp',
                'InputDate',
                'LastModifyAt',
                'status_employee',
                'status_hire',
                'tgl_hire',
                'tgl_resign',
                'tgl_AktifKerja',
                'UserID',
                'PositionRef',
                'PositionDesc',
                'Department',
            ])
            ->where(function ($query) use ($account, $user) {
                $query->whereRaw('LOWER(LTRIM(RTRIM(UserID))) = LOWER(?)', [(string) $account->UserID])
                    ->orWhereRaw('LOWER(LTRIM(RTRIM(Salary_Number))) = LOWER(?)', [$user])
                    ->orWhereRaw('LOWER(LTRIM(RTRIM(EmailAddress))) = LOWER(?)', [(string) ($account->EmailAddress ?? '')]);
            })
            ->first();

        $detail = $employee
            ? DB::table('ms_hr_employee_detail')->where('Employee_ID', $employee->Employee_ID)->first()
            : null;

        return ApiResponse::success('Legacy profile', [
            'account' => [
                'id' => (string) $account->UserID,
                'user_id' => (string) $account->UserID,
                'name' => trim((string) ($account->CompleteUserName ?? '')),
                'email' => trim((string) ($account->EmailAddress ?? '')),
                'role_id' => $account->UserRoleID,
                'is_active' => (bool) $account->isActiveUser,
                'is_first_login' => (bool) $account->isFirstLogin,
            ],
            'employee' => $employee ? $this->normalizeLegacyEmployee($employee) : null,
            'employee_detail' => $detail ? (array) $detail : [],
            'legacy_source' => 'ms_sa_permission + ms_hr_employee',
        ]);
    }

    /**
     * ANDALAN attendance location settings stored beside the legacy data.
     */
    public function attendanceLocations(Request $request): JsonResponse
    {
        $query = DB::table('andalan_attendance_locations')->orderByDesc('is_default')->orderBy('location_name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', 1);
        }

        return ApiResponse::success(
            'ANDALAN attendance locations',
            $query->get()->map(fn ($location) => $this->normalizeAndalanLocation($location))->values()
        );
    }

    public function createAttendanceLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_name' => ['required', 'string', 'max:255'],
            'location_code' => ['nullable', 'string', 'max:100'],
            'project_id' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:5000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (($validated['is_default'] ?? false) === true) {
            DB::table('andalan_attendance_locations')->update(['is_default' => 0]);
        }

        $id = DB::table('andalan_attendance_locations')->insertGetId([
            ...$validated,
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success(
            'ANDALAN attendance location created',
            $this->normalizeAndalanLocation(DB::table('andalan_attendance_locations')->where('id', $id)->first()),
            201
        );
    }

    public function updateAttendanceLocation(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'location_name' => ['sometimes', 'string', 'max:255'],
            'location_code' => ['nullable', 'string', 'max:100'],
            'project_id' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'radius_meters' => ['sometimes', 'integer', 'min:10', 'max:5000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $location = DB::table('andalan_attendance_locations')->where('id', $id)->first();
        if (!$location) {
            return ApiResponse::error('Location not found', null, 404);
        }

        if (($validated['is_default'] ?? false) === true) {
            DB::table('andalan_attendance_locations')->update(['is_default' => 0]);
        }

        $validated['updated_at'] = now();
        DB::table('andalan_attendance_locations')->where('id', $id)->update($validated);

        return ApiResponse::success(
            'ANDALAN attendance location updated',
            $this->normalizeAndalanLocation(DB::table('andalan_attendance_locations')->where('id', $id)->first())
        );
    }

    public function deleteAttendanceLocation(int $id): JsonResponse
    {
        $updated = DB::table('andalan_attendance_locations')
            ->where('id', $id)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        if (!$updated) {
            return ApiResponse::error('Location not found', null, 404);
        }

        return ApiResponse::success('ANDALAN attendance location deactivated', [
            'id' => $id,
            'is_active' => false,
        ]);
    }

    public function attendanceMonitoring(Request $request): JsonResponse
    {
        $query = DB::table('andalan_attendance_logs as logs')
            ->leftJoin('andalan_attendance_locations as locations', 'locations.id', '=', 'logs.location_id')
            ->select([
                'logs.*',
                'locations.location_name',
                'locations.location_code',
                'locations.project_id',
            ])
            ->orderByDesc('logs.attendance_date')
            ->orderByDesc('logs.check_in_at');

        if ($request->filled('date')) {
            $query->whereDate('logs.attendance_date', $request->string('date')->toString());
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->string('search')->toString()) . '%';
            $query->where(function ($builder) use ($search) {
                $builder->where('logs.salary_number', 'like', $search)
                    ->orWhere('logs.employee_id', 'like', $search)
                    ->orWhere('logs.employee_name', 'like', $search)
                    ->orWhere('locations.location_name', 'like', $search)
                    ->orWhere('locations.project_id', 'like', $search);
            });
        }

        $perPage = min(max($request->integer('per_page', 10), 1), 100);

        return ApiResponse::success('ANDALAN attendance monitoring', $query->paginate($perPage)->withQueryString());
    }

    public function deleteAttendanceMonitoring(string $id): JsonResponse
    {
        if (! ctype_digit($id)) {
            return ApiResponse::error('Attendance log ID tidak valid', null, 422);
        }

        $deleted = DB::table('andalan_attendance_logs')
            ->where('id', (int) $id)
            ->delete();

        if ($deleted === 0) {
            return ApiResponse::error('Attendance log tidak ditemukan', null, 404);
        }

        return ApiResponse::success('Attendance log berhasil dihapus', [
            'id' => (int) $id,
        ]);
    }

    public function attendanceToday(string $user): JsonResponse
    {
        [$account] = $this->legacyAttendanceContext($user);
        if (!$account) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $salaryNumber = trim((string) $account->UserID);
        $attendance = DB::table('andalan_attendance_logs')
            ->where('salary_number', $salaryNumber)
            ->where('attendance_date', now()->toDateString())
            ->first();

        return ApiResponse::success('ANDALAN attendance today', $attendance);
    }

    public function attendanceHistory(string $user, Request $request): JsonResponse
    {
        [$account] = $this->legacyAttendanceContext($user);
        if (!$account) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $perPage = min(max($request->integer('per_page', 10), 1), 100);
        $history = DB::table('andalan_attendance_logs')
            ->where('salary_number', trim((string) $account->UserID))
            ->orderByDesc('attendance_date')
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponse::success('ANDALAN attendance history', $history);
    }

    /**
     * Create a WebAuthn challenge for registering or using the employee's
     * platform biometric (Face ID, Touch ID, Windows Hello, or Android).
     * Credential data is stored only in ANDALAN tables; MINEPRO remains the
     * identity source.
     */
    public function biometricOptions(string $user, Request $request): JsonResponse
    {
        $action = strtolower($request->string('action', 'authenticate')->toString());
        if (!in_array($action, ['register', 'authenticate'], true)) {
            return ApiResponse::error('Action biometrik tidak valid', null, 422);
        }

        [$account] = $this->legacyAttendanceContext($user);
        if (!$account) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $userId = trim((string) $account->UserID);
        $webauthn = $this->webAuthn();
        $challengeKey = $this->webAuthnChallengeKey($user, $action);

        if ($action === 'register') {
            $existingCredentialIds = DB::table('andalan_webauthn_credentials')
                ->where('user_id', $userId)
                ->pluck('credential_id')
                ->map(fn ($credentialId) => ByteBuffer::fromBase64Url((string) $credentialId))
                ->all();

            $options = $webauthn->getCreateArgs(
                $userId,
                trim((string) ($account->EmailAddress ?: $account->UserID)),
                trim((string) ($account->CompleteUserName ?: $account->UserID)),
                60,
                false,
                true,
                false,
                $existingCredentialIds
            );
        } else {
            $credentials = DB::table('andalan_webauthn_credentials')
                ->where('user_id', $userId)
                ->get();

            if ($credentials->isEmpty()) {
                return ApiResponse::error('Belum ada Face ID/biometrik yang terdaftar. Aktifkan biometrik terlebih dahulu.', null, 422);
            }

            $options = $webauthn->getGetArgs(
                $credentials->map(fn ($credential) => ByteBuffer::fromBase64Url((string) $credential->credential_id))->all(),
                60,
                false,
                false,
                false,
                true,
                true,
                true
            );
        }

        Cache::put($challengeKey, [
            'user_id' => $userId,
            'action' => $action,
            'challenge' => $webauthn->getChallenge()->getBinaryString(),
        ], now()->addMinutes(2));

        return ApiResponse::success('WebAuthn options siap', $options);
    }

    public function biometricRegister(string $user, Request $request): JsonResponse
    {
        [$account, $employee] = $this->legacyAttendanceContext($user);
        if (!$account) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $challenge = Cache::pull($this->webAuthnChallengeKey($user, 'register'));
        if (!$challenge || ($challenge['user_id'] ?? null) !== trim((string) $account->UserID)) {
            return ApiResponse::error('Challenge biometrik kedaluwarsa. Silakan ulangi pendaftaran.', null, 422);
        }

        $credential = $request->input('credential', $request->all());
        $response = is_array($credential) ? ($credential['response'] ?? []) : [];
        $credentialId = is_array($credential) ? trim((string) ($credential['id'] ?? $credential['rawId'] ?? '')) : '';

        if ($credentialId === '' || !is_array($response)) {
            return ApiResponse::error('Data credential biometrik tidak lengkap', null, 422);
        }

        try {
            $result = $this->webAuthn()->processCreate(
                $this->decodeWebAuthnValue($response['clientDataJSON'] ?? ''),
                $this->decodeWebAuthnValue($response['attestationObject'] ?? ''),
                $challenge['challenge'],
                true,
                true,
                true,
                true
            );

            $now = now();
            DB::table('andalan_webauthn_credentials')->updateOrInsert(
                [
                    'user_id' => trim((string) $account->UserID),
                    'credential_id' => $credentialId,
                ],
                [
                    'salary_number' => trim((string) ($employee?->Salary_Number ?: $account->UserID)),
                    'public_key' => (string) $result->credentialPublicKey,
                    'signature_counter' => $result->signatureCounter,
                    'user_verified' => (bool) $result->userVerified,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            return ApiResponse::success('Face ID/biometrik berhasil diaktifkan', [
                'credential_id' => $credentialId,
                'user_verified' => (bool) $result->userVerified,
            ]);
        } catch (WebAuthnException|\Throwable $exception) {
            report($exception);
            return ApiResponse::error('Pendaftaran biometrik gagal. Pastikan Face ID/biometrik disetujui di perangkat.', null, 422);
        }
    }

    public function biometricVerify(string $user, Request $request): JsonResponse
    {
        [$account] = $this->legacyAttendanceContext($user);
        if (!$account) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $challenge = Cache::pull($this->webAuthnChallengeKey($user, 'authenticate'));
        if (!$challenge || ($challenge['user_id'] ?? null) !== trim((string) $account->UserID)) {
            return ApiResponse::error('Challenge biometrik kedaluwarsa. Silakan ulangi verifikasi.', null, 422);
        }

        $credential = $request->input('credential', $request->all());
        $response = is_array($credential) ? ($credential['response'] ?? []) : [];
        $credentialId = is_array($credential) ? trim((string) ($credential['id'] ?? $credential['rawId'] ?? '')) : '';
        $stored = DB::table('andalan_webauthn_credentials')
            ->where('user_id', trim((string) $account->UserID))
            ->where('credential_id', $credentialId)
            ->first();

        if (!$stored || !is_array($response)) {
            return ApiResponse::error('Credential biometrik tidak dikenali', null, 422);
        }

        try {
            $webauthn = $this->webAuthn();
            $webauthn->processGet(
                $this->decodeWebAuthnValue($response['clientDataJSON'] ?? ''),
                $this->decodeWebAuthnValue($response['authenticatorData'] ?? ''),
                $this->decodeWebAuthnValue($response['signature'] ?? ''),
                (string) $stored->public_key,
                $challenge['challenge'],
                $stored->signature_counter === null ? null : (int) $stored->signature_counter,
                true,
                true
            );

            $now = now();
            DB::table('andalan_webauthn_credentials')->where('id', $stored->id)->update([
                'signature_counter' => $webauthn->getSignatureCounter() ?? $stored->signature_counter,
                'last_used_at' => $now,
                'updated_at' => $now,
            ]);

            $token = bin2hex(random_bytes(32));
            Cache::put($this->webAuthnTokenKey($token), [
                'user_id' => trim((string) $account->UserID),
                'credential_id' => $credentialId,
                'verified_at' => $now->toIso8601String(),
            ], now()->addMinutes(2));

            return ApiResponse::success('Biometrik terverifikasi', [
                'biometric_token' => $token,
                'expires_in' => 120,
            ]);
        } catch (WebAuthnException|\Throwable $exception) {
            report($exception);
            return ApiResponse::error('Verifikasi Face ID/biometrik gagal.', null, 422);
        }
    }

    public function attendanceCheckIn(string $user, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'biometric_token' => ['nullable', 'string', 'max:128'],
        ]);

        [$account, $employee] = $this->legacyAttendanceContext($user);
        if (!$account) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }
        if (!$employee) {
            return ApiResponse::error('Employee record not found', null, 404);
        }

        $biometric = $this->consumeBiometricToken($validated['biometric_token'] ?? null, (string) $account->UserID);
        if ($biometric === false) {
            return ApiResponse::error('Verifikasi biometrik wajib dilakukan sebelum check-in', null, 422);
        }

        $salaryNumber = trim((string) ($employee->Salary_Number ?: $account->UserID));
        $existing = DB::table('andalan_attendance_logs')
            ->where('salary_number', $salaryNumber)
            ->where('attendance_date', now()->toDateString())
            ->first();
        if ($existing) {
            return ApiResponse::error('Anda sudah melakukan check-in hari ini', null, 422);
        }

        $location = $this->resolveAndalanAttendanceLocation($employee);
        if (!$location) {
            return ApiResponse::error('Belum ada setting lokasi absensi yang cocok untuk employee ini', null, 422);
        }

        $distance = $this->haversineMeters(
            (float) $location->latitude,
            (float) $location->longitude,
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        if ($distance > (int) $location->radius_meters) {
            return ApiResponse::error(
                'Check-in ditolak karena berada di luar radius lokasi',
                'Jarak Anda ' . $distance . ' meter, radius yang diizinkan ' . $location->radius_meters . ' meter.',
                422
            );
        }

        $now = now();
        $id = DB::table('andalan_attendance_logs')->insertGetId([
            'salary_number' => $salaryNumber,
            'employee_id' => (string) ($employee->Employee_ID ?? ''),
            'employee_name' => (string) ($employee->CompleteName ?? $account->CompleteUserName ?? ''),
            'attendance_date' => $now->toDateString(),
            'location_id' => $location->id,
            'check_in_at' => $now,
            'check_in_latitude' => $validated['latitude'],
            'check_in_longitude' => $validated['longitude'],
            'distance_meters' => $distance,
            'status' => 'on_time',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->recordBiometricEvent($biometric, (int) $id, 'check_in', $salaryNumber);

        return ApiResponse::success('Check-in berhasil', [
            'attendance' => DB::table('andalan_attendance_logs')->where('id', $id)->first(),
            'location' => $location->location_name,
            'distance' => $distance,
        ]);
    }

    public function attendanceCheckOut(string $user, Request $request): JsonResponse
    {
        [$account, $employee] = $this->legacyAttendanceContext($user);
        if (!$account) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $salaryNumber = trim((string) ($employee?->Salary_Number ?: $account->UserID));
        $attendance = DB::table('andalan_attendance_logs')
            ->where('salary_number', $salaryNumber)
            ->where('attendance_date', now()->toDateString())
            ->first();
        if (!$attendance) {
            return ApiResponse::error('Belum ada check-in hari ini', null, 422);
        }
        if ($attendance->check_out_at) {
            return ApiResponse::error('Anda sudah check-out hari ini', null, 422);
        }

        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'biometric_token' => ['nullable', 'string', 'max:128'],
        ]);
        $biometric = $this->consumeBiometricToken($validated['biometric_token'] ?? null, (string) $account->UserID);
        if ($biometric === false) {
            return ApiResponse::error('Verifikasi biometrik wajib dilakukan sebelum check-out', null, 422);
        }
        if ($biometric && ($validated['latitude'] === null || $validated['longitude'] === null)) {
            return ApiResponse::error('GPS wajib dikirim saat check-out dengan biometrik', null, 422);
        }
        if ($biometric && $employee) {
            $location = $this->resolveAndalanAttendanceLocation($employee);
            if (!$location) {
                return ApiResponse::error('Belum ada setting lokasi absensi yang cocok untuk employee ini', null, 422);
            }

            $distance = $this->haversineMeters(
                (float) $location->latitude,
                (float) $location->longitude,
                (float) $validated['latitude'],
                (float) $validated['longitude']
            );
            if ($distance > (int) $location->radius_meters) {
                return ApiResponse::error(
                    'Check-out ditolak karena berada di luar radius lokasi',
                    'Jarak Anda ' . $distance . ' meter, radius yang diizinkan ' . $location->radius_meters . ' meter.',
                    422
                );
            }
        }
        $now = now();
        DB::table('andalan_attendance_logs')->where('id', $attendance->id)->update([
            'check_out_at' => $now,
            'check_out_latitude' => $validated['latitude'] ?? null,
            'check_out_longitude' => $validated['longitude'] ?? null,
            'updated_at' => $now,
        ]);

        $this->recordBiometricEvent($biometric, (int) $attendance->id, 'check_out', $salaryNumber);

        return ApiResponse::success('Check-out berhasil', [
            'attendance' => DB::table('andalan_attendance_logs')->where('id', $attendance->id)->first(),
        ]);
    }

    public function masterData(): JsonResponse
    {
        $departments = DB::table('ms_oshe_general_departemen')
            ->orderBy('departemen')->get()->map(fn ($item) => [
                'id' => (string) $item->id,
                'name' => trim((string) $item->departemen),
                'code' => trim((string) $item->short_dept),
                'company_code' => trim((string) $item->companycode),
                'is_active' => true,
                'legacy_source' => 'ms_oshe_general_departemen',
            ])->values();

        $positions = DB::table('ms_oshe_general_jabatan')
            ->orderBy('jabatan')->get()->map(fn ($item) => [
                'id' => (string) $item->id,
                'name' => trim((string) $item->jabatan),
                'code' => (string) $item->id,
                'is_active' => true,
                'legacy_source' => 'ms_oshe_general_jabatan',
            ])->values();

        $locations = DB::table('ms_pc_location_pit')
            ->orderBy('PitName')->get()->map(fn ($item) => [
                'id' => (string) $item->PitID,
                'name' => trim((string) $item->PitName),
                'description' => trim((string) ($item->PitDescription ?? '')) ?: null,
                'code' => trim((string) ($item->ProjectID ?? '')) ?: null,
                'project_id' => trim((string) ($item->ProjectID ?? '')) ?: null,
                'is_active' => (string) $item->IsActive === '1',
                'legacy_source' => 'ms_pc_location_pit',
            ])->values();

        return ApiResponse::success('Legacy MINEPRO master data', [
            'departments' => $departments,
            'positions' => $positions,
            'locations' => $locations,
            'company' => DB::table('ms_company_code')->first(),
        ]);
    }

    private function normalizeLegacyEmployee(object $employee): array
    {
        $data = (array) $employee;
        $status = strtolower(trim((string) ($employee->status_employee ?? '')));

        return array_merge($data, [
            'id' => $employee->Employee_ID,
            'user_id' => $employee->UserID,
            'employee_code' => $employee->Employee_ID,
            'position' => $employee->PositionDesc,
            'department' => $employee->Department,
            'hire_date' => $employee->tgl_hire,
            'termination_date' => $employee->tgl_resign,
            'status' => str_contains($status, 'aktif') && !str_contains($status, 'non')
                ? 'active'
                : 'inactive',
            'salary' => null,
            'user' => [
                'id' => $employee->UserID,
                'name' => $employee->CompleteName,
                'email' => $employee->EmailAddress,
            ],
        ]);
    }

    private function normalizeAndalanLocation(?object $location): ?array
    {
        if (!$location) {
            return null;
        }

        return [
            'id' => $location->id,
            'name' => $location->location_name,
            'location_name' => $location->location_name,
            'location_code' => $location->location_code,
            'project_id' => $location->project_id,
            'department' => $location->department,
            'description' => $location->description,
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'radius' => (int) $location->radius_meters,
            'radius_meters' => (int) $location->radius_meters,
            'is_default' => (bool) $location->is_default,
            'is_active' => (bool) $location->is_active,
            'created_at' => $location->created_at,
            'updated_at' => $location->updated_at,
            'legacy_source' => 'andalan_attendance_locations',
        ];
    }

    private function legacyAttendanceContext(string $user): array
    {
        $account = DB::table('ms_sa_permission')
            ->select(['UserID', 'EmailAddress', 'CompleteUserName'])
            ->whereRaw('LOWER(LTRIM(RTRIM(UserID))) = LOWER(?)', [$user])
            ->first();

        if (!$account) {
            return [null, null];
        }

        $employee = DB::table('ms_hr_employee')
            ->where(function ($query) use ($account, $user) {
                $query->whereRaw('LOWER(LTRIM(RTRIM(Salary_Number))) = LOWER(?)', [$user])
                    ->orWhereRaw('LOWER(LTRIM(RTRIM(UserID))) = LOWER(?)', [(string) $account->UserID])
                    ->orWhereRaw('LOWER(LTRIM(RTRIM(EmailAddress))) = LOWER(?)', [(string) ($account->EmailAddress ?? '')]);
            })
            ->first();

        return [$account, $employee];
    }

    private function resolveAndalanAttendanceLocation(object $employee): ?object
    {
        $locations = DB::table('andalan_attendance_locations')
            ->where('is_active', 1)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $projectId = trim((string) ($employee->ProjectID ?? ''));
        $locationCode = trim((string) ($employee->InputLocation ?? ''));
        if ($locationCode === '') {
            $locationCode = trim((string) ($employee->ServerID ?? ''));
        }
        $department = trim((string) ($employee->Department ?? ''));

        return $locations->first(function ($location) use ($projectId, $locationCode, $department) {
            return ($projectId !== '' && strcasecmp(trim((string) $location->project_id), $projectId) === 0)
                || ($locationCode !== '' && strcasecmp(trim((string) $location->location_code), $locationCode) === 0)
                || ($department !== '' && strcasecmp(trim((string) $location->department), $department) === 0);
        }) ?: $locations->firstWhere('is_default', 1) ?: $locations->first();
    }

    private function haversineMeters(float $latitude1, float $longitude1, float $latitude2, float $longitude2): int
    {
        $earthRadius = 6371000;
        $deltaLatitude = deg2rad($latitude2 - $latitude1);
        $deltaLongitude = deg2rad($longitude2 - $longitude1);
        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($deltaLongitude / 2) ** 2;

        return (int) round($earthRadius * 2 * asin(min(1, sqrt($a))));
    }

    private function webAuthn(): WebAuthn
    {
        // Same-origin GET requests may not include Origin; Referer keeps local
        // Vite/PWA development working when APP_URL still points to staging.
        $origin = request()->headers->get('origin')
            ?: request()->headers->get('referer')
            ?: config('app.url');
        $originHost = parse_url((string) $origin, PHP_URL_HOST);
        $rpId = trim((string) env('WEBAUTHN_RP_ID', '')) ?: ((string) $originHost ?: 'localhost');
        $rpName = trim((string) env('WEBAUTHN_RP_NAME', 'ANDARA')) ?: 'ANDARA';

        return new WebAuthn($rpName, $rpId, ['none'], true);
    }

    private function webAuthnChallengeKey(string $user, string $action): string
    {
        return 'andalan:webauthn:challenge:' . $action . ':' . sha1(strtolower(trim($user)));
    }

    private function webAuthnTokenKey(string $token): string
    {
        return 'andalan:webauthn:verified:' . hash('sha256', $token);
    }

    private function decodeWebAuthnValue(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new WebAuthnException('Data WebAuthn kosong', WebAuthnException::INVALID_DATA);
        }

        return ByteBuffer::fromBase64Url($value)->getBinaryString();
    }

    /**
     * Returns false for an explicitly invalid token, null when no token was
     * sent (keeps the existing native/mobile attendance flow compatible), and
     * the verified credential payload for a valid token.
     */
    private function consumeBiometricToken(?string $token, string $userId): array|false|null
    {
        if (!$token) {
            return null;
        }

        $verified = Cache::pull($this->webAuthnTokenKey($token));
        if (!$verified || ($verified['user_id'] ?? null) !== trim($userId)) {
            return false;
        }

        return $verified;
    }

    private function recordBiometricEvent(?array $verified, int $attendanceId, string $operation, string $salaryNumber): void
    {
        if (!$verified) {
            return;
        }

        try {
            DB::table('andalan_attendance_biometric_events')->insert([
                'attendance_log_id' => $attendanceId,
                'user_id' => $verified['user_id'],
                'salary_number' => trim($salaryNumber),
                'operation' => $operation,
                'credential_id' => $verified['credential_id'] ?? null,
                'verified_at' => $verified['verified_at'] ?? now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // Attendance is already valid; keep the audit insert non-blocking
            // for deployments that have not run the optional audit SQL yet.
            report($exception);
        }
    }
}
