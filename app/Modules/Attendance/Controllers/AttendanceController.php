<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\CheckInRequest;
use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Services\CompanyScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService,
        protected CompanyScopeService $companyScope
    ) {}

    /**
     * Check-in with geofencing validation.
     * 
     * @param CheckInRequest $request Validated: latitude, longitude
     * @return JsonResponse
     */
    public function checkIn(CheckInRequest $request): JsonResponse
    {
        if (!$request->user()->hasPermission('attendance.check_in')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        try {
            $result = $this->attendanceService->checkIn(
                $request->user(),
                $request->validated()['latitude'],
                $request->validated()['longitude']
            );

            return ApiResponse::success('Check-in successful', [
                'location' => $result['location'],
                'distance' => $result['distance'] . ' meter',
                'data'     => $result['attendance']->load([
                    'user:id,name,email',
                    'user.profile:id,user_id,profile_photo_path',
                    'user.employee:id,user_id,department_id,position_id',
                    'user.employee.departmentRel:id,name',
                    'user.employee.positionRel:id,name',
                ]),
                'status'   => $result['attendance']->status,
            ]);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::error('Check-in failed', null, 500);
        }
    }

    /**
     * Check-out for today's attendance.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkOut(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('attendance.check_out')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        try {
            $result = $this->attendanceService->checkOut($request->user());

            $message = 'Check-out successful';
            if ($result['overtime_request']) {
                $minutes = $result['overtime_request']->overtime_minutes;
                $message .= '. Overtime detected: ' . floor($minutes / 60) . 'h ' . ($minutes % 60) . 'm (pending approval)';
            }

            return ApiResponse::success($message, [
                'attendance' => $result['attendance']->load([
                    'user:id,name,email',
                    'user.profile:id,user_id,profile_photo_path',
                    'user.employee:id,user_id,department_id,position_id',
                    'user.employee.departmentRel:id,name',
                    'user.employee.positionRel:id,name',
                ]),
                'overtime_request' => $result['overtime_request'],
            ]);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::error('Check-out failed', null, 500);
        }
    }

    /**
     * Get attendance history for the current user.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('attendance.view_own')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        try {
            $data = $this->attendanceService->getHistory($request->user());
            if ($data instanceof \Illuminate\Pagination\LengthAwarePaginator) {
                $data->getCollection()->transform(function ($item) {
                    return $item->load([
                        'user:id,name,email',
                        'user.profile:id,user_id,profile_photo_path',
                        'user.employee:id,user_id,department_id,position_id',
                        'user.employee.department:id,name',
                        'user.employee.position:id,name',
                    ]);
                });
            }

            return ApiResponse::success('Attendance history', $data);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch history', null, 500);
        }
    }

    /**
     * Get today's attendance for the current user.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function today(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('attendance.view_own')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        try {
            $attendance = $this->attendanceService->getToday($request->user());
            if ($attendance) {
                $attendance->load([
                    'user:id,name,email',
                    'user.profile:id,user_id,profile_photo_path',
                    'user.employee:id,user_id,department_id,position_id',
                    'user.employee.departmentRel:id,name',
                    'user.employee.positionRel:id,name',
                ]);
            }

            return ApiResponse::success('Today attendance', $attendance);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch today attendance', null, 500);
        }
    }

    /**
     * Attendance intelligence summary for the current user.
     */
    public function intelligence(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('attendance.view_own')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
        ]);

        try {
            $data = $this->attendanceService->getMyIntelligence($request->user(), $validated['days'] ?? 30);

            return ApiResponse::success('Attendance intelligence', $data);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch attendance intelligence', null, 500);
        }
    }

    /**
     * Overtime summary for the current user.
     */
    public function overtime(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('attendance.view_own')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
        ]);

        try {
            $data = $this->attendanceService->getMyOvertime($request->user(), $validated['days'] ?? 30);

            return ApiResponse::success('Overtime summary', $data);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch overtime summary', null, 500);
        }
    }

    /**
     * Get all attendance records with pagination and filtering (admin only).
     * 
     * Query params:
     * - per_page: int (default: 10, max: 100)
     * - sort_by: string (default: 'date', options: 'date', 'user_id', 'status')
     * - sort_order: string (default: 'desc', options: 'asc', 'desc')
     * - date_from: string (Y-m-d)
     * - date_to: string (Y-m-d)
    * - status: string (on_time, late, absent)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function all(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('attendance.view_all')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        try {
            // Validate query parameters
            $validated = $request->validate([
                'per_page'  => 'sometimes|integer|min:1|max:100',
                'sort_by'   => 'sometimes|in:date,user_id,status',
                'sort_order'=> 'sometimes|in:asc,desc',
                'date_from' => 'sometimes|date_format:Y-m-d',
                'date_to'   => 'sometimes|date_format:Y-m-d|after_or_equal:date_from',
                'status'    => 'sometimes|in:on_time,late,absent',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $sortBy = $validated['sort_by'] ?? 'date';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            // Optimized query with eager loading
            $query = Attendance::with([
                'user:id,name,email',
                'user.profile:id,user_id,phone,address,profile_photo_path',
                'user.employee:id,user_id,employee_code,department_id,position_id',
                'user.employee.department:id,name',
                'user.employee.position:id,name',
            ])
                ->select(['id', 'user_id', 'date', 'check_in', 'check_out', 'latitude', 'longitude', 'status', 'created_at']);

            $selectedCompanyId = $this->companyScope->selectedCompanyId($request);
            if ($selectedCompanyId) {
                $query->whereHas('user.employee', fn ($employeeQuery) => $employeeQuery->where('company_id', $selectedCompanyId));
            } elseif (!$this->companyScope->canViewAll($request->user())) {
                $allowedCompanyIds = $this->companyScope->availableCompanyIds($request->user());
                $query->whereHas('user.employee', fn ($employeeQuery) => $employeeQuery->whereIn('company_id', $allowedCompanyIds));
            }

            // Apply filters
            if (!empty($validated['date_from'])) {
                $query->whereDate('date', '>=', $validated['date_from']);
            }
            if (!empty($validated['date_to'])) {
                $query->whereDate('date', '<=', $validated['date_to']);
            }
            if (!empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            // Apply sorting with protection against injection
            $query->orderBy($sortBy, $sortOrder);

            $data = $query->paginate($perPage)->withQueryString();

            return ApiResponse::success('All attendance records', $data);
        } catch (ValidationException $e) {
            return ApiResponse::error('Invalid query parameters', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch records', null, 500);
        }
    }

    /**
     * Show a specific attendance record detail.
     * 
     * Security: Non-owners can only view if they have attendance.view_all permission
     * 
     * @param Request $request
     * @param int $id Attendance record ID
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            // Validate ID to prevent invalid casting
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'Invalid attendance ID']);
            }

            // Optimized query with eager loading
            $query = Attendance::with([
                'user:id,name,email',
                'user.profile:id,user_id,phone,address,profile_photo_path',
                'user.employee:id,user_id,employee_code,department_id,position_id',
                'user.employee.department:id,name',
                'user.employee.position:id,name',
            ])
            ->select(['id', 'user_id', 'date', 'check_in', 'check_out', 'latitude', 'longitude', 'status', 'created_at']);
            $this->companyScope->applyEmployeeScope($query, $request, 'company_id');
            $attendance = $query->findOrFail($id);

            $user = $request->user();

            // Authorization: Owner can view own record, others need permission
            if ($attendance->user_id !== $user->id && !$user->hasPermission('attendance.view_all')) {
                return ApiResponse::error('Forbidden', 'You cannot access this record', 403);
            }

            return ApiResponse::success('Attendance detail', $attendance);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Not found', 'Attendance record not found', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Invalid request', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch record', null, 500);
        }
    }

    /**
     * Delete an attendance record (admin only).
     * 
     * @param Request $request
     * @param int $id Attendance record ID
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('attendance.delete')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        try {
            // Validate ID
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'Invalid attendance ID']);
            }

            // Find and soft-delete record (if using soft deletes) or hard delete
            $query = Attendance::select(['id', 'user_id', 'date', 'check_in', 'check_out', 'latitude', 'longitude', 'status']);
            $this->companyScope->applyEmployeeScope($query, $request, 'company_id');
            $attendance = $query->findOrFail($id);

            // Convert to array BEFORE deleting to ensure we have the data
            $deleted = $attendance->toArray();

            $attendance->delete();

            return ApiResponse::success('Attendance record deleted', $deleted);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Not found', 'Attendance record not found', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Invalid request', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete record', null, 500);
        }
    }

    /**
     * Attendance intelligence for admin/manager by employee user ID.
     */
    public function employeeIntelligence(Request $request, int $userId): JsonResponse
    {
        if (!$request->user()->hasPermission('attendance.view_all')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
        ]);

        try {
            $employeeQuery = \App\Modules\Employee\Models\Employee::where('user_id', $userId);
            $this->companyScope->applyEmployeeScope($employeeQuery, $request, 'company_id');
            $employeeQuery->firstOrFail();

            $data = $this->attendanceService->getEmployeeIntelligence($userId, $validated['days'] ?? 30);

            return ApiResponse::success('Employee attendance intelligence', $data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Not found', 'Employee not found', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch employee attendance intelligence', null, 500);
        }
    }
}
