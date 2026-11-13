<?php

namespace App\Modules\Performance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Models\ApprovalFlow;
use App\Models\Kpi;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreKpiRequest;
use App\Services\CompanyScopeService;
use App\Traits\HasEmployee;

class KpiController extends Controller
{
    use HasEmployee;

    /**
     * Default relations to eager load for KPI queries
     */
    private const KPI_RELATIONS = [
        'employee:id,user_id,employee_code,department_id,position_id',
        'employee.user:id,name,email',
        'employee.user.profile:id,user_id,profile_photo_path',
        'employee.department:id,name',
        'employee.position:id,name',
        'employee.manager:id,name',
        'employee.manager.profile:id,user_id,profile_photo_path',
    ];

    /*
    |--------------------------------------------------------------------------
    | 🔥 HR / MANAGER (Admin Level)
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('kpi.view')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            // Optimized query with eager loading
            $query = Kpi::with(self::KPI_RELATIONS)
                ->latest();

            app(CompanyScopeService::class)->applyThroughEmployee($query, $request);

            // Scope by manager subordinates if not admin/hr
            if ($user->isManager() && !$user->isAdmin() && !$user->isHR()) {
                $subordinateIds = $user->teamMembers()
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                if (empty($subordinateIds)) {
                    // Return an empty paginator instead of a plain collection to maintain structure consistency
                    $emptyPaginator = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $request->integer('per_page', 10));
                    return ApiResponse::success('Tidak ada data KPI', $emptyPaginator);
                }

                $query->whereIn('employee_id', $subordinateIds);
            }

            $kpis = $query->paginate($request->integer('per_page', 10))->withQueryString();

            return ApiResponse::success('Data KPI berhasil dimuat', $kpis);

        } catch (\Exception $e) {
            \Log::error('KPI INDEX ERROR: ' . $e->getMessage());
            return ApiResponse::error('Gagal memuat data KPI: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Create new KPI record.
     */
    public function store(StoreKpiRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('kpi.create')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            if (!app(CompanyScopeService::class)->canAccessEmployeeCompany($request->validated()['employee_id'], $request->user())) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            $kpi = Kpi::create(array_merge(
                $request->validated(),
                [
                    'achievement' => 0,
                    'score'       => 0,
                    'status'      => 'assigned',
                ]
            ));

            return ApiResponse::success(
                'KPI berhasil dibuat',
                $kpi->load(self::KPI_RELATIONS),
                201
            );

        } catch (\Exception $e) {
            return ApiResponse::error('Gagal membuat KPI', null, 500);
        }
    }

    /**
     * Show specific KPI detail.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'ID KPI tidak valid']);
            }

            $kpiQuery = Kpi::with(self::KPI_RELATIONS);
            app(CompanyScopeService::class)->applyThroughEmployee($kpiQuery, $request);
            $kpi = $kpiQuery->findOrFail($id);

            $user = $request->user();
            $isOwner = $kpi->employee?->user_id === $user->id;

            if (!$isOwner && !$user->hasPermission('kpi.view')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            return ApiResponse::success('Detail KPI', $kpi);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Tidak ditemukan', 'Data KPI tidak ditemukan', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Request tidak valid', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memuat detail KPI', null, 500);
        }
    }

    /**
     * Update KPI record with auto-score calculation.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'ID KPI tidak valid']);
            }

            $kpiQuery = Kpi::query();
            app(CompanyScopeService::class)->applyThroughEmployee($kpiQuery, $request);
            $kpi = $kpiQuery->findOrFail($id);
            $user = $request->user();

            if (!$user->hasPermission('kpi.update')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            $validated = $request->validate([
                'title'       => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'target'      => 'sometimes|numeric|min:0',
                'achievement' => 'sometimes|numeric|min:0',
            ]);

            $kpi->update($validated);

            // Auto-calculate score if target is set
            if ($kpi->target > 0) {
                $kpi->score = ($kpi->achievement / $kpi->target) * 100;
                $kpi->save();
            }

            return ApiResponse::success(
                'KPI berhasil diperbarui',
                $kpi->fresh(self::KPI_RELATIONS)
            );

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Tidak ditemukan', 'Data KPI tidak ditemukan', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui KPI', null, 500);
        }
    }

    /**
     * Delete KPI record.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'ID KPI tidak valid']);
            }

            $kpiQuery = Kpi::select(['id', 'employee_id', 'title', 'target', 'achievement', 'status']);
            app(CompanyScopeService::class)->applyThroughEmployee($kpiQuery, $request);
            $kpi = $kpiQuery->findOrFail($id);
            $user = $request->user();

            if (!$user->hasPermission('kpi.delete')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            $deleted = $kpi->toArray();
            $kpi->delete();

            return ApiResponse::success('KPI berhasil dihapus', $deleted);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Tidak ditemukan', 'Data KPI tidak ditemukan', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Request tidak valid', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menghapus KPI', null, 500);
        }
    }

    /**
     * Get all KPIs for specific employee.
     */
    public function byEmployee(Request $request, int $employee_id): JsonResponse
    {
        try {
            if ($employee_id <= 0) {
                throw ValidationException::withMessages(['employee_id' => 'ID karyawan tidak valid']);
            }

            $user = $request->user();

            if (!$user->hasPermission('kpi.view')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            $kpisQuery = Kpi::with(self::KPI_RELATIONS)
                ->where('employee_id', $employee_id)
                ->latest();
            app(CompanyScopeService::class)->applyThroughEmployee($kpisQuery, $request);
            $kpis = $kpisQuery->paginate($request->integer('per_page', 10))->withQueryString();

            return ApiResponse::success('KPI karyawan', $kpis);

        } catch (ValidationException $e) {
            return ApiResponse::error('Request tidak valid', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memuat data KPI', null, 500);
        }
    }

    /**
     * Approve KPI record.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'ID KPI tidak valid']);
            }

            $flow = ApprovalFlow::where('module', 'kpi')->where('is_active', true)->first();
            if (!$flow) {
                return ApiResponse::error('Approval flow untuk KPI belum dikonfigurasi. Silakan buat di menu Alur Persetujuan terlebih dahulu.', null, 400);
            }

            $kpiQuery = Kpi::with(self::KPI_RELATIONS);
            app(CompanyScopeService::class)->applyThroughEmployee($kpiQuery, $request);
            $kpi = $kpiQuery->findOrFail($id);
            $user = $request->user();

            if (!$user->hasPermission('kpi.approve')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            if ($kpi->status !== 'submitted') {
                return ApiResponse::error('Status tidak valid', 'Hanya KPI dengan status submitted yang bisa disetujui', 400);
            }

            $approvalService = app(\App\Services\ApprovalFlowService::class);
            $approvalService->guardNotSelfApproval($kpi, $user);

            // Correct Flow Integration: Use ApprovalFlowService
            try {
                $result = $approvalService->processApproval($kpi, $user, 'approved', $request->note);
                
                $kpi = $result['model'];
                $kpi->load(self::KPI_RELATIONS);

                return ApiResponse::success(
                    $result['final'] ? 'KPI berhasil disetujui sepenuhnya' : 'KPI disetujui - menunggu tahap berikutnya', 
                    $kpi
                );
            } catch (\DomainException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            } catch (\RuntimeException $e) {
                // Simple Fallback
                $kpi->update(['status' => 'approved']);
                return ApiResponse::success('KPI berhasil disetujui', $kpi->fresh(self::KPI_RELATIONS));
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Tidak ditemukan', 'Data KPI tidak ditemukan', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Request tidak valid', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menyetujui KPI', null, 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 👤 EMPLOYEE SELF-SERVICE (ESS)
    |--------------------------------------------------------------------------
    */

    /**
     * Get authenticated employee's KPIs.
     */
    public function myKpi(Request $request): JsonResponse
    {
        try {
            $employee = $this->getAuthenticatedEmployee();

            $kpis = Kpi::with(self::KPI_RELATIONS)
                ->where('employee_id', $employee->id)
                ->latest()
                ->paginate($request->integer('per_page', 10))
                ->withQueryString();

            return ApiResponse::success('KPI saya', $kpis);

        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memuat KPI Anda', null, 500);
        }
    }

    /**
     * Accept assigned KPI (assigned → active).
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'ID KPI tidak valid']);
            }

            $employee = $this->getAuthenticatedEmployee();
            $kpi = Kpi::findOrFail($id);

            if ($kpi->employee_id !== $employee->id) {
                return ApiResponse::error('Dilarang', 'Anda tidak dapat menerima KPI ini', 403);
            }

            if ($kpi->status !== 'assigned') {
                return ApiResponse::error('Status tidak valid', 'Hanya KPI dengan status assigned yang bisa diterima', 400);
            }

            $kpi->update(['status' => 'active']);

            return ApiResponse::success('KPI berhasil diterima', $kpi->fresh(self::KPI_RELATIONS));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Tidak ditemukan', 'Data KPI tidak ditemukan', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Request tidak valid', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menerima KPI', null, 500);
        }
    }

    /**
     * Update KPI progress (achievement).
     */
    public function updateProgress(Request $request, int $id): JsonResponse
    {
        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'ID KPI tidak valid']);
            }

            $employee = $this->getAuthenticatedEmployee();
            $kpi = Kpi::findOrFail($id);

            if ($kpi->employee_id !== $employee->id) {
                return ApiResponse::error('Dilarang', 'Anda tidak dapat memperbarui KPI ini', 403);
            }

            if (!in_array($kpi->status, ['active', 'submitted'])) {
                return ApiResponse::error('Status tidak valid', 'Hanya KPI dengan status active atau submitted yang bisa diperbarui', 400);
            }

            $validated = $request->validate([
                'achievement' => 'required|numeric|min:0',
            ]);

            $kpi->achievement = $validated['achievement'];

            // Auto-calculate score
            if ($kpi->target > 0) {
                $kpi->score = ($kpi->achievement / $kpi->target) * 100;
            }

            $kpi->save();

            return ApiResponse::success('Progres KPI diperbarui', $kpi->fresh(self::KPI_RELATIONS));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Tidak ditemukan', 'Data KPI tidak ditemukan', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Request tidak valid', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui progres KPI', null, 500);
        }
    }

    /**
     * Submit KPI for approval by employee (active → submitted).
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'ID KPI tidak valid']);
            }

            $employee = $this->getAuthenticatedEmployee();
            $kpi = Kpi::findOrFail($id);

            // Authorization: must be own KPI
            if ($kpi->employee_id !== $employee->id) {
                return ApiResponse::error('Dilarang', 'Anda tidak dapat mengajukan KPI ini', 403);
            }

            // Validation: must be in active status
            if ($kpi->status !== 'active') {
                return ApiResponse::error('Status tidak valid', 'Hanya KPI dengan status active yang bisa diajukan', 400);
            }

            $kpi->update(['status' => 'submitted']);

            return ApiResponse::success('KPI berhasil diajukan', $kpi->fresh(self::KPI_RELATIONS));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Tidak ditemukan', 'Data KPI tidak ditemukan', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Request tidak valid', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal mengajukan KPI', null, 500);
        }
    }
}
