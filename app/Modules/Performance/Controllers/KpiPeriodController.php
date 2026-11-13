<?php

namespace App\Modules\Performance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ApprovalFlow;
use App\Models\KpiPeriod;
use App\Models\KpiItem;
use App\Helpers\ApiResponse;
use App\Traits\HasEmployee;
use App\Services\CompanyScopeService;
use App\Services\ApprovalFlowService;

class KpiPeriodController extends Controller
{
    use HasEmployee;

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('kpi.view')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            $query = KpiPeriod::with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'items',
            ]);
            app(CompanyScopeService::class)->applyThroughEmployee($query, $request);

            if ($user->isManager() && !$user->isAdmin() && !$user->isHR() && !$user->hasPermission('kpi.view')) {
                $subordinateIds = $user->teamMembers()->pluck('id')->filter()->toArray();
                if (empty($subordinateIds)) {
                    return ApiResponse::success('Tidak ada periode KPI', collect());
                }
                $query->whereIn('employee_id', $subordinateIds);
            }

            $periods = $query->latest()->paginate($request->integer('per_page', 10))->withQueryString();

            $periods->getCollection()->each(fn($p) => $p->calculateOverallScore());

            return ApiResponse::success('Periode KPI berhasil dimuat', $periods);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memuat periode KPI', null, 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('kpi.create')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'period_type' => 'required|in:quarterly,semi_annual,annual',
                'period_date' => 'required|date',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.indicator' => 'required|string|max:255',
                'items.*.description' => 'nullable|string',
                'items.*.category' => 'nullable|string',
                'items.*.measurement_method' => 'nullable|string',
                'items.*.formula_type' => 'nullable|string',
                'items.*.weight' => 'required|integer|min:0|max:100',
                'items.*.target' => 'required|numeric|min:0',
                'items.*.source' => 'nullable|string',
            ]);

            $periodLabel = KpiPeriod::generateLabel($validated['period_type'], $validated['period_date']);
            $dateRange = KpiPeriod::getDateRange($validated['period_type'], $validated['period_date']);

            $period = KpiPeriod::create([
                'employee_id' => $validated['employee_id'],
                'period_type' => $validated['period_type'],
                'period_label' => $periodLabel,
                'start_date' => $dateRange['start'],
                'end_date' => $dateRange['end'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
                'status' => 'draft',
            ]);

            foreach ($validated['items'] as $itemData) {
                $item = new KpiItem();
                $item->kpi_period_id = $period->id;
                $item->indicator = $itemData['indicator'];
                $item->description = $itemData['description'] ?? null;
                $item->category = $itemData['category'] ?? null;
                $item->measurement_method = $itemData['measurement_method'] ?? 'direct';
                $item->formula_type = $itemData['formula_type'] ?? 'standard';
                $item->weight = $itemData['weight'];
                $item->target = $itemData['target'];
                $item->achievement = 0;
                $item->score = 0;
                $item->source = $itemData['source'] ?? null;
                $item->save();
            }

            $period->load(['employee.user', 'items']);

            return ApiResponse::success('Periode KPI berhasil dibuat', $period, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
                return ApiResponse::error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal membuat periode KPI', null, 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $periodQuery = KpiPeriod::with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'items',
                'creator:id,name,email',
                'creator.profile:id,user_id,profile_photo_path'
            ]);
            app(CompanyScopeService::class)->applyThroughEmployee($periodQuery, $request);
            $period = $periodQuery->findOrFail($id);

            $user = $request->user();
            $isOwner = $period->employee?->user_id === $user->id;

            if (!$isOwner && !$user->hasPermission('kpi.view')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            $period->calculateOverallScore();

            return ApiResponse::success('Detail periode KPI', $period);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Tidak ditemukan', 'Periode KPI tidak ditemukan', 404);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memuat detail periode KPI', null, 500);
        }
    }

    public function updateItems(Request $request, int $id): JsonResponse
    {
        try {
            $periodQuery = KpiPeriod::query();
            app(CompanyScopeService::class)->applyThroughEmployee($periodQuery, $request);
            $period = $periodQuery->findOrFail($id);
            $user = $request->user();

            if (!$user->hasPermission('kpi.update')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            if ($period->status !== 'draft') {
                return ApiResponse::error('Cannot edit non-draft KPI period', null, 400);
            }

            $validated = $request->validate([
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.id' => 'nullable|exists:kpi_items,id',
                'items.*.indicator' => 'required|string|max:255',
                'items.*.description' => 'nullable|string',
                'items.*.category' => 'nullable|string',
                'items.*.measurement_method' => 'nullable|string',
                'items.*.formula_type' => 'nullable|string',
                'items.*.weight' => 'required|integer|min:0|max:100',
                'items.*.target' => 'required|numeric|min:0',
                'items.*.achievement' => 'nullable|numeric|min:0',
                'items.*.source' => 'nullable|string',
            ]);

            if (isset($validated['notes'])) {
                $period->update(['notes' => $validated['notes']]);
            }

            $submittedIds = [];

            foreach ($validated['items'] as $itemData) {
                $data = [
                    'indicator' => $itemData['indicator'],
                    'description' => $itemData['description'] ?? null,
                    'category' => $itemData['category'] ?? null,
                    'measurement_method' => $itemData['measurement_method'] ?? 'direct',
                    'formula_type' => $itemData['formula_type'] ?? 'standard',
                    'weight' => $itemData['weight'],
                    'target' => $itemData['target'],
                    'source' => $itemData['source'] ?? null,
                ];

                if (isset($itemData['achievement'])) {
                    $data['achievement'] = $itemData['achievement'];
                }

                if (!empty($itemData['id'])) {
                    $item = KpiItem::find($itemData['id']);
                    if ($item && $item->kpi_period_id === $period->id) {
                        $item->update($data);
                        if (isset($itemData['achievement'])) {
                            $item->calculateScore();
                            $item->save();
                        }
                        $submittedIds[] = $item->id;
                    }
                } else {
                    $item = $period->items()->create($data);
                    if (isset($itemData['achievement'])) {
                        $item->calculateScore();
                        $item->save();
                    }
                    $submittedIds[] = $item->id;
                }
            }

            $period->items()->whereNotIn('id', $submittedIds)->delete();

            $period->load('items');
            $period->calculateOverallScore();
            $period->save();

            return ApiResponse::success('Periode KPI berhasil diperbarui', $period->fresh(['employee.user', 'items']));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Tidak ditemukan', 'Periode KPI tidak ditemukan', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
                return ApiResponse::error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui periode KPI', null, 500);
        }
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        try {
            $employee = $this->getAuthenticatedEmployee();
            $periodQuery = KpiPeriod::with('items');
            app(CompanyScopeService::class)->applyThroughEmployee($periodQuery, $request);
            $period = $periodQuery->findOrFail($id);

            if ($period->employee_id !== $employee->id) {
                return ApiResponse::error('Forbidden', 'Bukan periode KPI Anda', 403);
            }

            $validated = $request->validate([
                'item_id' => 'nullable|exists:kpi_items,id',
            ]);

            // Submit single item if item_id provided
            if (!empty($validated['item_id'])) {
                $item = $period->items->firstWhere('id', (int) $validated['item_id']);

                if (!$item) {
                    return ApiResponse::error('Not found', 'KPI item not found in this period', 404);
                }

                if ($item->status !== 'draft') {
                    return ApiResponse::error('Status tidak valid', 'Item sudah tidak dalam status draft', 400);
                }

                $item->status = 'submitted';
                $item->save();

                // Reload period with fresh items from DB
                $period = $period->fresh(['items']);
                $this->syncPeriodStatus($period);

                return ApiResponse::success('Item KPI berhasil diajukan', $period->fresh(['employee.user', 'items']));
            }

            // Submit all items if no item_id (legacy behavior)
            if ($period->status !== 'draft') {
                return ApiResponse::error('Sudah diajukan', 'Periode sudah tidak dalam status draft', 400);
            }

            $period->items()->where('status', 'draft')->update(['status' => 'submitted']);
            $period->update(['status' => 'submitted']);
            $period->calculateOverallScore();

            return ApiResponse::success('Periode KPI berhasil diajukan', $period->fresh(['employee.user', 'items']));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Tidak ditemukan', 'Periode KPI tidak ditemukan', 404);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal mengajukan periode KPI', null, 500);
        }
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('kpi.approve')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            $flow = ApprovalFlow::where('module', 'kpi')->where('is_active', true)->first();
            if (!$flow) {
                return ApiResponse::error('Approval flow untuk KPI belum dikonfigurasi. Silakan buat di menu Alur Persetujuan terlebih dahulu.', null, 400);
            }

            $periodQuery = KpiPeriod::with('items');
            app(CompanyScopeService::class)->applyThroughEmployee($periodQuery, $request);
            $period = $periodQuery->findOrFail($id);
            $approvalService = app(ApprovalFlowService::class);
            $approvalService->guardNotSelfApproval($period, $user);

            $validated = $request->validate([
                'item_id' => 'nullable|exists:kpi_items,id',
            ]);

            // Approve single item if item_id provided
            if (!empty($validated['item_id'])) {
                $item = $period->items->firstWhere('id', (int) $validated['item_id']);

                if (!$item) {
                    return ApiResponse::error('Not found', 'KPI item not found in this period', 404);
                }

                if ($item->status !== 'submitted') {
                    return ApiResponse::error('Status tidak valid', 'Item KPI harus diajukan terlebih dahulu', 400);
                }

                $item->calculateScore();
                $item->status = 'approved';
                $item->save();

                // Reload period with fresh items from DB
                $period = $period->fresh(['items']);
                $this->syncPeriodStatus($period);

                return ApiResponse::success('Item KPI berhasil disetujui', $period->fresh(['employee.user', 'items']));
            }

            // Correct Flow Integration: Use ApprovalFlowService
            try {
                $result = $approvalService->processApproval($period, $user, 'approved', $request->note);
                
                $period = $result['model'];
                
                // If final approval, ensure items are also marked
                if ($result['final']) {
                    foreach ($period->items as $item) {
                        $item->calculateScore();
                        $item->status = 'approved';
                        $item->save();
                    }
                    $period->calculateOverallScore();
                    $period->status = 'approved';
                    $period->save();
                }

                $period->load([
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id,profile_photo_path',
                    'employee.department:id,name',
                    'employee.position:id,name',
                    'items'
                ]);

                return ApiResponse::success(
                    $result['final'] ? 'Periode KPI berhasil disetujui sepenuhnya' : 'Periode KPI disetujui - menunggu tahap berikutnya', 
                    $period
                );
            } catch (\DomainException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            } catch (\RuntimeException $e) {
                // Fallback: simple single-step approval
                foreach ($period->items as $item) {
                    $item->calculateScore();
                    $item->status = 'approved';
                    $item->save();
                }

                $period->calculateOverallScore();
                $period->status = 'approved';
                $period->save();

                return ApiResponse::success('Periode KPI berhasil disetujui', $period->fresh(['employee.user', 'items']));
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Tidak ditemukan', 'Periode KPI tidak ditemukan', 404);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menyetujui periode KPI', null, 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('kpi.delete')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            $periodQuery = KpiPeriod::query();
            app(CompanyScopeService::class)->applyThroughEmployee($periodQuery, $request);
            $period = $periodQuery->findOrFail($id);
            $period->delete();

            return ApiResponse::success('Periode KPI berhasil dihapus');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Not found', null, 404);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menghapus', null, 500);
        }
    }

    // Employee self-service
    public function myKpiPeriods(Request $request): JsonResponse
    {
        try {
            $employee = $this->getAuthenticatedEmployee();

            $periods = KpiPeriod::with('items')
                ->where('employee_id', $employee->id)
                ->latest()
                ->paginate($request->integer('per_page', 10))
                ->withQueryString();

            $periods->getCollection()->each(fn($p) => $p->calculateOverallScore());

            return ApiResponse::success('Periode KPI saya', $periods);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memuat periode KPI', null, 500);
        }
    }

    public function myUpdateItems(Request $request, int $id): JsonResponse
    {
        try {
            $employee = $this->getAuthenticatedEmployee();
            $period = KpiPeriod::with('items')->findOrFail($id);

            if ($period->employee_id !== $employee->id) {
                return ApiResponse::error('Forbidden', 'Bukan periode KPI Anda', 403);
            }

            if ($period->status !== 'draft') {
                return ApiResponse::error('Tidak dapat mengedit periode KPI yang bukan draft', null, 400);
            }

            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|exists:kpi_items,id',
                'items.*.achievement' => 'nullable|numeric|min:0',
            ]);

            foreach ($validated['items'] as $itemData) {
                $item = $period->items->firstWhere('id', (int) $itemData['id']);

                if (!$item) {
                    return ApiResponse::error('Validasi gagal', [
                        'items' => ['One or more KPI items do not belong to this period.'],
                    ], 422);
                }

                $item->achievement = $itemData['achievement'] ?? 0;
                $item->calculateScore();
                $item->status = 'draft';
                $item->save();
            }

            $period->calculateOverallScore();
            $period->save();

            return ApiResponse::success('Item periode KPI berhasil diperbarui', $period->fresh(['employee.user', 'items']));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Tidak ditemukan', 'Periode KPI tidak ditemukan', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
                return ApiResponse::error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui item periode KPI', null, 500);
        }
    }

    public function mySubmit(Request $request, int $id): JsonResponse
    {
        return $this->submit($request, $id);
    }

    private function syncPeriodStatus(KpiPeriod $period): void
    {
        $period->loadMissing('items');

        if ($period->items->isEmpty()) {
            $period->status = 'draft';
            $period->calculateOverallScore();
            $period->save();
            return;
        }

        $allApproved = $period->items->every(fn($item) => $item->status === 'approved');
        $anySubmitted = $period->items->contains(fn($item) => in_array($item->status, ['submitted', 'approved'], true));

        if ($allApproved) {
            $period->status = 'approved';
        } elseif ($anySubmitted) {
            $period->status = 'submitted';
        } else {
            $period->status = 'draft';
        }

        $period->calculateOverallScore();
        $period->save();
    }
}
