<?php

namespace App\Modules\Overtime\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\OvertimeRequest;
use App\Models\OvertimeEvidence;
use App\Models\UserNotification;
use App\Services\ApprovalFlowService;
use App\Services\CompanyScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OvertimeController extends Controller
{
    /**
     * GET /my/overtime - Employee's own overtime requests
     */
    public function myOvertimeRequests(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return ApiResponse::error('Employee not found', null, 404);
        }

        $requests = OvertimeRequest::where('employee_id', $employee->id)
            ->with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.departmentRel:id,name',
                'employee.positionRel:id,name',
                'attendance', 
                'approver:id,name,email',
                'approver.profile:id,user_id,profile_photo_path',
                'approver.employee:id,user_id,position_id',
                'approver.employee.position:id,name',
                'evidences'
            ])
            ->latest('date')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return ApiResponse::success('My overtime requests', $requests);
    }

    /**
     * PUT /my/overtime/{id}/reason - Employee adds reason to overtime request
     */
    public function addReason(Request $request, int $id): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return ApiResponse::error('Employee not found', null, 404);
        }

        $overtimeRequest = OvertimeRequest::where('employee_id', $employee->id)
            ->where('id', $id)
            ->first();

        if (!$overtimeRequest) {
            return ApiResponse::error('Overtime request not found', null, 404);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $overtimeRequest->update(['reason' => $validated['reason']]);

        return ApiResponse::success('Reason updated', $overtimeRequest->fresh());
    }

    /**
     * GET /overtime/requests - Admin/Manager view all overtime requests
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('overtime.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        try {
            $query = OvertimeRequest::with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.departmentRel:id,name',
                'employee.positionRel:id,name',
                'attendance',
                'approver:id,name,email',
                'approver.profile:id,user_id,profile_photo_path',
                'approver.employee:id,user_id,position_id',
                'approver.employee.position:id,name',
                'approvalFlow.steps.role',
                'approvalFlow.steps.user'
            ])
                ->latest('date');

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            app(CompanyScopeService::class)->applyThroughEmployee($query, $request);

            $data = $query->paginate($request->integer('per_page', 10))->withQueryString();
            $service = app(ApprovalFlowService::class);
            $data->getCollection()->transform(function ($item) use ($service, $user) {
                $item->can_act = $service->canUserAct($item, $user);
                return $item;
            });
            return ApiResponse::success('Overtime requests', $data);
        } catch (\Exception $e) {
            \Log::error('OvertimeController@index ERROR', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to fetch overtime requests', null, 500);
        }
    }

    /**
     * GET /overtime/requests/pending - Admin/Manager view pending requests
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('overtime.approve')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        try {
            $query = OvertimeRequest::where('status', 'pending')
                ->with([
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id,profile_photo_path',
                    'employee.departmentRel:id,name',
                    'employee.positionRel:id,name',
                    'attendance',
                    'approver:id,name,email',
                    'approver.profile:id,user_id,profile_photo_path',
                    'approver.employee:id,user_id,position_id',
                    'approver.employee.position:id,name',
                    'approvalFlow.steps.role',
                    'approvalFlow.steps.user'
                ])
                ->latest('date');

            app(CompanyScopeService::class)->applyThroughEmployee($query, $request);

            $requests = $query->paginate($request->integer('per_page', 10))
                ->withQueryString();

            $service = app(ApprovalFlowService::class);
            $requests->getCollection()->transform(function ($item) use ($service, $user) {
                $item->can_act = $service->canUserAct($item, $user);
                return $item;
            });

            return ApiResponse::success('Pending overtime requests', $requests);
        } catch (\Exception $e) {
            \Log::error('OvertimeController@pending ERROR', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to fetch pending overtime requests', null, 500);
        }
    }

    /**
     * PUT /overtime/requests/{id}/approve - Approve overtime request
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('overtime.approve')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $query = OvertimeRequest::with('employee.user', 'approvalFlow.steps.role', 'approvalFlow.steps.user');
        app(CompanyScopeService::class)->applyThroughEmployee($query, $request);
        $overtimeRequest = $query->findOrFail($id);

        // Apply approval flow on first approval if not yet configured
        if (!$overtimeRequest->approval_flow_id && $overtimeRequest->status === 'pending') {
            try {
                $approvalService = app(ApprovalFlowService::class);
                $approvalService->applyToModel('overtime', $overtimeRequest);
                $overtimeRequest->refresh();
            } catch (\RuntimeException $e) {
                // No approval flow configured, continue with simple approval
            }
        }

        // Use approval flow if configured
        if ($overtimeRequest->approval_flow_id) {
            try {
                $approvalService = app(ApprovalFlowService::class);
                $result = $approvalService->processApproval($overtimeRequest, $user, 'approved', $request->note);

                $overtimeRequest = $result['model'];
                $overtimeRequest->load([
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id,profile_photo_path',
                    'employee.departmentRel:id,name',
                    'employee.positionRel:id,name',
                    'approver:id,name,email',
                    'approver.profile:id,user_id,profile_photo_path',
                    'evidences',
                    'approvalFlow.steps.role',
                    'approvalFlow.steps.user'
                ]);

                if ($result['final']) {
                    // Send notification
                    if ($overtimeRequest->employee && $overtimeRequest->employee->user) {
                        UserNotification::create([
                            'user_id' => $overtimeRequest->employee->user->id,
                            'title' => 'Lembur Disetujui',
                            'message' => 'Pengajuan lembur Anda tanggal ' . $overtimeRequest->date->format('d M Y') . ' telah disetujui sepenuhnya.',
                            'type' => 'overtime',
                            'is_read' => false,
                        ]);
                    }
                    return ApiResponse::success('Overtime fully approved', $overtimeRequest);
                }

                return ApiResponse::success('Approved - menunggu persetujuan ' . ($result['next_role'] ?? 'berikutnya'), $overtimeRequest);
            } catch (\DomainException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            } catch (\RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), null, 500);
            }
        }

        // Fallback: simple single-step approval
        if ($overtimeRequest->status !== 'pending') {
            return ApiResponse::error('Request already processed', null, 422);
        }

        app(ApprovalFlowService::class)->guardNotSelfApproval($overtimeRequest, $user);
        $overtimeRequest->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        // Send notification to employee
        if ($overtimeRequest->employee && $overtimeRequest->employee->user) {
            UserNotification::create([
                'user_id' => $overtimeRequest->employee->user->id,
                'title' => 'Lembur Disetujui',
                'message' => 'Pengajuan lembur Anda tanggal ' . $overtimeRequest->date->format('d M Y') . ' (' . floor($overtimeRequest->overtime_minutes / 60) . 'j ' . ($overtimeRequest->overtime_minutes % 60) . 'm) telah disetujui.',
                'type' => 'overtime',
                'is_read' => false,
            ]);
        }

        return ApiResponse::success('Overtime request approved', $overtimeRequest->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.departmentRel:id,name',
            'employee.positionRel:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path',
            'evidences'
        ]));
    }

    /**
     * PUT /overtime/requests/{id}/reject - Reject overtime request
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('overtime.approve')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'reject_reason' => 'sometimes|string|max:500',
        ]);

        $query = OvertimeRequest::with('employee.user', 'approvalFlow.steps.role', 'approvalFlow.steps.user');
        app(CompanyScopeService::class)->applyThroughEmployee($query, $request);
        $overtimeRequest = $query->findOrFail($id);

        // Use approval flow if configured
        if ($overtimeRequest->approval_flow_id) {
            try {
                $approvalService = app(ApprovalFlowService::class);
                $result = $approvalService->processApproval($overtimeRequest, $user, 'rejected', $validated['reject_reason'] ?? null);

                $overtimeRequest = $result['model'];
                $overtimeRequest->update([
                    'reject_reason' => $validated['reject_reason'] ?? null,
                    'rejected_by' => $user->id,
                    'rejected_at' => now(),
                ]);

                $overtimeRequest->load([
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id,profile_photo_path',
                    'employee.departmentRel:id,name',
                    'employee.positionRel:id,name',
                    'approver:id,name,email',
                    'approver.profile:id,user_id,profile_photo_path',
                    'approvalFlow.steps.role',
                    'approvalFlow.steps.user'
                ]);

                // Send notification
                if ($overtimeRequest->employee && $overtimeRequest->employee->user) {
                    UserNotification::create([
                        'user_id' => $overtimeRequest->employee->user->id,
                        'title' => 'Lembur Ditolak',
                        'message' => 'Pengajuan lembur Anda tanggal ' . $overtimeRequest->date->format('d M Y') . ' telah ditolak.',
                        'type' => 'overtime',
                        'is_read' => false,
                    ]);
                }

                return ApiResponse::success('Overtime rejected', $overtimeRequest);
            } catch (\DomainException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            } catch (\RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), null, 500);
            }
        }

        // Fallback: simple single-step rejection
        if ($overtimeRequest->status !== 'pending') {
            return ApiResponse::error('Request already processed', null, 422);
        }

        app(ApprovalFlowService::class)->guardNotSelfApproval($overtimeRequest, $user, 'Anda tidak dapat menolak pengajuan lembur milik Anda sendiri.');
        $overtimeRequest->update([
            'status' => 'rejected',
            'reject_reason' => $validated['reject_reason'] ?? null,
            'rejected_by' => $user->id,
            'rejected_at' => now(),
        ]);

        // Send notification to employee
        if ($overtimeRequest->employee && $overtimeRequest->employee->user) {
            UserNotification::create([
                'user_id' => $overtimeRequest->employee->user->id,
                'title' => 'Lembur Ditolak',
                'message' => 'Pengajuan lembur Anda tanggal ' . $overtimeRequest->date->format('d M Y') . ' telah ditolak.' . ($validated['reject_reason'] ?? ''),
                'type' => 'overtime',
                'is_read' => false,
            ]);
        }

        return ApiResponse::success('Overtime request rejected', $overtimeRequest->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.departmentRel:id,name',
            'employee.positionRel:id,name',
            'approver:id,name,email',
            'approver.profile:id,user_id,profile_photo_path'
        ]));
    }

    /**
     * POST /my/overtime/{id}/evidence - Employee upload evidence for overtime
     */
    public function uploadEvidence(Request $request, int $id): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return ApiResponse::error('Employee not found', null, 404);
        }

        $overtimeRequest = OvertimeRequest::where('employee_id', $employee->id)
            ->where('id', $id)
            ->where('status', 'pending')
            ->first();

        if (!$overtimeRequest) {
            return ApiResponse::error('Overtime request not found or already processed', null, 404);
        }

        $validated = $request->validate([
            // Batasi tipe file yang diizinkan (whitelist MIME) dan ukuran maksimal 5MB.
            // Ini mencegah upload file berbahaya seperti .php, .exe, .sh, dll.
            'file' => [
                'required',
                'file',
                'max:5120', // 5MB max (diturunkan dari 10MB)
                'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx',
            ],
        ], [
            'file.mimes'    => 'Format file tidak didukung. Gunakan: JPG, PNG, PDF, DOC, DOCX, XLS, atau XLSX.',
            'file.max'      => 'Ukuran file maksimal 5MB.',
            'file.required' => 'File bukti wajib diupload.',
        ]);

        $file = $request->file('file');
        $path = $file->store("overtime/{$id}", 'public');

        $evidence = OvertimeEvidence::create([
            'overtime_request_id' => $overtimeRequest->id,
            'uploaded_by' => $request->user()->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_mime' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'pending',
        ]);

        return ApiResponse::success('Evidence uploaded successfully', $evidence->fresh(['uploader']));
    }

    /**
     * GET /my/overtime/{id}/evidences - Employee view their overtime evidences
     */
    public function myOvertimeEvidences(Request $request, int $id): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return ApiResponse::error('Employee not found', null, 404);
        }

        $overtimeRequest = OvertimeRequest::where('employee_id', $employee->id)
            ->where('id', $id)
            ->first();

        if (!$overtimeRequest) {
            return ApiResponse::error('Overtime request not found', null, 404);
        }

        $evidences = OvertimeEvidence::where('overtime_request_id', $overtimeRequest->id)
            ->with(['uploader:id,name', 'reviewer:id,name'])
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return ApiResponse::success('My overtime evidences', $evidences);
    }

    /**
     * GET /overtime/evidences/{id}/file - Download overtime evidence file
     */
    public function downloadEvidence(Request $request, int $id)
    {
        $query = OvertimeEvidence::with('overtimeRequest.employee');
        app(CompanyScopeService::class)->applyThroughEmployee($query, $request, 'overtimeRequest.employee');
        $evidence = $query->findOrFail($id);

        if (!$evidence->file_path || !Storage::disk('public')->exists($evidence->file_path)) {
            return ApiResponse::error('File not found', null, 404);
        }

        return Storage::disk('public')->download($evidence->file_path, $evidence->file_name ?: basename($evidence->file_path));
    }

    /**
     * GET /overtime/{id}/evidences - Manager/HR/Admin view all evidences for overtime
     */
    public function overtimeEvidences(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('overtime.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $query = OvertimeRequest::query();
        app(CompanyScopeService::class)->applyThroughEmployee($query, $request);
        $overtimeRequest = $query->findOrFail($id);

        $evidences = OvertimeEvidence::where('overtime_request_id', $overtimeRequest->id)
            ->with(['uploader:id,name', 'reviewer:id,name'])
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return ApiResponse::success('Overtime evidences', $evidences);
    }

    /**
     * PUT /overtime/evidences/{id}/approve - Manager/HR/Admin approve evidence
     */
    public function approveEvidence(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('overtime.approve')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $query = OvertimeEvidence::with('overtimeRequest.employee.user');
        app(CompanyScopeService::class)->applyThroughEmployee($query, $request, 'overtimeRequest.employee');
        $evidence = $query->findOrFail($id);

        if ($evidence->status !== 'pending') {
            return ApiResponse::error('Evidence already reviewed', null, 422);
        }

        $evidence->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Send notification to employee
        if ($evidence->overtimeRequest && $evidence->overtimeRequest->employee && $evidence->overtimeRequest->employee->user) {
            UserNotification::create([
                'user_id' => $evidence->overtimeRequest->employee->user->id,
                'title' => 'Bukti Lembur Disetujui',
                'message' => 'Bukti lembur Anda untuk tanggal ' . $evidence->overtimeRequest->date->format('d M Y') . ' telah disetujui.',
                'type' => 'overtime',
                'is_read' => false,
            ]);
        }

        return ApiResponse::success('Evidence approved', $evidence->fresh(['uploader', 'reviewer']));
    }

    /**
     * PUT /overtime/evidences/{id}/reject - Manager/HR/Admin reject evidence
     */
    public function rejectEvidence(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('overtime.approve')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'review_notes' => 'sometimes|string|max:500',
        ]);

        $query = OvertimeEvidence::with('overtimeRequest.employee.user');
        app(CompanyScopeService::class)->applyThroughEmployee($query, $request, 'overtimeRequest.employee');
        $evidence = $query->findOrFail($id);

        if ($evidence->status !== 'pending') {
            return ApiResponse::error('Evidence already reviewed', null, 422);
        }

        $evidence->update([
            'status' => 'rejected',
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Send notification to employee
        if ($evidence->overtimeRequest && $evidence->overtimeRequest->employee && $evidence->overtimeRequest->employee->user) {
            UserNotification::create([
                'user_id' => $evidence->overtimeRequest->employee->user->id,
                'title' => 'Bukti Lembur Ditolak',
                'message' => 'Bukti lembur Anda untuk tanggal ' . $evidence->overtimeRequest->date->format('d M Y') . ' ditolak. Silakan upload bukti lagi.' . ($validated['review_notes'] ? ' Catatan: ' . $validated['review_notes'] : ''),
                'type' => 'overtime',
                'is_read' => false,
            ]);
        }

        return ApiResponse::success('Evidence rejected', $evidence->fresh(['uploader', 'reviewer']));
    }

    /**
     * POST /overtime/approval - Backward-compatible endpoint accepting JSON payload { id }
     * This helper extracts `id` from the request body and delegates to `approve()`.
     */
    public function approveByBody(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'sometimes|integer',
        ]);

        $id = $validated['id'] ?? $request->query('id');

        if (!$id) {
            return ApiResponse::error('id is required', ['id' => ['The id field is required.']], 422);
        }

        return $this->approve($request, (int) $id);
    }
}
