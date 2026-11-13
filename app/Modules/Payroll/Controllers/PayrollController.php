<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApprovalFlow;
use App\Modules\Employee\Models\Employee;
use App\Models\Payroll;
use App\Helpers\ApiResponse;
use App\Traits\HasEmployee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\PayrollService;
use App\Services\CompanyScopeService;

class PayrollController extends Controller
{
    use HasEmployee;

    public function __construct(
        protected PayrollService $payrollService,
        protected CompanyScopeService $companyScope
    ) {}

    // =========================================================================
    // LIST & DETAIL
    // =========================================================================

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.view')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $data = Payroll::with([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'employee.manager:id,name',
            'employee.manager.profile:id,user_id,profile_photo_path',
            'details',
            'reimbursements'
        ])
            ->when(true, fn ($query) => $this->companyScope->applyThroughEmployee($query, $request))
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return ApiResponse::success(
            $data->isEmpty() ? 'No payroll data available' : 'Payroll data retrieved successfully',
            $data
        );
    }

    public function myPayroll(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        $data = Payroll::with([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'employee.manager:id,name',
            'employee.manager.profile:id,user_id,profile_photo_path',
            'details',
            'reimbursements'
        ])
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return ApiResponse::success(
            $data->isEmpty() ? 'No payroll data found' : 'Payroll retrieved successfully',
            $data
        );
    }

    public function show(Request $request, $id): JsonResponse
    {
        $query = Payroll::with([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'employee.manager:id,name',
            'employee.manager.profile:id,user_id,profile_photo_path',
            'details',
            'reimbursements'
        ]);
        $this->companyScope->applyThroughEmployee($query, $request);
        $data = $query->find($id);

        if (!$data) {
            return ApiResponse::error('Payroll not found', null, 404);
        }

        $user = $request->user();

        if ($data->employee?->user_id !== $user->id && !$user->hasPermission('payroll.view')) {
            return ApiResponse::error('Forbidden', 'You cannot access this payroll', 403);
        }

        return ApiResponse::success('Payroll details retrieved successfully', $data);
    }

    // =========================================================================
    // STORE / GENERATE
    // =========================================================================

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.create')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period'      => 'required',
        ]);

        return DB::transaction(function () use ($request) {
            $employeeQuery = Employee::query();
            $this->companyScope->applyEmployeeScope($employeeQuery, $request);
            $employee = $employeeQuery->findOrFail($request->employee_id);

            $exists = Payroll::where('employee_id', $employee->id)
                ->where('period', $request->period)
                ->exists();

            if ($exists) {
                return ApiResponse::error('Payroll for this period already exists', null, 400);
            }

            try {
                $payroll = $this->payrollService->calculateAndCreate(
                    $employee,
                    $request->period,
                    (float) ($request->allowance ?? 0),
                    (float) ($request->bonus ?? 0)
                );

                return ApiResponse::success(
                    'Payroll created successfully',
                    $payroll->load([
                        'employee:id,user_id,employee_code,department_id,position_id',
                        'employee.user:id,name,email',
                        'employee.user.profile:id,user_id,profile_photo_path',
                        'employee.department:id,name',
                        'employee.position:id,name',
                        'employee.manager:id,name',
                        'employee.manager.profile:id,user_id,profile_photo_path',
                        'details',
                        'reimbursements'
                    ]),
                    201
                );
            } catch (\DomainException $e) {
                return ApiResponse::error($e->getMessage(), null, 400);
            }
        });
    }

    public function generateMonthly(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.generate')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $request->validate(['period' => 'required']);

        try {
            $result = $this->payrollService->generateMonthlyBulk(
                $request->period,
                $this->companyScope->selectedCompanyId($request)
            );
            
            // Hydrate with Mega-Full context
            $hydrated = Payroll::with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'details'
            ])->whereIn('id', collect($result)->pluck('id'))->get();

            return ApiResponse::success('Payroll generation completed successfully', [
                'total' => $hydrated->count(),
                'data'  => $hydrated,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Error generating payroll', ['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // UPDATE / DELETE
    // =========================================================================

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.create') && !$user->hasPermission('payroll.generate')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $query = Payroll::query();
        $this->companyScope->applyThroughEmployee($query, $request);
        $payroll = $query->find($id);

        if (!$payroll) {
            return ApiResponse::error('Payroll not found', null, 404);
        }

        if ($payroll->status !== 'draft') {
            return ApiResponse::error('Payroll can only be edited when in draft status', null, 400);
        }

        $validated = $request->validate([
            'allowance' => 'sometimes|numeric|min:0',
            'bonus'     => 'sometimes|numeric|min:0',
        ]);

        $payroll->update($validated);

        return ApiResponse::success('Payroll updated successfully', $payroll->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'employee.manager:id,name',
            'employee.manager.profile:id,user_id,profile_photo_path',
            'details',
            'reimbursements'
        ]));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.create') && !$user->hasPermission('payroll.generate')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $query = Payroll::query();
        $this->companyScope->applyThroughEmployee($query, $request);
        $payroll = $query->find($id);

        if (!$payroll) {
            return ApiResponse::error('Payroll not found', null, 404);
        }

        if ($payroll->isLocked()) {
            return ApiResponse::error('Cannot delete locked payroll (approved/paid)', null, 400);
        }

        // Unlink any reimbursements linked to this payroll
        $payroll->reimbursements()->update(['payroll_id' => null]);

        $deleted = $payroll->toArray();
        $payroll->delete();

        return ApiResponse::success('Payroll deleted successfully', $deleted);
    }

    // =========================================================================
    // APPROVAL FLOW: draft â†’ pending_hr â†’ approved â†’ paid
    // =========================================================================

    /**
     * Step 1 â€” Manager approves: draft â†’ pending_hr
     */
    public function managerApprove(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.approve')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $flow = ApprovalFlow::where('module', 'payroll')->where('is_active', true)->first();
        if (!$flow) {
            return ApiResponse::error('Approval flow untuk Payroll belum dikonfigurasi. Silakan buat di menu Alur Persetujuan terlebih dahulu.', null, 400);
        }

        $query = Payroll::query();
        $this->companyScope->applyThroughEmployee($query, $request);
        $payroll = $query->find($id);

        if (!$payroll) {
            return ApiResponse::error('Payroll not found', null, 404);
        }

        if ($payroll->status !== 'draft') {
            return ApiResponse::error(
                'Payroll must be in draft status for manager approval. Current: ' . $payroll->status,
                null,
                400
            );
        }

        $payroll->update([
            'status'              => 'pending_hr',
            'manager_approved_by' => $user->id,
            'manager_approved_at' => now(),
        ]);

        return ApiResponse::success('Payroll approved by manager â€” awaiting HR final approval', $payroll->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'employee.manager:id,name',
            'employee.manager.profile:id,user_id,profile_photo_path',
            'details',
            'reimbursements'
        ]));
    }

    /**
     * Step 2 â€” HR final approves: pending_hr â†’ approved
     */
    public function hrApprove(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.approve')) {
            return ApiResponse::error('Forbidden', 'Only HR or Admin can perform final approval', 403);
        }

        $flow = ApprovalFlow::where('module', 'payroll')->where('is_active', true)->first();
        if (!$flow) {
            return ApiResponse::error('Approval flow untuk Payroll belum dikonfigurasi. Silakan buat di menu Alur Persetujuan terlebih dahulu.', null, 400);
        }

        $query = Payroll::query();
        $this->companyScope->applyThroughEmployee($query, $request);
        $payroll = $query->find($id);

        if (!$payroll) {
            return ApiResponse::error('Payroll not found', null, 404);
        }

        if ($payroll->status !== 'pending_hr') {
            return ApiResponse::error(
                'Payroll must be in pending_hr status for HR approval. Current: ' . $payroll->status,
                null,
                400
            );
        }

        $payroll->update([
            'status'          => 'approved',
            'hr_approved_by'  => $user->id,
            'hr_approved_at'  => now(),
        ]);

        return ApiResponse::success('Payroll approved by HR â€” ready for payment', $payroll->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'employee.manager:id,name',
            'employee.manager.profile:id,user_id,profile_photo_path',
            'details',
            'reimbursements'
        ]));
    }

    /**
     * Reject: draft|pending_hr â†’ rejected (Manager or HR can reject)
     */
    public function reject(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.approve')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $query = Payroll::query();
        $this->companyScope->applyThroughEmployee($query, $request);
        $payroll = $query->find($id);

        if (!$payroll) {
            return ApiResponse::error('Payroll not found', null, 404);
        }

        if (!in_array($payroll->status, ['draft', 'pending_hr'])) {
            return ApiResponse::error(
                'Only draft or pending_hr payroll can be rejected. Current: ' . $payroll->status,
                null,
                400
            );
        }

        // Unlink reimbursements so they can be included in next payroll
        $payroll->reimbursements()->update(['payroll_id' => null]);

        $payroll->update([
            'status'          => 'rejected',
            'rejected_by'     => $user->id,
            'rejected_reason' => $request->reason,
        ]);

        return ApiResponse::success('Payroll rejected', $payroll->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'employee.manager:id,name',
            'employee.manager.profile:id,user_id,profile_photo_path',
            'details',
            'reimbursements'
        ]));
    }

    /**
     * Backward-compat: POST /{id}/approve â€” routes to correct step based on status.
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $query = Payroll::query();
        $this->companyScope->applyThroughEmployee($query, $request);
        $payroll = $query->find($id);

        if (!$payroll) {
            return ApiResponse::error('Payroll not found', null, 404);
        }

        if ($payroll->status === 'draft') {
            return $this->managerApprove($request, $id);
        }

        if ($payroll->status === 'pending_hr') {
            return $this->hrApprove($request, $id);
        }

        return ApiResponse::error('Payroll has already been processed. Status: ' . $payroll->status, null, 400);
    }

    /**
     * Mark single payroll as paid: approved â†’ paid
     */
    public function pay(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.pay')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $query = Payroll::query();
        $this->companyScope->applyThroughEmployee($query, $request);
        $payroll = $query->find($id);

        if (!$payroll) {
            return ApiResponse::error('Payroll not found', null, 404);
        }

        if ($payroll->status !== 'approved') {
            return ApiResponse::error('Payroll must be approved before marking as paid', null, 400);
        }

        $payroll->update(['status' => 'paid']);

        // Mark linked reimbursements as paid
        $payroll->reimbursements()->where('status', 'approved')->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        return ApiResponse::success('Payroll paid', $payroll->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'employee.manager:id,name',
            'employee.manager.profile:id,user_id,profile_photo_path',
            'details',
            'reimbursements'
        ]));
    }

    /**
     * Bulk pay: mark ALL approved payrolls in a period as paid (with DB transaction).
     */
    public function bulkPay(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.pay')) {
            return ApiResponse::error('Forbidden', 'Only HR or Admin can bulk-pay payrolls', 403);
        }

        $request->validate([
            'period' => 'required|string|max:7', // YYYY-MM
        ]);

        $period = $request->period;

        $query = Payroll::with('reimbursements')
            ->where('period', $period)
            ->where('status', 'approved');
        $this->companyScope->applyThroughEmployee($query, $request);
        $payrolls = $query->get();

        if ($payrolls->isEmpty()) {
            return ApiResponse::error("No approved payrolls found for period {$period}", null, 404);
        }

        DB::transaction(function () use ($payrolls) {
            foreach ($payrolls as $payroll) {
                $payroll->update(['status' => 'paid']);

                // Mark linked reimbursements as paid
                $payroll->reimbursements()->where('status', 'approved')->update([
                    'status'  => 'paid',
                    'paid_at' => now(),
                ]);
            }
        });

        return ApiResponse::success("Bulk pay completed for period {$period}", [
            'period' => $period,
            'total_paid' => $payrolls->count(),
            'data' => $payrolls->load([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'details'
            ])
        ]);
    }

    // =========================================================================
    // SLIP & EXPORT
    // =========================================================================

    public function myPayrollSlip(Request $request, int $id): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        $payroll = Payroll::with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'employee.manager:id,name',
                'employee.manager.profile:id,user_id,profile_photo_path',
                'details',
                'reimbursements'
            ])
            ->where('id', $id)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$payroll) {
            return ApiResponse::error('Payroll not found', null, 404);
        }

        return ApiResponse::success('Payroll slip retrieved successfully', $this->buildSlipPayload($payroll));
    }

    public function slip(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.view')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $query = Payroll::with([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'employee.manager:id,name',
            'employee.manager.profile:id,user_id,profile_photo_path',
            'details',
            'reimbursements'
        ]);
        $this->companyScope->applyThroughEmployee($query, $request);
        $payroll = $query->find($id);

        if (!$payroll) {
            return ApiResponse::error('Payroll not found', null, 404);
        }

        return ApiResponse::success('Payroll slip retrieved successfully', $this->buildSlipPayload($payroll));
    }

    public function exportSlipCsv(Request $request, int $id)
    {
        $user    = $request->user();
        $query = Payroll::with([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'details'
        ]);
        $this->companyScope->applyThroughEmployee($query, $request);
        $payroll = $query->find($id);

        if (!$payroll) {
            return ApiResponse::error('Payroll not found', null, 404);
        }

        if ($payroll->employee?->user_id !== $user->id && !$user->hasPermission('payroll.export')) {
            return ApiResponse::error('Forbidden', 'You cannot access this payroll', 403);
        }

        $payload  = $this->buildSlipPayload($payroll);
        $filename = 'payroll-slip-' . $payroll->period . '-employee-' . $payroll->employee_id . '.csv';

        $rows = [
            ['Item', 'Amount'],
            ['Employee', $payload['employee']['name']],
            ['Employee Code', $payload['employee']['employee_code']],
            ['Period', $payload['period']],
            ['Status', $payload['status']],
            ['Basic Salary', (string) $payload['summary']['basic_salary']],
            ['Allowance', (string) $payload['summary']['allowance']],
            ['Bonus', (string) $payload['summary']['bonus']],
            ['Overtime Pay', (string) $payload['summary']['overtime_pay']],
            ['Paid Leave Amount', (string) $payload['summary']['paid_leave_amount']],
            ['Reimbursement', (string) $payload['summary']['reimbursement_amount']],
            ['Gross Pay', (string) $payload['summary']['gross_pay']],
            ['BPJS Kesehatan', (string) $payload['summary']['bpjs_kesehatan']],
            ['BPJS Ketenagakerjaan', (string) $payload['summary']['bpjs_ketenagakerjaan']],
            ['PPh 21', (string) $payload['summary']['pph21']],
            ['Late Deduction', (string) $payload['summary']['late_deduction']],
            ['Total Deduction', (string) $payload['summary']['total_deduction']],
            ['Take Home Pay', (string) $payload['summary']['take_home_pay']],
        ];

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportSlipPdf(Request $request, int $id)
    {
        $user    = $request->user();
        $query = Payroll::with([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'details'
        ]);
        $this->companyScope->applyThroughEmployee($query, $request);
        $payroll = $query->find($id);

        if (!$payroll) {
            return ApiResponse::error('Payroll not found', null, 404);
        }

        if ($payroll->employee?->user_id !== $user->id && !$user->hasPermission('payroll.export')) {
            return ApiResponse::error('Forbidden', 'You cannot access this payroll', 403);
        }

        $payload  = $this->buildSlipPayload($payroll);
        $filename = 'payroll-slip-' . str_replace(['/', '\\', ' '], '-', (string) $payroll->period) . '-employee-' . $payroll->employee_id . '.pdf';

        if (class_exists('Barryvdh\\DomPDF\\Facade\\Pdf')) {
            $pdfFacade = 'Barryvdh\\DomPDF\\Facade\\Pdf';
            return $pdfFacade::loadView('pdf.payroll-slip', ['payload' => $payload])->download($filename);
        }

        if (app()->bound('dompdf.wrapper')) {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('pdf.payroll-slip', ['payload' => $payload]);
            return $pdf->download($filename);
        }

        return ApiResponse::error('PDF engine not available.', null, 500);
    }

    public function exportBcaKlikPay(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.export')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $request->validate([
            'period' => 'required|string|max:50',
            'bank'   => 'nullable|string|max:100',
        ]);

        $period     = $request->input('period');
        $bankFilter = $request->input('bank');

        $query = Payroll::with(['employee.user.profile', 'employee.user'])
            ->where('period', $period)
            ->whereIn('status', ['approved', 'paid']);
        $this->companyScope->applyThroughEmployee($query, $request);

        if ($bankFilter) {
            $query->whereHas('employee.user.profile', function ($q) use ($bankFilter) {
                $q->where('bank_name', 'like', '%' . $bankFilter . '%');
            });
        }

        $payrolls = $query->get();

        if ($payrolls->isEmpty()) {
            return ApiResponse::error('No payroll data found for the selected period', null, 404);
        }

        $headers     = ['No', 'Account Number', 'Account Name', 'Amount', 'Description', 'Email'];
        $lines       = [implode(',', $headers)];
        $totalAmount = 0;

        foreach ($payrolls as $index => $payroll) {
            $profile           = $payroll->employee?->user?->profile;
            $bankAccountNumber = $profile?->bank_account_number ?? '';
            $bankAccountName   = $profile?->bank_account_name ?? $payroll->employee?->user?->name ?? '';
            $bankName          = $profile?->bank_name ?? '';
            $amount            = (float) $payroll->take_home_pay;
            $email             = $payroll->employee?->user?->email ?? '';

            if ($bankFilter && stripos($bankName, $bankFilter) === false) {
                continue;
            }

            $totalAmount += $amount;

            $lines[] = implode(',', [
                $index + 1,
                $bankAccountNumber,
                $bankAccountName,
                number_format($amount, 2, '.', ''),
                'Gaji ' . $period,
                $email,
            ]);
        }

        $lines[]  = implode(',', ['', '', 'TOTAL', number_format($totalAmount, 2, '.', ''), '', '']);
        $filename = 'bca-klikpay-' . str_replace(['/', '\\', ' ', '-'], '', $period) . '.csv';
        $content  = implode("\n", $lines) . "\n";

        return response($content, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportPayrollSummaryCsv(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('payroll.export')) {
            return ApiResponse::error('Forbidden', 'You are not authorized', 403);
        }

        $request->validate(['period' => 'required|string|max:50']);

        $period   = $request->input('period');
        $query = Payroll::with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
            ])
            ->where('period', $period);
        $this->companyScope->applyThroughEmployee($query, $request);
        $payrolls = $query->get();

        if ($payrolls->isEmpty()) {
            return ApiResponse::error('No payroll data found for the selected period', null, 404);
        }

        $headers = ['No', 'Employee Code', 'Name', 'Department', 'Position', 'Bank', 'Account Number', 'Account Name', 'Basic Salary', 'Allowance', 'Bonus', 'Overtime', 'Paid Leave', 'Reimbursement', 'BPJS Kesehatan', 'BPJS TK', 'PPh 21', 'Late Deduction', 'Total Deduction', 'Take Home Pay', 'Status'];
        $lines   = [implode(',', $headers)];

        $totalNet = 0;

        foreach ($payrolls as $index => $payroll) {
            $profile = $payroll->employee?->user?->profile;
            $net     = (float) $payroll->take_home_pay;
            $totalNet += $net;

            $lines[] = implode(',', [
                $index + 1,
                $payroll->employee?->employee_code ?? '',
                $payroll->employee?->user?->name ?? '',
                $payroll->employee?->department?->name ?? '',
                $payroll->employee?->position?->name ?? '',
                $profile?->bank_name ?? '',
                $profile?->bank_account_number ?? '',
                $profile?->bank_account_name ?? '',
                number_format((float) $payroll->basic_salary, 2, '.', ''),
                number_format((float) $payroll->allowance, 2, '.', ''),
                number_format((float) $payroll->bonus, 2, '.', ''),
                number_format((float) $payroll->overtime_pay, 2, '.', ''),
                number_format((float) $payroll->paid_leave_amount, 2, '.', ''),
                number_format((float) $payroll->reimbursement_amount, 2, '.', ''),
                number_format((float) $payroll->bpjs_kesehatan, 2, '.', ''),
                number_format((float) $payroll->bpjs_ketenagakerjaan, 2, '.', ''),
                number_format((float) $payroll->pph21, 2, '.', ''),
                number_format((float) $payroll->late_deduction, 2, '.', ''),
                number_format((float) $payroll->total_deduction, 2, '.', ''),
                number_format($net, 2, '.', ''),
                $payroll->status,
            ]);
        }

        $lines[]  = implode(',', array_fill(0, count($headers) - 2, '')) . ',TOTAL,' . number_format($totalNet, 2, '.', '') . ',';
        $filename = 'payroll-summary-' . str_replace(['/', '\\', ' ', '-'], '', $period) . '.csv';
        $content  = implode("\n", $lines) . "\n";

        return response($content, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // =========================================================================
    // PRIVATE HELPER
    // =========================================================================

    private function buildSlipPayload(Payroll $payroll): array
    {
        $details    = $payroll->details->map(fn($d) => [
            'id'     => $d->id,
            'type'   => $d->type,
            'name'   => $d->name,
            'amount' => (float) $d->amount,
        ])->values();

        $earnings   = $details->where('type', 'allowance')->values();
        $deductions = $details->where('type', 'deduction')->values();

        // FIX: include paid_leave_amount and reimbursement_amount in gross_pay
        $grossPay = (float) $payroll->basic_salary
            + (float) $payroll->allowance
            + (float) $payroll->bonus
            + (float) $payroll->overtime_pay
            + (float) $payroll->paid_leave_amount
            + (float) $payroll->reimbursement_amount;

        $netEarnings   = $grossPay + $earnings->sum('amount');
        $netDeductions = (float) $payroll->total_deduction + $deductions->sum('amount');

        return [
            'id'       => $payroll->id,
            'period'   => $payroll->period,
            'status'   => $payroll->status,
            'employee' => [
                'id'            => $payroll->employee?->id,
                'employee_code' => $payroll->employee?->employee_code,
                'name'          => $payroll->employee?->user?->name,
                'email'         => $payroll->employee?->user?->email,
                'department'    => $payroll->employee?->department?->name,
                'position'      => $payroll->employee?->position?->name,
            ],
            'summary'  => [
                'basic_salary'          => (float) $payroll->basic_salary,
                'allowance'             => (float) $payroll->allowance,
                'bonus'                 => (float) $payroll->bonus,
                'overtime_pay'          => (float) $payroll->overtime_pay,
                'paid_leave_days'       => (float) $payroll->paid_leave_days,
                'paid_leave_amount'     => (float) $payroll->paid_leave_amount,
                'reimbursement_amount'  => (float) $payroll->reimbursement_amount,
                'gross_pay'             => $grossPay,
                'additional_allowances' => $earnings->sum('amount'),
                'late_days'             => (int) $payroll->late_days,
                'late_deduction'        => (float) $payroll->late_deduction,
                'bpjs_kesehatan'        => (float) $payroll->bpjs_kesehatan,
                'bpjs_ketenagakerjaan'  => (float) $payroll->bpjs_ketenagakerjaan,
                'pph21'                 => (float) $payroll->pph21,
                'additional_deductions' => $deductions->sum('amount'),
                'total_deduction'       => $netDeductions,
                'take_home_pay'         => (float) $payroll->take_home_pay,
            ],
            'earnings'         => $earnings,
            'deductions'       => $deductions,
            'details'          => $details,
            'reimbursements'   => $payroll->reimbursements ?? [],
            'approval_trail'   => [
                'manager_approved_by' => $payroll->manager_approved_by,
                'manager_approved_at' => $payroll->manager_approved_at,
                'hr_approved_by'      => $payroll->hr_approved_by,
                'hr_approved_at'      => $payroll->hr_approved_at,
                'rejected_by'         => $payroll->rejected_by,
                'rejected_reason'     => $payroll->rejected_reason,
            ],
        ];
    }
}

