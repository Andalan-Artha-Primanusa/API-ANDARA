<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Models\ApprovalFlowHistory;
use App\Modules\Administration\Models\AssignmentLetter;
use App\Modules\Approval\Models\ApprovalFlow;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeDocument;
use App\Services\CompanyScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;


class AssignmentLetterController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = max(1, $request->integer('per_page', 10));

$query = AssignmentLetter::with(['user.profile', 'approvalFlow.steps.role']);

            if (!$user->hasPermission('assignment_letter.view')) {
                $query->where('user_id', $user->id);
            }

            app(CompanyScopeService::class)->applyThroughEmployee($query, $request, 'user.employee');

            $letters = $query->latest()->paginate($perPage)->withQueryString();
            $letters->load('approver.profile');

            return ApiResponse::success('Assignment letters', $letters);
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal memuat surat tugas: ' . $e->getMessage(), null, 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('assignment_letter.create')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'location' => 'nullable|string|max:255',
            'user_id' => 'nullable|integer|exists:users,id',
]);

        $targetUserId = $validated['user_id'] ?? $user->id;

        if ($targetUserId !== $user->id && !$user->hasPermission('assignment_letter.create')) {
            return ApiResponse::error('Forbidden: only admin/HR can create letters for other users', null, 403);
        }

        $targetEmployee = Employee::where('user_id', $targetUserId)->first();
        if ($targetEmployee && !app(CompanyScopeService::class)->canAccessEmployeeCompany($targetEmployee->id, $user)) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $flow = ApprovalFlow::where('module', 'assignment_letter')->where('is_active', true)->with('steps')->first();
        if (!$flow) {
            return ApiResponse::error('Approval flow for assignment letter not configured', null, 500);
        }
        $letter = AssignmentLetter::create([
            ...$validated,
            'user_id' => $targetUserId,
            'approval_flow_id' => $flow->id,
            'current_step' => 1,
            'status' => 'pending',
        ]);

        $firstStep = $flow->steps->where('step_order', 1)->first();
        if ($firstStep) {
            ApprovalFlowHistory::create([
                'module' => 'assignment_letter',
                'module_id' => $letter->id,
                'approval_flow_id' => $flow->id,
                'step_order' => 1,
                'role_id' => $firstStep->role_id,
                'user_id' => $firstStep->user_id,
                'action' => 'pending',
                'acted_at' => now(),
            ]);
        }

        return ApiResponse::success('Assignment letter submitted', $letter->fresh(), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $letterQuery = AssignmentLetter::with(['user.profile', 'approvalFlow.steps.role']);
            app(CompanyScopeService::class)->applyThroughEmployee($letterQuery, $request, 'user.employee');
            $letter = $letterQuery->findOrFail($id);
            if ($letter->approved_by) {
                $letter->load('approver.profile');
            }
            $user = $request->user();
            if ($letter->user_id !== $user->id && !$user->hasPermission('assignment_letter.view')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }
            return ApiResponse::success('Assignment letter detail', $letter);
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal memuat detail surat tugas', null, 500);
        }
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $letterQuery = AssignmentLetter::with('approvalFlow.steps.role', 'approvalFlow.steps.user');
            app(CompanyScopeService::class)->applyThroughEmployee($letterQuery, $request, 'user.employee');
            $letter = $letterQuery->findOrFail($id);
            if ($letter->status !== 'pending') {
                return ApiResponse::error('Assignment letter already processed', null, 400);
            }

            if (!$letter->approvalFlow) {
                $flow = ApprovalFlow::where('module', 'assignment_letter')->where('is_active', true)->first();
                if (!$flow) {
                    return ApiResponse::error('Approval flow for assignment letter not configured', null, 500);
                }
                $letter->update(['approval_flow_id' => $flow->id]);
                $letter->load('approvalFlow.steps.role', 'approvalFlow.steps.user');
            }

            $step = $letter->approvalFlow?->steps->where('step_order', $letter->current_step)->first();
            if (!$step) {
                $availableSteps = $letter->approvalFlow?->steps->pluck('step_order')->join(', ');
                return ApiResponse::error('Approval step not found (letter_id: ' . $letter->id . ', approval_flow_id: ' . ($letter->approval_flow_id ?? 'null') . ', current_step: ' . ($letter->current_step ?? 'null') . ', available step_orders: ' . ($availableSteps ?: 'none') . ')', null, 500);
            }
            if (!$step->role) {
                return ApiResponse::error('Approval step role not found (step_id: ' . $step->id . ', role_id: ' . ($step->role_id ?? 'null') . ')', null, 500);
            }
            if (!$user->hasRole($step->role->name) && !$user->hasPermission('assignment_letter.approve')) {
                return ApiResponse::error('It is not your turn to approve', null, 403);
            }
            if ($user->id === $letter->user_id) {
                return ApiResponse::error('Anda tidak dapat menyetujui surat tugas milik Anda sendiri.', null, 403);
            }

            ApprovalFlowHistory::create([
                'module' => 'assignment_letter',
                'module_id' => $letter->id,
                'approval_flow_id' => $letter->approval_flow_id,
                'step_order' => $step->step_order,
                'role_id' => $step->role_id,
                'user_id' => $user->id,
                'action' => 'approved',
                'note' => $request->note,
                'acted_at' => now(),
            ]);

            $nextStep = $letter->approvalFlow->steps->where('step_order', $letter->current_step + 1)->first();
            if ($nextStep) {
                $letter->update(['current_step' => $letter->current_step + 1]);

                ApprovalFlowHistory::create([
                    'module' => 'assignment_letter',
                    'module_id' => $letter->id,
                    'approval_flow_id' => $letter->approval_flow_id,
                    'step_order' => $nextStep->step_order,
                    'role_id' => $nextStep->role_id,
                    'user_id' => $nextStep->user_id,
                    'action' => 'pending',
                    'acted_at' => now(),
                ]);

                return ApiResponse::success('Assignment letter advanced to next approval step', $letter->fresh(['approvalFlow.steps.role']));
            }
            $letter->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);
            return ApiResponse::success('Assignment letter approved', $letter->fresh(['approvalFlow.steps.role', 'approver.profile']));
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal menyetujui surat tugas: ' . $e->getMessage(), null, 500);
        }
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $letterQuery = AssignmentLetter::with('approvalFlow.steps.role', 'approvalFlow.steps.user');
            app(CompanyScopeService::class)->applyThroughEmployee($letterQuery, $request, 'user.employee');
            $letter = $letterQuery->findOrFail($id);
            if ($letter->status !== 'pending') {
                return ApiResponse::error('Assignment letter already processed', null, 400);
            }

            if (!$letter->approvalFlow) {
                $flow = ApprovalFlow::where('module', 'assignment_letter')->where('is_active', true)->first();
                if (!$flow) {
                    return ApiResponse::error('Approval flow for assignment letter not configured', null, 500);
                }
                $letter->update(['approval_flow_id' => $flow->id]);
                $letter->load('approvalFlow.steps.role', 'approvalFlow.steps.user');
            }

            $step = $letter->approvalFlow?->steps->where('step_order', $letter->current_step)->first();
            if (!$step) {
                $availableSteps = $letter->approvalFlow?->steps->pluck('step_order')->join(', ');
                return ApiResponse::error('Approval step not found (letter_id: ' . $letter->id . ', approval_flow_id: ' . ($letter->approval_flow_id ?? 'null') . ', current_step: ' . ($letter->current_step ?? 'null') . ', available step_orders: ' . ($availableSteps ?: 'none') . ')', null, 500);
            }
            if (!$step->role) {
                return ApiResponse::error('Approval step role not found (step_id: ' . $step->id . ', role_id: ' . ($step->role_id ?? 'null') . ')', null, 500);
            }
            if (!$user->hasRole($step->role->name) && !$user->hasPermission('assignment_letter.approve')) {
                return ApiResponse::error('It is not your turn to approve', null, 403);
            }
            if ($user->id === $letter->user_id) {
                return ApiResponse::error('Anda tidak dapat menolak surat tugas milik Anda sendiri.', null, 403);
            }

            ApprovalFlowHistory::create([
                'module' => 'assignment_letter',
                'module_id' => $letter->id,
                'approval_flow_id' => $letter->approval_flow_id,
                'step_order' => $step->step_order,
                'role_id' => $step->role_id,
                'user_id' => $user->id,
                'action' => 'rejected',
                'note' => $request->note,
                'acted_at' => now(),
            ]);

            $letter->update(['status' => 'rejected']);
            return ApiResponse::success('Assignment letter rejected', $letter->fresh(['approvalFlow.steps.role', 'approver.profile']));
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal menolak surat tugas: ' . $e->getMessage(), null, 500);
        }
    }

    public function generatePdf(Request $request, int $id): JsonResponse
    {
        try {
            $letterQuery = AssignmentLetter::with(['user.profile', 'user.employee']);
            app(CompanyScopeService::class)->applyThroughEmployee($letterQuery, $request, 'user.employee');
            $letter = $letterQuery->findOrFail($id);
            
            if ($letter->status !== 'approved') {
                return ApiResponse::error('Only approved assignment letters can generate PDF', null, 400);
            }

            $user = $request->user();
            if ($letter->user_id !== $user->id && !$user->hasPermission('assignment_letter.export')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            if (!$letter->user || !$letter->user->employee) {
                return ApiResponse::error('Employee record not found for this user', null, 404);
            }

            $now = now();
            $filename = 'assignment-letter-' . $letter->id . '-' . $now->format('YmdHis') . '.pdf';

            $pdf = Pdf::loadView('pdf.assignment-letter', [
                'letter' => $letter,
                'date' => $now->toDateString(),
            ]);

            $pdfContent = $pdf->output();
            $storedPath = 'employee-documents/' . $letter->user->employee->id . '/' . $filename;
            Storage::disk('public')->put($storedPath, $pdfContent);

            EmployeeDocument::updateOrCreate(
                [
                    'employee_id' => $letter->user->employee->id,
                    'title' => 'Surat Tugas: ' . $letter->title,
                    'document_type' => 'assignment_letter',
                ],
                [
                    'uploaded_by' => $user->id,
                    'category' => 'official_letter',
                    'status' => 'approved',
                    'file_name' => $filename,
                    'file_path' => $storedPath,
                    'file_mime' => 'application/pdf',
                    'file_size' => strlen($pdfContent),
                    'reviewed_at' => $now,
                    'reviewed_by' => $user->id,
                ]
            );

            return ApiResponse::success('Surat tugas berhasil dibuat', [
                'file_url' => Storage::disk('public')->url($storedPath),
                'filename' => $filename
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal generating PDF: ' . $e->getMessage(), null, 500);
        }
    }
}



