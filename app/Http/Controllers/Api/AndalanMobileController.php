<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AndalanMobileController extends Controller
{
    public function news(Request $request): JsonResponse
    {
        $limit = min(max($request->integer('limit', 10), 1), 50);
        $items = DB::table('andalan_news')
            ->where('is_published', 1)
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return ApiResponse::success('ANDALAN mobile news', $items);
    }

    public function createNews(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:100'],
            'cover_image_url' => ['nullable', 'url', 'max:1000'],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'created_by_user_id' => ['nullable', 'string', 'max:255'],
        ]);

        $isPublished = (bool) ($validated['is_published'] ?? true);
        $id = DB::table('andalan_news')->insertGetId([
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'content' => $validated['content'] ?? null,
            'category' => $validated['category'] ?? 'general',
            'cover_image_url' => $validated['cover_image_url'] ?? null,
            'is_published' => $isPublished,
            'published_at' => $isPublished ? ($validated['published_at'] ?? now()) : ($validated['published_at'] ?? null),
            'created_by_user_id' => $validated['created_by_user_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Berita ANDARA berhasil dibuat', [
            'news' => DB::table('andalan_news')->where('id', $id)->first(),
        ], 201);
    }

    public function updateNews(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'summary' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string', 'max:10000'], 'category' => ['nullable', 'string', 'max:100'],
            'cover_image_url' => ['nullable', 'url', 'max:1000'], 'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'], 'updated_by_user_id' => ['nullable', 'string', 'max:255'],
        ]);
        if (!DB::table('andalan_news')->where('id', $id)->exists()) return ApiResponse::error('Berita tidak ditemukan.', null, 404);
        $isPublished = (bool) ($validated['is_published'] ?? true);
        DB::table('andalan_news')->where('id', $id)->update([
            'title' => $validated['title'], 'summary' => $validated['summary'] ?? null,
            'content' => $validated['content'] ?? null, 'category' => $validated['category'] ?? 'general',
            'cover_image_url' => $validated['cover_image_url'] ?? null, 'is_published' => $isPublished,
            'published_at' => $isPublished ? ($validated['published_at'] ?? now()) : ($validated['published_at'] ?? null),
            'updated_at' => now(),
        ]);
        return ApiResponse::success('Berita ANDARA berhasil diperbarui', ['news' => DB::table('andalan_news')->where('id', $id)->first()]);
    }

    /**
     * Read-only mobile profile context. This endpoint only assembles existing
     * MINEPRO employee/account data and the ANDALAN attendance location.
     */
    public function mobileProfile(string $user): JsonResponse
    {
        $identity = $this->identity($user);
        if (!$identity) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $account = DB::table('ms_sa_permission')
            ->select(['UserID', 'EmailAddress', 'CompleteUserName', 'isActiveUser'])
            ->whereRaw('LOWER(LTRIM(RTRIM(UserID))) = LOWER(?)', [$identity['user_id']])
            ->first();

        // Prefer the exact salary number supplied by the legacy login. Some
        // MINEPRO rows can share an empty UserID, so a broad OR query may pick
        // another employee before reaching the exact salary-number match.
        $employee = DB::table('ms_hr_employee')
            ->whereRaw('LOWER(LTRIM(RTRIM(Salary_Number))) = LOWER(?)', [$user])
            ->first();
        $employee ??= DB::table('ms_hr_employee')
            ->whereRaw('LOWER(LTRIM(RTRIM(UserID))) = LOWER(?)', [$identity['user_id']])
            ->first();
        $employee ??= DB::table('ms_hr_employee')
            ->whereRaw('LOWER(LTRIM(RTRIM(EmailAddress))) = LOWER(?)', [(string) ($account->EmailAddress ?? '')])
            ->first();

        $location = null;
        if ($employee) {
            $projectId = trim((string) ($employee->ProjectID ?? ''));
            $locationCode = trim((string) ($employee->InputLocation ?? ''));
            $department = trim((string) ($employee->Department ?? ''));

            $location = DB::table('andalan_attendance_locations')
                ->where('is_active', 1)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->get()
                ->first(function ($item) use ($projectId, $locationCode, $department) {
                    return ($projectId !== '' && strcasecmp(trim((string) $item->project_id), $projectId) === 0)
                        || ($locationCode !== '' && strcasecmp(trim((string) $item->location_code), $locationCode) === 0)
                        || ($department !== '' && strcasecmp(trim((string) $item->department), $department) === 0);
                });

            $location ??= DB::table('andalan_attendance_locations')
                ->where('is_active', 1)
                ->where('is_default', 1)
                ->first();
        }

        $localEmployeeId = $employee
            ? DB::table('employees')->where('employee_code', trim((string) $employee->Employee_ID))->value('id')
            : null;
        $balances = $localEmployeeId
            ? DB::table('employee_leave_balances')
                ->where('employee_id', $localEmployeeId)
                ->where('year', now()->year)
                ->orderBy('leave_type')
                ->get()
                ->map(fn ($balance) => [
                    'leave_type' => $balance->leave_type,
                    'year' => (int) $balance->year,
                    'allocated_days' => (int) $balance->allocated_days,
                    'carry_over_days' => (int) $balance->carry_over_days,
                    'used_days' => (int) $balance->used_days,
                    'pending_days' => (int) $balance->pending_days,
                    'available_days' => max(0, ((int) $balance->allocated_days + (int) $balance->carry_over_days) - (int) $balance->used_days - (int) $balance->pending_days),
                ])
                ->values()
            : collect();

        return ApiResponse::success('ANDALAN mobile profile context', [
            'account' => $account ? [
                'user_id' => trim((string) $account->UserID),
                'name' => trim((string) ($account->CompleteUserName ?? '')),
                'email' => trim((string) ($account->EmailAddress ?? '')),
                'is_active' => (bool) $account->isActiveUser,
            ] : null,
            'employee' => $employee ? [
                'employee_id' => trim((string) ($employee->Employee_ID ?? '')),
                'salary_number' => trim((string) ($employee->Salary_Number ?? '')),
                'name' => trim((string) ($employee->CompleteName ?? '')),
                'position' => trim((string) ($employee->PositionDesc ?? '')) ?: null,
                'department' => trim((string) ($employee->Department ?? '')) ?: null,
                'project_id' => trim((string) ($employee->ProjectID ?? '')) ?: null,
                'location_code' => trim((string) ($employee->InputLocation ?? '')) ?: null,
                'hire_date' => $employee->tgl_hire ?? null,
                'employment_status' => trim((string) ($employee->status_employee ?? '')) ?: null,
                'hire_status' => trim((string) ($employee->status_hire ?? '')) ?: null,
            ] : null,
            'attendance_location' => $location ? [
                'id' => $location->id,
                'name' => $location->location_name,
                'location_code' => $location->location_code,
                'project_id' => $location->project_id,
                'department' => $location->department,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'radius_meters' => (int) $location->radius_meters,
            ] : null,
            'leave_balances' => $balances,
            'legacy_source' => 'ms_sa_permission + ms_hr_employee',
        ]);
    }

    public function roster(Request $request): JsonResponse
    {
        $from = $request->date('from')?->toDateString() ?? now()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->copy()->addDays(30)->toDateString();

        $items = DB::table('andalan_rosters')
            ->whereBetween('roster_date', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->orderBy('roster_date')
            ->orderBy('employee_name')
            ->limit(500)
            ->get();

        return ApiResponse::success('ANDALAN roster cuti', $items);
    }

    public function createRoster(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'roster_date' => ['required', 'date_format:Y-m-d'],
            'user_id' => ['nullable', 'string', 'max:255'],
            'salary_number' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:255'],
            'employee_name' => ['required', 'string', 'max:255'],
            'roster_type' => ['nullable', 'string', 'max:100'],
            'leave_type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:planned,approved,cancelled'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'created_by_user_id' => ['nullable', 'string', 'max:255'],
        ]);

        $id = DB::table('andalan_rosters')->insertGetId([
            'roster_date' => $validated['roster_date'],
            'user_id' => $validated['user_id'] ?? null,
            'salary_number' => $validated['salary_number'] ?? null,
            'employee_id' => $validated['employee_id'] ?? null,
            'employee_name' => $validated['employee_name'],
            'roster_type' => $validated['roster_type'] ?? 'shift',
            'leave_type' => $validated['leave_type'] ?? null,
            'status' => $validated['status'] ?? 'planned',
            'notes' => $validated['notes'] ?? null,
            'created_by_user_id' => $validated['created_by_user_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Roster ANDARA berhasil dibuat', [
            'roster' => DB::table('andalan_rosters')->where('id', $id)->first(),
        ], 201);
    }

    public function updateRoster(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'roster_date' => ['required', 'date_format:Y-m-d'], 'user_id' => ['nullable', 'string', 'max:255'],
            'salary_number' => ['nullable', 'string', 'max:255'], 'employee_id' => ['nullable', 'string', 'max:255'],
            'employee_name' => ['required', 'string', 'max:255'], 'roster_type' => ['nullable', 'string', 'max:100'],
            'leave_type' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'in:planned,approved,cancelled'],
            'notes' => ['nullable', 'string', 'max:2000'], 'updated_by_user_id' => ['nullable', 'string', 'max:255'],
        ]);
        if (!DB::table('andalan_rosters')->where('id', $id)->exists()) return ApiResponse::error('Roster tidak ditemukan.', null, 404);
        DB::table('andalan_rosters')->where('id', $id)->update([
            'roster_date' => $validated['roster_date'], 'user_id' => $validated['user_id'] ?? null,
            'salary_number' => $validated['salary_number'] ?? null, 'employee_id' => $validated['employee_id'] ?? null,
            'employee_name' => $validated['employee_name'], 'roster_type' => $validated['roster_type'] ?? 'shift',
            'leave_type' => $validated['leave_type'] ?? null, 'status' => $validated['status'] ?? 'planned',
            'notes' => $validated['notes'] ?? null, 'updated_at' => now(),
        ]);
        return ApiResponse::success('Roster ANDARA berhasil diperbarui', ['roster' => DB::table('andalan_rosters')->where('id', $id)->first()]);
    }

    public function leaves(string $user): JsonResponse
    {
        $identity = $this->identity($user);
        if (!$identity) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $items = DB::table('andalan_leave_requests')
            ->where('user_id', $identity['user_id'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return ApiResponse::success('ANDALAN mobile leave requests', $items);
    }

    public function createLeave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'max:255'],
            'leave_type' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $identity = $this->identity($validated['user_id']);
        if (!$identity) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $totalDays = Carbon::parse($validated['start_date'])->diffInDays(Carbon::parse($validated['end_date'])) + 1;
        $id = DB::table('andalan_leave_requests')->insertGetId([
            ...$identity,
            'leave_type' => $validated['leave_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'total_days' => $totalDays,
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->notify($identity['user_id'], 'Pengajuan cuti dibuat', 'Pengajuan ' . $validated['leave_type'] . ' menunggu proses approval.', 'leave', $id);

        return ApiResponse::success('Pengajuan cuti berhasil dibuat', [
            'request' => DB::table('andalan_leave_requests')->where('id', $id)->first(),
        ], 201);
    }

    public function reimbursements(string $user): JsonResponse
    {
        $identity = $this->identity($user);
        if (!$identity) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $items = DB::table('andalan_reimbursements')
            ->where('user_id', $identity['user_id'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return ApiResponse::success('ANDALAN mobile reimbursements', $items);
    }

    public function createReimbursement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:2000'],
            'receipt_url' => ['nullable', 'url', 'max:1000'],
        ]);

        $identity = $this->identity($validated['user_id']);
        if (!$identity) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $id = DB::table('andalan_reimbursements')->insertGetId([
            ...$identity,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'description' => $validated['description'] ?? null,
            'receipt_url' => $validated['receipt_url'] ?? null,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->notify($identity['user_id'], 'Draft reimburse dibuat', 'Klaim ' . $validated['title'] . ' tersimpan sebagai draft.', 'reimbursement', $id);

        return ApiResponse::success('Pengajuan reimbursement berhasil dibuat', [
            'request' => DB::table('andalan_reimbursements')->where('id', $id)->first(),
        ], 201);
    }

    public function businessTrips(string $user): JsonResponse
    {
        $identity = $this->identity($user);
        if (!$identity) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $items = DB::table('andalan_business_trips')
            ->where('user_id', $identity['user_id'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return ApiResponse::success('ANDALAN mobile business trips', $items);
    }

    public function createBusinessTrip(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'estimated_amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $identity = $this->identity($validated['user_id']);
        if (!$identity) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $totalDays = Carbon::parse($validated['start_date'])->diffInDays(Carbon::parse($validated['end_date'])) + 1;
        $id = DB::table('andalan_business_trips')->insertGetId([
            ...$identity,
            'purpose' => $validated['purpose'],
            'destination' => $validated['destination'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'total_days' => $totalDays,
            'estimated_amount' => $validated['estimated_amount'] ?? 0,
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->notify($identity['user_id'], 'Pengajuan perjalanan dibuat', 'Perjalanan ke ' . $validated['destination'] . ' menunggu proses approval.', 'business_trip', $id);

        return ApiResponse::success('Pengajuan perjalanan dinas berhasil dibuat', [
            'request' => DB::table('andalan_business_trips')->where('id', $id)->first(),
        ], 201);
    }

    public function requests(string $user): JsonResponse
    {
        $identity = $this->identity($user);
        if (!$identity) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $leaves = DB::table('andalan_leave_requests')->where('user_id', $identity['user_id'])->get()->map(fn ($item) => [
            ...((array) $item), 'request_type' => 'leave', 'request_label' => 'Cuti & Izin',
        ]);
        $reimbursements = DB::table('andalan_reimbursements')->where('user_id', $identity['user_id'])->get()->map(fn ($item) => [
            ...((array) $item), 'request_type' => 'reimbursement', 'request_label' => 'Reimburse',
        ]);
        $trips = DB::table('andalan_business_trips')->where('user_id', $identity['user_id'])->get()->map(fn ($item) => [
            ...((array) $item), 'request_type' => 'business_trip', 'request_label' => 'Perjalanan Dinas',
        ]);

        return ApiResponse::success('ANDALAN mobile request history', $leaves->concat($reimbursements)->concat($trips)->sortByDesc('created_at')->values());
    }

    public function notifications(string $user): JsonResponse
    {
        $identity = $this->identity($user);
        if (!$identity) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        return ApiResponse::success('ANDALAN mobile notifications', DB::table('andalan_notifications')
            ->where('recipient_key', $identity['user_id'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get());
    }

    public function markNotificationRead(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'string', 'max:255']]);
        $identity = $this->identity($validated['user_id']);
        if (!$identity) {
            return ApiResponse::error('Legacy user not found', null, 404);
        }

        $updated = DB::table('andalan_notifications')
            ->where('id', $id)
            ->where('recipient_key', $identity['user_id'])
            ->update(['is_read' => true, 'read_at' => now(), 'updated_at' => now()]);

        return $updated
            ? ApiResponse::success('Notifikasi ditandai sudah dibaca', DB::table('andalan_notifications')->where('id', $id)->first())
            : ApiResponse::error('Notifikasi tidak ditemukan', null, 404);
    }

    private function notify(string $recipientKey, string $title, string $message, string $sourceType, int $sourceId): void
    {
        DB::table('andalan_notifications')->insert([
            'recipient_key' => $recipientKey,
            'title' => $title,
            'message' => $message,
            'type' => 'request',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function identity(string $user): ?array
    {
        $login = trim($user);
        $account = DB::table('ms_sa_permission')
            ->where(function ($query) use ($login) {
                $query->whereRaw('LOWER(LTRIM(RTRIM(UserID))) = LOWER(?)', [$login])
                    ->orWhereRaw('LOWER(LTRIM(RTRIM(EmailAddress))) = LOWER(?)', [$login]);
            })
            ->first();

        if (!$account) {
            return null;
        }

        $employee = DB::table('ms_hr_employee')
            ->where(function ($query) use ($account, $login) {
                $query->whereRaw('LOWER(LTRIM(RTRIM(UserID))) = LOWER(?)', [(string) $account->UserID])
                    ->orWhereRaw('LOWER(LTRIM(RTRIM(Salary_Number))) = LOWER(?)', [$login])
                    ->orWhereRaw('LOWER(LTRIM(RTRIM(EmailAddress))) = LOWER(?)', [(string) ($account->EmailAddress ?? '')]);
            })
            ->first();

        return [
            'user_id' => trim((string) $account->UserID),
            'salary_number' => trim((string) ($employee->Salary_Number ?? $account->UserID)),
            'employee_id' => trim((string) ($employee->Employee_ID ?? '')) ?: null,
            'employee_name' => trim((string) ($employee->CompleteName ?? $account->CompleteUserName ?? $account->UserID)),
        ];
    }
}
