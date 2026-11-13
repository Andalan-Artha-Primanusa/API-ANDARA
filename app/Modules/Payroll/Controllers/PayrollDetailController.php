<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PayrollDetailController extends Controller
{
    // 📌 GET DETAIL BY PAYROLL
    public function index(Request $request, $payroll_id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.view')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $query = Payroll::with([
            'employee.user.profile',
            'employee.manager.profile',
            'details'
        ]);
        app(\App\Services\CompanyScopeService::class)->applyThroughEmployee($query, $request);
        $payroll = $query->find($payroll_id);

        if (!$payroll) {
            return ApiResponse::error('Payroll not found', null, 404);
        }

        $details = PayrollDetail::where('payroll_id', $payroll_id)->get();

        return ApiResponse::success(
            $details->isEmpty() ? 'No payroll details available' : 'Data retrieved successfully',
            [
                'payroll' => $payroll,
                'details' => $details
            ]
        );
    }

    // 📌 STORE (BULK)
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.create') && !$user->hasPermission('payroll.generate')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $request->validate([
            'payroll_id' => 'required|exists:payrolls,id',
            'details' => 'required|array|min:1',
            'details.*.type' => 'required|in:allowance,deduction',
            'details.*.name' => 'required|string|max:255',
            'details.*.amount' => 'required|numeric|min:0',
        ]);

        $query = Payroll::query();
        app(\App\Services\CompanyScopeService::class)->applyThroughEmployee($query, $request);
        $payroll = $query->findOrFail($request->payroll_id);

        if ($payroll->status !== 'draft') {
            return ApiResponse::error('Payroll has already been processed', null, 400);
        }

        $insertData = collect($request->details)->map(function ($item) use ($request) {
            return [
                'payroll_id' => $request->payroll_id,
                'type' => $item['type'],
                'name' => $item['name'],
                'amount' => $item['amount'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        PayrollDetail::insert($insertData);

        return ApiResponse::success('Payroll detail created successfully', $insertData, 201);
    }

    // 📌 UPDATE (SINGLE)
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.create') && !$user->hasPermission('payroll.generate')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $detail = PayrollDetail::with('payroll.employee.user.profile')
            ->whereHas('payroll', function($q) use ($request) {
                app(\App\Services\CompanyScopeService::class)->applyThroughEmployee($q, $request);
            })
            ->find($id);

        if (!$detail) {
            return ApiResponse::error("Detail ID $id not found", null, 404);
        }

        if (!$detail->payroll || $detail->payroll->status !== 'draft') {
            return ApiResponse::error('Cannot edit payroll detail', null, 400);
        }

        $data = array_filter($request->only(['type', 'name', 'amount']), function ($v) {
            return !is_null($v);
        });

        if (empty($data)) {
            return ApiResponse::error('No data to update', null, 400);
        }

        $detail->update($data);

        return ApiResponse::success('Detail updated successfully', $detail->fresh('payroll.employee.user.profile'));
    }

    // 📌 BULK UPDATE
    public function bulkUpdate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.create') && !$user->hasPermission('payroll.generate')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $request->validate([
            'details' => 'required|array|min:1',
            'details.*.id' => 'required|exists:payroll_details,id',
            'details.*.type' => 'nullable|in:allowance,deduction',
            'details.*.name' => 'nullable|string|max:255',
            'details.*.amount' => 'nullable|numeric|min:0',
        ]);

        $updated = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($request->details as $item) {
                $detail = PayrollDetail::with('payroll.employee.user.profile')
                    ->whereHas('payroll', function($q) use ($request) {
                        app(\App\Services\CompanyScopeService::class)->applyThroughEmployee($q, $request);
                    })
                    ->find($item['id']);

                if (!$detail) {
                    $errors[] = [
                        'id' => $item['id'],
                        'message' => 'Detail not found'
                    ];
                    continue;
                }

                if (!$detail->payroll || $detail->payroll->status !== 'draft') {
                    $errors[] = [
                        'id' => $item['id'],
                        'message' => 'Cannot edit (not in draft status)'
                    ];
                    continue;
                }

                $data = array_filter([
                    'type' => $item['type'] ?? null,
                    'name' => $item['name'] ?? null,
                    'amount' => $item['amount'] ?? null,
                ], fn($v) => !is_null($v));

                if (!empty($data)) {
                    $detail->update($data);
                    $updated[] = $detail->fresh('payroll.employee.user.profile');
                }
            }

            DB::commit();

            return ApiResponse::success('Bulk update completed successfully', [
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error('An error occurred', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 📌 DELETE
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.create') && !$user->hasPermission('payroll.generate')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $detail = PayrollDetail::with('payroll.employee.user.profile')
            ->whereHas('payroll', function($q) use ($request) {
                app(\App\Services\CompanyScopeService::class)->applyThroughEmployee($q, $request);
            })
            ->find($id);

        if (!$detail) {
            return ApiResponse::error('Detail not found', null, 404);
        }

        if (!$detail->payroll || $detail->payroll->status !== 'draft') {
            return ApiResponse::error('Cannot delete payroll detail', null, 400);
        }

        $deleted = $detail->toArray();
        $detail->delete();

        return ApiResponse::success('Detail deleted successfully', $deleted);
    }
}
