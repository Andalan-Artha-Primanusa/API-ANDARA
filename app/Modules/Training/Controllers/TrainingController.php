<?php

namespace App\Modules\Training\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Models\TrainingProgressHistory;
use App\Models\TrainingProgram;
use App\Models\UserNotification;
use App\Services\ApprovalFlowService;
use App\Services\CompanyScopeService;
use App\Traits\HasEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    use HasEmployee;

    private function canUseAdminTrainingViews(Request $request): bool
    {
        $user = $request->user();

        return $user && $user->hasPermission('training.view');
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('training.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|string|in:draft,active,completed,cancelled',
            'mode' => 'sometimes|string|in:online,offline,hybrid',
            'category' => 'sometimes|string|max:100',
            'search' => 'sometimes|string|max:255',
        ]);

        $query = TrainingProgram::with([
                'enrollments.employee:id,user_id,employee_code,department_id,position_id',
                'enrollments.employee.user:id,name,email',
                'enrollments.employee.user.profile:id,user_id',
                'enrollments.employee.department:id,name',
                'enrollments.employee.position:id,name',
            ])->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['mode'])) {
            $query->where('mode', $validated['mode']);
        }

        if (!empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', '%' . $search . '%')
                    ->orWhere('provider', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%');
            });
        }

        app(CompanyScopeService::class)->applyEmployeeScope($query, $request);

        return ApiResponse::success('Training programs retrieved successfully', $query->paginate($validated['per_page'] ?? 10)->withQueryString());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('training.create')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'provider' => 'nullable|string|max:255',
            'mode' => 'required|string|in:online,offline,hybrid',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'status' => 'sometimes|string|in:draft,active,completed,cancelled',
        ]);

        $validated['company_id'] = $validated['company_id'] ?? app(CompanyScopeService::class)->selectedCompanyId($request);

        $program = TrainingProgram::create($validated);

        return ApiResponse::success('Training program created successfully', $program, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $programQuery = TrainingProgram::with([
            'enrollments.employee:id,user_id,employee_code,department_id,position_id',
            'enrollments.employee.user:id,name,email',
            'enrollments.employee.user.profile:id,user_id',
            'enrollments.employee.department:id,name',
            'enrollments.employee.position:id,name',
            'enrollments.employee.manager:id,name,email',
            'enrollments.employee.manager.profile:id,user_id'
        ]);

        app(CompanyScopeService::class)->applyEmployeeScope($programQuery, $request);

        $program = $programQuery->find($id);

        if (!$program) {
            return ApiResponse::error('Training program not found', null, 404);
        }

        $user = $request->user();
        if (!$user->hasPermission('training.view')) {
            $employee = $this->getAuthenticatedEmployee();
            $hasEnrollment = $program->enrollments->contains(fn ($enrollment) => $enrollment->employee_id === $employee->id);

            if (!$hasEnrollment) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }
        }

        return ApiResponse::success('Training program detail', $program);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('training.update')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $program = TrainingProgram::find($id);

        if (!$program) {
            return ApiResponse::error('Training program not found', null, 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'category' => 'sometimes|nullable|string|max:100',
            'description' => 'sometimes|nullable|string',
            'provider' => 'sometimes|nullable|string|max:255',
            'mode' => 'sometimes|string|in:online,offline,hybrid',
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'budget' => 'sometimes|nullable|numeric|min:0',
            'status' => 'sometimes|string|in:draft,active,completed,cancelled',
        ]);

        $program->update($validated);

        return ApiResponse::success('Training program updated successfully', $program->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('training.delete')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $program = TrainingProgram::with('enrollments')->find($id);

        if (!$program) {
            return ApiResponse::error('Training program not found', null, 404);
        }

        $deleted = $program->toArray();
        $program->delete();

        return ApiResponse::success('Training program deleted successfully', $deleted);
    }

    public function enroll(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('training.enroll')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'required|exists:employees,id',
        ]);

        $programQuery = TrainingProgram::query();
        app(CompanyScopeService::class)->applyEmployeeScope($programQuery, $request);
        $program = $programQuery->find($id);

        if (!$program) {
            return ApiResponse::error('Training program not found', null, 404);
        }

        $enrollments = [];

        foreach ($validated['employee_ids'] as $employeeId) {
            $enrollment = TrainingEnrollment::firstOrCreate(
                [
                    'training_program_id' => $program->id,
                    'employee_id' => $employeeId,
                ],
                [
                    'status' => 'enrolled',
                ]
            );

            $enrollments[] = $enrollment;

            $employee = Employee::with('user')->find($employeeId);
            if ($employee?->user) {
                UserNotification::create([
                    'user_id' => $employee->user_id,
                    'sender_user_id' => $user->id,
                    'title' => 'Training enrollment',
                    'message' => 'You have been enrolled in training: ' . $program->title,
                    'type' => 'training.enrolled',
                    'category' => 'training',
                    'data' => [
                        'training_program_id' => $program->id,
                        'training_title' => $program->title,
                    ],
                ]);
            }
        }

        $enrichedEnrollments = TrainingEnrollment::whereIn('id', collect($enrollments)->pluck('id'))
            ->with([
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id',
                'employee.department:id,name',
                'employee.position:id,name'
            ])->get();

        return ApiResponse::success('Employees enrolled successfully', $enrichedEnrollments, 201);
    }

    public function myTrainings(Request $request): JsonResponse
    {
        if ($this->canUseAdminTrainingViews($request)) {
            $query = TrainingEnrollment::with([
                    'program:id,title,category,mode,start_date,end_date,status', 
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id',
                    'employee.department:id,name',
                    'employee.position:id,name'
                ])
                ->latest();

            app(CompanyScopeService::class)->applyThroughEmployee($query, $request);

            $data = $query
                ->paginate($request->integer('per_page', 10))
                ->withQueryString();

            return ApiResponse::success('My trainings retrieved successfully', $data);
        }

        $employee = $this->getAuthenticatedEmployee();

        $data = TrainingEnrollment::with([
                'program:id,title,category,mode,start_date,end_date,status',
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id',
                'employee.department:id,name',
                'employee.position:id,name'
            ])
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return ApiResponse::success('My trainings retrieved successfully', $data);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('training.update')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'score' => 'nullable|numeric|min:0|max:100',
            'certificate_path' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $enrollmentQuery = TrainingEnrollment::with('program', 'employee.user');
        app(CompanyScopeService::class)->applyThroughEmployee($enrollmentQuery, $request);
        $enrollment = $enrollmentQuery->find($id);

        if (!$enrollment) {
            return ApiResponse::error('Training enrollment not found', null, 404);
        }

        $oldScore = $enrollment->score;
        $oldStatus = $enrollment->status;

        $enrollment->update([
            'status' => 'completed',
            'score' => $validated['score'] ?? $enrollment->score,
            'certificate_path' => $validated['certificate_path'] ?? $enrollment->certificate_path,
            'notes' => $validated['notes'] ?? $enrollment->notes,
            'completed_at' => now(),
        ]);

        TrainingProgressHistory::create([
            'training_enrollment_id' => $enrollment->id,
            'user_id' => $user->id,
            'old_score' => $oldScore,
            'new_score' => $enrollment->score,
            'old_status' => $oldStatus,
            'new_status' => 'completed',
            'notes' => $validated['notes'] ?? 'Pelatihan ditandai selesai',
        ]);

        if ($enrollment->employee?->user) {
            UserNotification::create([
                'user_id' => $enrollment->employee->user_id,
                'sender_user_id' => $user->id,
                'title' => 'Training completed',
                'message' => 'Your training has been marked completed: ' . $enrollment->program->title,
                'type' => 'training.completed',
                'category' => 'training',
                'data' => [
                    'training_program_id' => $enrollment->training_program_id,
                    'score' => $enrollment->score,
                ],
            ]);
        }

        return ApiResponse::success('Training enrollment completed successfully', $enrollment->fresh([
            'program:id,title,category,mode,start_date,end_date,status', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id',
            'employee.department:id,name',
            'employee.position:id,name'
        ]));
    }

    public function updateProgress(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('training.update')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'score' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $enrollmentQuery = TrainingEnrollment::with('program', 'employee.user');
        app(CompanyScopeService::class)->applyThroughEmployee($enrollmentQuery, $request);
        $enrollment = $enrollmentQuery->find($id);

        if (!$enrollment) {
            return ApiResponse::error('Training enrollment not found', null, 404);
        }

        $oldScore = $enrollment->score;
        $oldStatus = $enrollment->status;
        $newStatus = $oldStatus === 'pending' ? 'in_progress' : $oldStatus;

        $enrollment->update([
            'score' => $validated['score'],
            'status' => $newStatus,
            'notes' => $validated['notes'] ?? $enrollment->notes,
        ]);

        TrainingProgressHistory::create([
            'training_enrollment_id' => $enrollment->id,
            'user_id' => $user->id,
            'old_score' => $oldScore,
            'new_score' => $validated['score'],
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'notes' => $validated['notes'] ?? 'Progress diupdate',
        ]);

        return ApiResponse::success('Training progress updated successfully', $enrollment->fresh([
            'program:id,title,category,mode,start_date,end_date,status', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id',
            'employee.department:id,name',
            'employee.position:id,name'
        ]));
    }

    public function progressHistory(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$this->canUseAdminTrainingViews($request)) {
            $employee = $this->getAuthenticatedEmployee();
            $enrollmentQuery = TrainingEnrollment::query();
            app(CompanyScopeService::class)->applyThroughEmployee($enrollmentQuery, $request);
            $enrollment = $enrollmentQuery->find($id);
            if (!$enrollment || $enrollment->employee_id !== $employee->id) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }
        }

        $histories = TrainingProgressHistory::with('user:id,name')->where('training_enrollment_id', $id)->latest()->get();

        return ApiResponse::success('Progress histories retrieved', $histories);
    }

    public function enrollmentsIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('training.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $query = TrainingEnrollment::with([
                'program:id,title,category,mode,start_date,end_date,status', 
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id',
                'employee.department:id,name',
                'employee.position:id,name',
                'approvalFlow.steps.role', 
                'approvalFlow.steps.user'
            ])->latest();

        app(CompanyScopeService::class)->applyThroughEmployee($query, $request);

        $enrollments = $query->paginate($request->integer('per_page', 10))->withQueryString();

        $service = app(ApprovalFlowService::class);
        $enrollments->getCollection()->transform(function ($item) use ($service, $user) {
            $item->can_act = $service->canUserAct($item, $user);
            return $item;
        });

        return ApiResponse::success('Enrollments retrieved', $enrollments);
    }

    public function availableTrainings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|string|max:255',
        ]);

        $query = TrainingProgram::where('status', 'active')->latest();

        if (!$this->canUseAdminTrainingViews($request)) {
            $employee = $this->getAuthenticatedEmployee();

            $enrolledIds = TrainingEnrollment::where('employee_id', $employee->id)
                ->pluck('training_program_id')
                ->toArray();

            $query->whereNotIn('id', $enrolledIds);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', '%' . $search . '%')
                    ->orWhere('provider', 'like', '%' . $search . '%');
            });
        }

        app(CompanyScopeService::class)->applyEmployeeScope($query, $request);

        return ApiResponse::success('Available trainings retrieved successfully', $query->paginate($validated['per_page'] ?? 10)->withQueryString());
    }

    public function selfEnroll(Request $request, int $id): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();
        $programQuery = TrainingProgram::query();
        app(CompanyScopeService::class)->applyEmployeeScope($programQuery, $request);
        $program = $programQuery->find($id);

        if (!$program) {
            return ApiResponse::error('Training program not found', null, 404);
        }

        if ($program->status !== 'active') {
            return ApiResponse::error('This training program is not active', null, 400);
        }

        $existingEnrollment = TrainingEnrollment::where('training_program_id', $program->id)
            ->where('employee_id', $employee->id)
            ->first();

        if ($existingEnrollment) {
            return ApiResponse::error('You are already enrolled in this training program', null, 400);
        }

        $enrollment = TrainingEnrollment::create([
            'training_program_id' => $program->id,
            'employee_id' => $employee->id,
            'status' => 'pending',
        ]);

        // Apply approval flow
        try {
            $approvalService = app(ApprovalFlowService::class);
            $approvalService->applyToModel('training', $enrollment);
        } catch (\RuntimeException $e) {
            // If no approval flow configured, keep simple approval
        }

        return ApiResponse::success('Successfully requested enrollment in training program. Waiting for approval.', $enrollment->fresh([
            'program:id,title,category,mode,start_date,end_date,status', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id',
            'employee.department:id,name',
            'employee.position:id,name',
            'approvalFlow.steps.role', 
            'approvalFlow.steps.user'
        ]), 201);
    }

    public function approveEnrollment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('training.enroll')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $enrollmentQuery = TrainingEnrollment::with('program', 'employee.user', 'approvalFlow.steps.role', 'approvalFlow.steps.user');
        app(CompanyScopeService::class)->applyThroughEmployee($enrollmentQuery, $request);
        $enrollment = $enrollmentQuery->find($id);

        if (!$enrollment) {
            return ApiResponse::error('Training enrollment not found', null, 404);
        }

        // Use approval flow if configured
        if ($enrollment->approval_flow_id) {
            try {
                $approvalService = app(ApprovalFlowService::class);
                $result = $approvalService->processApproval($enrollment, $user, 'approved', $request->note);

                $enrollment = $result['model'];
                $enrollment->load([
                    'program:id,title,category,mode,start_date,end_date,status', 
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id',
                    'employee.department:id,name',
                    'employee.position:id,name',
                    'approvalFlow.steps.role', 
                    'approvalFlow.steps.user'
                ]);

                if ($result['final']) {
                    $enrollment->update(['status' => 'enrolled']);

                    if ($enrollment->employee?->user) {
                        UserNotification::create([
                            'user_id' => $enrollment->employee->user_id,
                            'sender_user_id' => $user->id,
                            'title' => 'Training Approved',
                            'message' => 'Your request to join training: ' . $enrollment->program->title . ' has been fully approved.',
                            'type' => 'training.approved',
                            'category' => 'training',
                            'data' => ['training_program_id' => $enrollment->training_program_id],
                        ]);
                    }

                    return ApiResponse::success('Training enrollment fully approved', $enrollment);
                }

                return ApiResponse::success('Disetujui - menunggu persetujuan ' . ($result['next_role'] ?? 'berikutnya'), $enrollment);
            } catch (\DomainException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            } catch (\RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), null, 500);
            }
        }

        // Fallback: simple single-step approval
        if ($enrollment->status !== 'pending') {
            return ApiResponse::error('Only pending enrollments can be approved', null, 400);
        }

        app(ApprovalFlowService::class)->guardNotSelfApproval($enrollment, $user);
        $enrollment->update(['status' => 'enrolled']);

        if ($enrollment->employee?->user) {
            UserNotification::create([
                'user_id' => $enrollment->employee->user_id,
                'sender_user_id' => $user->id,
                'title' => 'Training Approved',
                'message' => 'Your request to join training: ' . $enrollment->program->title . ' has been approved.',
                'type' => 'training.approved',
                'category' => 'training',
                'data' => [
                    'training_program_id' => $enrollment->training_program_id,
                ],
            ]);
        }

        return ApiResponse::success('Training enrollment approved', $enrollment->fresh([
            'program:id,title,category,mode,start_date,end_date,status', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id',
            'employee.department:id,name',
            'employee.position:id,name'
        ]));
    }

    public function rejectEnrollment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('training.enroll')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $enrollmentQuery = TrainingEnrollment::with('program', 'employee.user', 'approvalFlow.steps.role', 'approvalFlow.steps.user');
        app(CompanyScopeService::class)->applyThroughEmployee($enrollmentQuery, $request);
        $enrollment = $enrollmentQuery->find($id);

        if (!$enrollment) {
            return ApiResponse::error('Training enrollment not found', null, 404);
        }

        // Use approval flow if configured
        if ($enrollment->approval_flow_id) {
            try {
                $approvalService = app(ApprovalFlowService::class);
                $result = $approvalService->processApproval($enrollment, $user, 'rejected', $request->note);

                $enrollment = $result['model'];
                $enrollment->load([
                    'program:id,title,category,mode,start_date,end_date,status', 
                    'employee:id,user_id,employee_code,department_id,position_id',
                    'employee.user:id,name,email',
                    'employee.user.profile:id,user_id',
                    'employee.department:id,name',
                    'employee.position:id,name',
                    'approvalFlow.steps.role', 
                    'approvalFlow.steps.user'
                ]);

                if ($enrollment->employee?->user) {
                    UserNotification::create([
                        'user_id' => $enrollment->employee->user_id,
                        'sender_user_id' => $user->id,
                        'title' => 'Training Rejected',
                        'message' => 'Your request to join training: ' . $enrollment->program->title . ' has been rejected.',
                        'type' => 'training.rejected',
                        'category' => 'training',
                        'data' => ['training_program_id' => $enrollment->training_program_id],
                    ]);
                }

                return ApiResponse::success('Training enrollment rejected', $enrollment);
            } catch (\DomainException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            } catch (\RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), null, 500);
            }
        }

        // Fallback: simple single-step rejection
        if ($enrollment->status !== 'pending') {
            return ApiResponse::error('Only pending enrollments can be rejected', null, 400);
        }

        app(ApprovalFlowService::class)->guardNotSelfApproval($enrollment, $user, 'Anda tidak dapat menolak pengajuan training milik Anda sendiri.');
        $enrollment->update(['status' => 'cancelled']);

        if ($enrollment->employee?->user) {
            UserNotification::create([
                'user_id' => $enrollment->employee->user_id,
                'sender_user_id' => $user->id,
                'title' => 'Training Rejected',
                'message' => 'Your request to join training: ' . $enrollment->program->title . ' has been rejected.',
                'type' => 'training.rejected',
                'category' => 'training',
                'data' => [
                    'training_program_id' => $enrollment->training_program_id,
                ],
            ]);
        }

        return ApiResponse::success('Training enrollment rejected', $enrollment->fresh([
            'program:id,title,category,mode,start_date,end_date,status', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id',
            'employee.department:id,name',
            'employee.position:id,name'
        ]));
    }
}

