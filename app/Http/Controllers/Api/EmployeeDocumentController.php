<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeDocument;
use App\Modules\User\Models\UserNotification;
use App\Services\ApprovalFlowService;
use App\Traits\HasEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class EmployeeDocumentController extends Controller
{
    use HasEmployee;

    public function contracts(Request $request): JsonResponse
    {
        if (!$this->canManageDocuments($request)) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'employee_id' => ['sometimes', 'integer', 'exists:employees,id'],
            'status' => ['sometimes', 'string', 'in:pending,approved,rejected,expired,archived'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:255'],
        ]);

        $days = $validated['days'] ?? 90;
        $from = now()->startOfDay();
        $until = now()->addDays($days)->endOfDay();

        $query = EmployeeDocument::with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'uploader:id,name,email',
                'uploader.profile:id,user_id,profile_photo_path',
                'reviewer:id,name,email',
                'reviewer.profile:id,user_id,profile_photo_path'
            ])
            ->where(function ($builder) {
                $builder->where('category', 'contract')
                    ->orWhere('document_type', 'contract')
                    ->orWhere('document_type', 'employment_contract')
                    ->orWhere('document_type', 'pkwt')
                    ->orWhere('document_type', 'pkwtt');
            })
            ->latest();

        if (!empty($validated['employee_id'])) {
            $query->where('employee_id', $validated['employee_id']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', '%' . $search . '%')
                    ->orWhere('document_type', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%')
                    ->orWhere('file_name', 'like', '%' . $search . '%');
            });
        }

        $contracts = $query->paginate($validated['per_page'] ?? 10)->withQueryString();

        $baseCountQuery = EmployeeDocument::query()
            ->where(function ($builder) {
                $builder->where('category', 'contract')
                    ->orWhere('document_type', 'contract')
                    ->orWhere('document_type', 'employment_contract')
                    ->orWhere('document_type', 'pkwt')
                    ->orWhere('document_type', 'pkwtt');
            });

        if (!empty($validated['employee_id'])) {
            $baseCountQuery->where('employee_id', $validated['employee_id']);
        }

        $summary = [
            'total_contracts' => (clone $baseCountQuery)->count(),
            'active_contracts' => (clone $baseCountQuery)
                ->where('status', EmployeeDocument::STATUS_APPROVED)
                ->where(function ($q) use ($from) {
                    $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', $from->toDateString());
                })
                ->count(),
            'expiring_within_days' => (clone $baseCountQuery)
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '>=', $from->toDateString())
                ->whereDate('expires_at', '<=', $until->toDateString())
                ->count(),
            'expired_contracts' => (clone $baseCountQuery)
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '<', $from->toDateString())
                ->count(),
            'pending_review' => (clone $baseCountQuery)
                ->where('status', EmployeeDocument::STATUS_PENDING)
                ->count(),
        ];

        return ApiResponse::success('Contract tracking retrieved successfully', [
            'window_days' => $days,
            'from' => $from->toDateString(),
            'to' => $until->toDateString(),
            'summary' => $summary,
            'contracts' => $contracts,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('document.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $documents = $this->buildQuery($request)->paginate($request->integer('per_page', 10))->withQueryString();
        $service = app(ApprovalFlowService::class);
        $documents->getCollection()->transform(function ($item) use ($service, $user) {
            $item->can_act = $service->canUserAct($item, $user);
            return $item;
        });

        return ApiResponse::success('Employee documents retrieved successfully', $documents);
    }

    public function myDocuments(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        $query = EmployeeDocument::with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'uploader:id,name,email',
                'uploader.profile:id,user_id,profile_photo_path',
                'reviewer:id,name,email',
                'reviewer.profile:id,user_id,profile_photo_path'
            ])
            ->where('employee_id', $employee->id)
            ->latest();

        return ApiResponse::success('My documents retrieved successfully', $query->paginate($request->integer('per_page', 10))->withQueryString());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $isPrivileged = $user->hasPermission('document.create');

        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:pending,approved,rejected,expired,archived'],
            'expires_at' => ['nullable', 'date'],
            'is_confidential' => ['sometimes', 'boolean'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        if ($isPrivileged) {
            if (empty($validated['employee_id'])) {
                return ApiResponse::error('Validation error', ['employee_id' => ['The employee_id field is required.']], 422);
            }
            $employee = Employee::findOrFail($validated['employee_id']);
        } else {
            $employee = $this->getAuthenticatedEmployee();

            if (!empty($validated['employee_id']) && (int) $validated['employee_id'] !== $employee->id) {
                return ApiResponse::error('Forbidden', 'You can only upload documents for your own employee record', 403);
            }
        }

        $file = $request->file('file');
        $storedName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
        $storedPath = $file->storeAs('employee-documents/' . $employee->id, $storedName, 'public');

        $document = EmployeeDocument::create([
            'employee_id' => $employee->id,
            'uploaded_by' => $user->id,
            'title' => $validated['title'],
            'document_type' => $validated['document_type'],
            'category' => $validated['category'] ?? null,
            'status' => $validated['status'] ?? EmployeeDocument::STATUS_PENDING,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'file_mime' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'expires_at' => $validated['expires_at'] ?? null,
            'is_confidential' => $validated['is_confidential'] ?? false,
        ]);

        try {
            $approvalService = app(ApprovalFlowService::class);
            $approvalService->applyToModel('document', $document);
            $document->refresh();
        } catch (\RuntimeException $e) {
            // No approval flow configured — fall back to direct pending
        }

        UserNotification::create([
            'user_id' => $employee->user_id,
            'sender_user_id' => $user->id,
            'title' => 'Document uploaded',
            'message' => 'A new document has been added to your employee file: ' . $document->title,
            'type' => 'document.uploaded',
            'category' => 'document_management',
            'data' => [
                'document_id' => $document->id,
                'document_type' => $document->document_type,
                'status' => $document->status,
            ],
        ]);

        return ApiResponse::success('Employee document created successfully', $document->load([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'uploader:id,name,email',
            'uploader.profile:id,user_id,profile_photo_path'
        ]), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $document = EmployeeDocument::with([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'uploader:id,name,email',
            'uploader.profile:id,user_id,profile_photo_path',
            'reviewer:id,name,email',
            'reviewer.profile:id,user_id,profile_photo_path'
        ])->find($id);

        if (!$document) {
            return ApiResponse::error('Document not found', null, 404);
        }

        if (!$this->canAccessDocument($request, $document)) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        return ApiResponse::success('Document detail', $document);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->canUpdateDocuments($request)) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $document = EmployeeDocument::find($id);

        if (!$document) {
            return ApiResponse::error('Document not found', null, 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'document_type' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:pending,approved,rejected,expired,archived'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'is_confidential' => ['sometimes', 'boolean'],
            'file' => ['sometimes', 'file', 'max:10240'],
        ]);

        if ($request->hasFile('file')) {
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $file = $request->file('file');
            $storedName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
            $storedPath = $file->storeAs('employee-documents/' . $document->employee_id, $storedName, 'public');

            $document->fill([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'file_mime' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        $document->fill($validated);
        $document->save();

        return ApiResponse::success('Employee document updated successfully', $document->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'uploader:id,name,email',
            'uploader.profile:id,user_id,profile_photo_path',
            'reviewer:id,name,email',
            'reviewer.profile:id,user_id,profile_photo_path'
        ]));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$this->canDeleteDocuments($request)) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $document = EmployeeDocument::find($id);

        if (!$document) {
            return ApiResponse::error('Document not found', null, 404);
        }

        $deleted = $document->toArray();

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return ApiResponse::success('Employee document deleted successfully', $deleted);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        if (!$this->canReviewDocuments($request)) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected,archived,pending,expired'],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $document = EmployeeDocument::with('approvalFlow.steps.role', 'approvalFlow.steps.user')->find($id);

        if (!$document) {
            return ApiResponse::error('Document not found', null, 404);
        }

        if ($document->approval_flow_id) {
            try {
                $approvalService = app(ApprovalFlowService::class);
                $action = $validated['status'] === 'approved' ? 'approved' : 'rejected';
                $result = $approvalService->processApproval($document, $request->user(), $action, $validated['review_notes'] ?? null);

                if ($document->employee?->user) {
                    UserNotification::create([
                        'user_id' => $document->employee->user_id,
                        'sender_user_id' => $request->user()->id,
                        'title' => 'Document reviewed',
                        'message' => 'Your document "' . $document->title . '" has been reviewed.',
                        'type' => 'document.reviewed',
                        'category' => 'document_management',
                        'data' => [
                            'document_id' => $document->id,
                            'status' => $result['model']->status,
                        ],
                    ]);
                }

                return ApiResponse::success('Employee document reviewed successfully', $result['model']->fresh([
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id,profile_photo_path',
                    'employee.department:id,name',
                    'employee.position:id,name',
                    'uploader:id,name,email',
                    'uploader.profile:id,user_id,profile_photo_path',
                    'reviewer:id,name,email',
                    'reviewer.profile:id,user_id,profile_photo_path'
                ]));
            } catch (\DomainException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            } catch (\RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), null, 400);
            }
        }

        $document->update([
            'status' => $validated['status'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($document->employee?->user) {
            UserNotification::create([
                'user_id' => $document->employee->user_id,
                'sender_user_id' => $request->user()->id,
                'title' => 'Document reviewed',
                'message' => 'Your document "' . $document->title . '" has been reviewed.',
                'type' => 'document.reviewed',
                'category' => 'document_management',
                'data' => [
                    'document_id' => $document->id,
                    'status' => $document->status,
                ],
            ]);
        }

        return ApiResponse::success('Employee document reviewed successfully', $document->fresh([
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'uploader:id,name,email',
            'uploader.profile:id,user_id,profile_photo_path',
            'reviewer:id,name,email',
            'reviewer.profile:id,user_id,profile_photo_path'
        ]));
    }

    public function approveDocument(Request $request, int $id): JsonResponse
    {
        if (!$this->canReviewDocuments($request)) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $document = EmployeeDocument::with('approvalFlow.steps.role', 'approvalFlow.steps.user')->findOrFail($id);

        if (!$document->approval_flow_id) {
            return ApiResponse::error('No approval flow configured for this document', null, 400);
        }

        try {
            $approvalService = app(ApprovalFlowService::class);
            $result = $approvalService->processApproval($document, $request->user(), 'approved', $request->note);

            if ($document->employee?->user) {
                UserNotification::create([
                    'user_id' => $document->employee->user_id,
                    'sender_user_id' => $request->user()->id,
                    'title' => 'Document approved',
                    'message' => 'Your document "' . $document->title . '" has been approved.',
                    'type' => 'document.approved',
                    'category' => 'document_management',
                    'data' => ['document_id' => $document->id, 'status' => $result['model']->status],
                ]);
            }

            return ApiResponse::success('Document approved', $result['model']->fresh([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'uploader:id,name,email',
                'uploader.profile:id,user_id,profile_photo_path',
                'reviewer:id,name,email',
                'reviewer.profile:id,user_id,profile_photo_path'
            ]));
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function rejectDocument(Request $request, int $id): JsonResponse
    {
        if (!$this->canReviewDocuments($request)) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $document = EmployeeDocument::with('approvalFlow.steps.role', 'approvalFlow.steps.user')->findOrFail($id);

        if (!$document->approval_flow_id) {
            return ApiResponse::error('No approval flow configured for this document', null, 400);
        }

        try {
            $approvalService = app(ApprovalFlowService::class);
            $result = $approvalService->processApproval($document, $request->user(), 'rejected', $request->note ?? $request->input('note'));

            if ($document->employee?->user) {
                UserNotification::create([
                    'user_id' => $document->employee->user_id,
                    'sender_user_id' => $request->user()->id,
                    'title' => 'Document rejected',
                    'message' => 'Your document "' . $document->title . '" has been rejected.',
                    'type' => 'document.rejected',
                    'category' => 'document_management',
                    'data' => ['document_id' => $document->id, 'status' => $result['model']->status],
                ]);
            }

            return ApiResponse::success('Document rejected', $result['model']->fresh([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'uploader:id,name,email',
                'uploader.profile:id,user_id,profile_photo_path',
                'reviewer:id,name,email',
                'reviewer.profile:id,user_id,profile_photo_path'
            ]));
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function expiring(Request $request): JsonResponse
    {
        if (!$this->canViewDocuments($request)) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        $days = $validated['days'] ?? 30;

        $documents = EmployeeDocument::with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'uploader:id,name,email',
                'uploader.profile:id,user_id,profile_photo_path'
            ])
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->startOfDay(), now()->addDays($days)->endOfDay()])
            ->orderBy('expires_at')
            ->get();

        return ApiResponse::success('Expiring documents retrieved successfully', $documents);
    }

    protected function buildQuery(Request $request)
    {
        $query = EmployeeDocument::with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'uploader:id,name,email',
                'uploader.profile:id,user_id,profile_photo_path',
                'reviewer:id,name,email',
                'reviewer.profile:id,user_id,profile_photo_path',
                'approvalFlow.steps.role',
                'approvalFlow.steps.user'
            ])->latest();

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->input('document_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', '%' . $search . '%')
                    ->orWhere('document_type', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%')
                    ->orWhere('file_name', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    protected function canAccessDocument(Request $request, EmployeeDocument $document): bool
    {
        $user = $request->user();

        if ($user->hasPermission('document.view')) {
            return true;
        }

        return $document->employee?->user_id === $user->id;
    }

    public function download(Request $request, int $id)
    {
        $document = EmployeeDocument::findOrFail($id);
        $path = $document->file_path;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json([
                'message' => 'File tidak ditemukan',
                'debug_path' => $path,
                'files' => Storage::disk('public')->files('employee-documents/1')
            ], 404);
        }

        return Storage::disk('public')->download($path);
    }

    private function canViewDocuments(Request $request): bool
    {
        $user = $request->user();

        return $user && $user->hasPermission('document.view');
    }

    private function canManageDocuments(Request $request): bool
    {
        $user = $request->user();

        return $user && $user->hasPermission('document.view');
    }

    private function canUpdateDocuments(Request $request): bool
    {
        $user = $request->user();

        return $user && $user->hasPermission('document.update');
    }

    private function canDeleteDocuments(Request $request): bool
    {
        $user = $request->user();

        return $user && $user->hasPermission('document.delete');
    }

    private function canReviewDocuments(Request $request): bool
    {
        $user = $request->user();

        return $user && $user->hasPermission('document.review');
    }
}


