<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AndalanKpiController extends Controller
{
    private const REVIEW_STEPS = [
        'supervisor_review' => 'supervisor',
        'head_review' => 'head',
        'hr_review' => 'hr',
        'director_review' => 'director',
    ];

    public function index(Request $request): JsonResponse
    {
        $limit = min(max($request->integer('limit', 100), 1), 200);
        $cycles = DB::table('andalan_kpi_cycles')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($cycle) => $this->summary($cycle))
            ->values();

        return ApiResponse::success('ANDALAN KPI cycles', $cycles);
    }

    public function forUser(string $user): JsonResponse
    {
        $login = trim($user);
        $items = DB::table('andalan_kpi_items')
            ->where(function ($query) use ($login) {
                $query->where('user_id', $login)
                    ->orWhere('salary_number', $login)
                    ->orWhere('employee_id', $login);
            })
            ->orderBy('cycle_id')
            ->orderBy('id')
            ->get();

        $cycleIds = $items->pluck('cycle_id')->unique()->values();
        $cycles = DB::table('andalan_kpi_cycles')
            ->whereIn('id', $cycleIds)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(function ($cycle) use ($items) {
                return [
                    'cycle' => $this->summary($cycle),
                    'items' => $items->where('cycle_id', $cycle->id)->values(),
                    'approvals' => DB::table('andalan_kpi_approvals')
                        ->where('cycle_id', $cycle->id)
                        ->orderBy('id')
                        ->get(),
                ];
            })
            ->values();

        return ApiResponse::success('KPI ANDARA saya', $cycles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedCycle($request);
        $this->validateWeights($validated['items']);

        $cycleId = DB::transaction(function () use ($validated, $request) {
            $now = now();
            $cycleId = DB::table('andalan_kpi_cycles')->insertGetId([
                'name' => $validated['name'],
                'period_type' => $validated['period_type'],
                'period_label' => $validated['period_label'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by_user_id' => $this->actorId($request),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($validated['items'] as $item) {
                DB::table('andalan_kpi_items')->insert($this->itemData($cycleId, $item, $now));
            }

            return $cycleId;
        });

        return ApiResponse::success('Siklus KPI ANDARA berhasil dibuat', $this->detail($cycleId), 201);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success('Detail siklus KPI ANDARA', $this->detail($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $cycle = $this->cycle($id);
        if (!in_array($cycle->status, ['draft', 'rejected'], true)) {
            return ApiResponse::error('KPI yang sudah masuk proses approval tidak dapat diedit.', null, 409);
        }

        $validated = $this->validatedCycle($request);
        $this->validateWeights($validated['items']);

        DB::transaction(function () use ($validated, $request, $id) {
            $now = now();
            DB::table('andalan_kpi_cycles')->where('id', $id)->update([
                'name' => $validated['name'],
                'period_type' => $validated['period_type'],
                'period_label' => $validated['period_label'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'updated_by_user_id' => $this->actorId($request),
                'updated_at' => $now,
            ]);
            DB::table('andalan_kpi_items')->where('cycle_id', $id)->delete();
            foreach ($validated['items'] as $item) {
                DB::table('andalan_kpi_items')->insert($this->itemData($id, $item, $now));
            }
        });

        return ApiResponse::success('Siklus KPI ANDARA berhasil diperbarui', $this->detail($id));
    }

    public function destroy(int $id): JsonResponse
    {
        $cycle = $this->cycle($id);
        if (!in_array($cycle->status, ['draft', 'rejected'], true)) {
            return ApiResponse::error('Hanya KPI draft atau rejected yang dapat dihapus.', null, 409);
        }

        DB::table('andalan_kpi_cycles')->where('id', $id)->delete();
        return ApiResponse::success('Siklus KPI ANDARA berhasil dihapus');
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $cycle = $this->cycle($id);
        if (!in_array($cycle->status, ['draft', 'rejected'], true)) {
            return ApiResponse::error('KPI hanya dapat diajukan dari status draft atau rejected.', null, 409);
        }
        if (!DB::table('andalan_kpi_items')->where('cycle_id', $id)->exists()) {
            return ApiResponse::error('KPI belum memiliki indikator.', null, 422);
        }

        DB::transaction(function () use ($id, $request) {
            $now = now();
            DB::table('andalan_kpi_cycles')->where('id', $id)->update([
                'status' => 'supervisor_review',
                'updated_by_user_id' => $this->actorId($request),
                'updated_at' => $now,
            ]);
            DB::table('andalan_kpi_items')->where('cycle_id', $id)->update([
                'status' => 'submitted',
                'updated_at' => $now,
            ]);
            $this->recordApproval($id, 'employee', 'submitted', $request, null, $now);
        });

        return ApiResponse::success('KPI berhasil diajukan ke Supervisor', $this->detail($id));
    }

    public function selfAssessment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'max:255'],
            'submit' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:andalan_kpi_items,id'],
            'items.*.achievement' => ['required', 'numeric', 'min:0'],
            'items.*.employee_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $cycle = $this->cycle($id);
        if (!in_array($cycle->status, ['draft', 'rejected', 'supervisor_review'], true)) {
            return ApiResponse::error('Self-assessment tidak dapat diubah pada status KPI ini.', null, 409);
        }

        DB::transaction(function () use ($validated, $id, $request) {
            $now = now();
            foreach ($validated['items'] as $itemData) {
                $item = DB::table('andalan_kpi_items')
                    ->where('id', $itemData['id'])
                    ->where('cycle_id', $id)
                    ->first();

                if (!$item || !$this->matchesIdentity($item, $validated['user_id'])) {
                    throw ValidationException::withMessages([
                        'items' => ['Salah satu KPI bukan milik karyawan yang sedang login.'],
                    ]);
                }

                $score = (float) $item->target > 0
                    ? ((float) $itemData['achievement'] / (float) $item->target) * 100
                    : 0;

                DB::table('andalan_kpi_items')->where('id', $item->id)->update([
                    'achievement' => $itemData['achievement'],
                    'score' => round($score, 2),
                    'employee_comment' => $itemData['employee_comment'] ?? null,
                    'status' => !empty($validated['submit']) ? 'submitted' : 'self_assessment',
                    'updated_at' => $now,
                ]);
            }

            if (!empty($validated['submit'])) {
                DB::table('andalan_kpi_cycles')->where('id', $id)->update([
                    'status' => 'supervisor_review',
                    'updated_by_user_id' => $this->actorId($request) ?? $validated['user_id'],
                    'updated_at' => $now,
                ]);
                $this->recordApproval($id, 'employee', 'submitted', $request, null, $now);
            }
        });

        return ApiResponse::success(
            !empty($validated['submit']) ? 'Self-assessment KPI berhasil diajukan.' : 'Realisasi KPI berhasil disimpan.',
            $this->detail($id)
        );
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $cycle = $this->cycle($id);
        $expectedRole = self::REVIEW_STEPS[$cycle->status] ?? null;
        if (!$expectedRole) {
            return ApiResponse::error('KPI tidak sedang menunggu approval.', null, 409);
        }

        $validated = $request->validate([
            'action' => ['sometimes', 'in:approved,returned,rejected'],
            'approval_role' => ['sometimes', 'in:supervisor,head,hr,director'],
            'note' => ['nullable', 'string', 'max:2000'],
            'actor_user_id' => ['required', 'string', 'max:255'],
        ]);
        $actor = $this->legacyAccount($validated['actor_user_id']);
        if (!$actor || !(bool) ($actor->isActiveUser ?? false)) {
            return ApiResponse::error('Approver KPI tidak aktif atau tidak terdaftar di MINEPRO.', null, 403);
        }
        $role = $validated['approval_role'] ?? $expectedRole;
        if ($role !== $expectedRole) {
            return ApiResponse::error("Approval harus dilakukan oleh role {$expectedRole} pada tahap ini.", null, 403);
        }

        $action = $validated['action'] ?? 'approved';
        $nextStatus = match ($action) {
            'returned' => 'draft',
            'rejected' => 'rejected',
            default => match ($cycle->status) {
                'supervisor_review' => 'head_review',
                'head_review' => 'hr_review',
                'hr_review' => 'director_review',
                'director_review' => 'finalized',
                default => 'rejected',
            },
        };

        DB::transaction(function () use ($id, $request, $role, $action, $nextStatus, $validated, $cycle) {
            $now = now();
            DB::table('andalan_kpi_cycles')->where('id', $id)->update([
                'status' => $nextStatus,
                'updated_by_user_id' => $this->actorId($request),
                'updated_at' => $now,
            ]);
            DB::table('andalan_kpi_items')->where('cycle_id', $id)->update([
                'status' => $nextStatus === 'finalized' ? 'finalized' : ($action === 'rejected' ? 'rejected' : 'submitted'),
                'updated_at' => $now,
            ]);
            if ($nextStatus === 'finalized') {
                $this->calculateScores($id);
            }
            $this->recordApproval($id, $role, $action, $request, $validated['note'] ?? null, $now);
        });

        $message = match ($action) {
            'returned' => 'KPI dikembalikan untuk revisi.',
            'rejected' => 'KPI ditolak.',
            default => $nextStatus === 'finalized' ? 'KPI berhasil difinalisasi.' : "KPI disetujui dan diteruskan ke {$nextStatus}.",
        };

        return ApiResponse::success($message, $this->detail($id));
    }

    private function validatedCycle(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'period_type' => ['required', 'in:monthly,quarterly,semi_annual,annual'],
            'period_label' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.user_id' => ['nullable', 'string', 'max:255'],
            'items.*.salary_number' => ['nullable', 'string', 'max:255'],
            'items.*.employee_id' => ['nullable', 'string', 'max:255'],
            'items.*.employee_name' => ['required', 'string', 'max:255'],
            'items.*.department' => ['nullable', 'string', 'max:255'],
            'items.*.position' => ['nullable', 'string', 'max:255'],
            'items.*.indicator' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.category' => ['nullable', 'string', 'max:100'],
            'items.*.measurement_method' => ['nullable', 'string', 'max:100'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.target' => ['required', 'numeric', 'min:0'],
            'items.*.achievement' => ['nullable', 'numeric', 'min:0'],
            'items.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.evidence_url' => ['nullable', 'url', 'max:1000'],
        ]);
    }

    private function validateWeights(array $items): void
    {
        $weights = collect($items)->groupBy(fn (array $item) => (string) ($item['employee_id'] ?? $item['salary_number'] ?? $item['employee_name']))
            ->map(fn ($group) => round($group->sum(fn (array $item) => (float) ($item['weight'] ?? 0)), 2));

        if ($weights->contains(fn (float $weight) => $weight > 100)) {
            throw ValidationException::withMessages([
                'items' => ['Total bobot KPI untuk satu karyawan tidak boleh lebih dari 100%.'],
            ]);
        }
    }

    private function itemData(int $cycleId, array $item, $now): array
    {
        return [
            'cycle_id' => $cycleId,
            'user_id' => $item['user_id'] ?? null,
            'salary_number' => $item['salary_number'] ?? null,
            'employee_id' => $item['employee_id'] ?? null,
            'employee_name' => $item['employee_name'],
            'department' => $item['department'] ?? null,
            'position' => $item['position'] ?? null,
            'indicator' => $item['indicator'],
            'description' => $item['description'] ?? null,
            'category' => $item['category'] ?? null,
            'measurement_method' => $item['measurement_method'] ?? 'manual',
            'unit' => $item['unit'] ?? null,
            'target' => $item['target'],
            'achievement' => $item['achievement'] ?? 0,
            'score' => 0,
            'weight' => $item['weight'],
            'status' => 'draft',
            'evidence_url' => $item['evidence_url'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function calculateScores(int $cycleId): void
    {
        DB::table('andalan_kpi_items')->where('cycle_id', $cycleId)->get()->each(function ($item) {
            $score = (float) $item->target > 0 ? ((float) $item->achievement / (float) $item->target) * 100 : 0;
            DB::table('andalan_kpi_items')->where('id', $item->id)->update([
                'score' => round($score, 2),
                'updated_at' => now(),
            ]);
        });
    }

    private function recordApproval(int $cycleId, string $role, string $action, Request $request, ?string $note, $now): void
    {
        DB::table('andalan_kpi_approvals')->insert([
            'cycle_id' => $cycleId,
            'approval_role' => $role,
            'action' => $action,
            'actor_user_id' => $this->actorId($request),
            'actor_name' => $request->user()?->name ?? $request->input('actor_name'),
            'note' => $note,
            'acted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function summary(object $cycle): array
    {
        return [
            ...get_object_vars($cycle),
            'item_count' => DB::table('andalan_kpi_items')->where('cycle_id', $cycle->id)->count(),
            'employee_count' => DB::table('andalan_kpi_items')->where('cycle_id', $cycle->id)->distinct('employee_id')->count('employee_id'),
        ];
    }

    private function detail(int $id): array
    {
        $cycle = $this->cycle($id);
        return [
            'cycle' => $this->summary($cycle),
            'items' => DB::table('andalan_kpi_items')->where('cycle_id', $id)->orderBy('employee_name')->orderBy('id')->get(),
            'approvals' => DB::table('andalan_kpi_approvals')->where('cycle_id', $id)->orderBy('id')->get(),
        ];
    }

    private function cycle(int $id): object
    {
        $cycle = DB::table('andalan_kpi_cycles')->where('id', $id)->first();
        if (!$cycle) {
            abort(404, 'Siklus KPI ANDARA tidak ditemukan.');
        }
        return $cycle;
    }

    private function actorId(Request $request): ?string
    {
        $id = $request->user()?->id ?? $request->input('actor_user_id');
        return $id === null || $id === '' ? null : (string) $id;
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

    private function matchesIdentity(object $item, string $user): bool
    {
        return in_array($user, array_filter([
            (string) ($item->user_id ?? ''),
            (string) ($item->salary_number ?? ''),
            (string) ($item->employee_id ?? ''),
        ], static fn (string $value) => $value !== ''), true);
    }
}
