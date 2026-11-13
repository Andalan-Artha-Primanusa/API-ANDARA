<?php

namespace App\Modules\Competency\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\EmployeeCompetency;
use App\Modules\Employee\Models\Employee;
use App\Traits\HasEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompetencyController extends Controller
{
    use HasEmployee;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('competency.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|string|in:active,inactive',
            'search' => 'sometimes|string|max:255',
        ]);

        $query = Competency::with('employeeCompetencies.employee.user')->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('code', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%');
            });
        }

        return ApiResponse::success('Competencies retrieved successfully', $query->paginate($validated['per_page'] ?? 10)->withQueryString());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('competency.create')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:competencies,code',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        $competency = Competency::create($validated);

        return ApiResponse::success('Competency created successfully', $competency, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $competency = Competency::with('employeeCompetencies.employee.user.profile')->find($id);

        if (!$competency) {
            return ApiResponse::error('Competency not found', null, 404);
        }

        return ApiResponse::success('Competency detail', $competency);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('competency.update')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $competency = Competency::find($id);

        if (!$competency) {
            return ApiResponse::error('Competency not found', null, 404);
        }

        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:competencies,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        $competency->update($validated);

        return ApiResponse::success('Competency updated successfully', $competency->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('competency.delete')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $competency = Competency::find($id);

        if (!$competency) {
            return ApiResponse::error('Competency not found', null, 404);
        }

        $deleted = $competency->toArray();
        $competency->delete();

        return ApiResponse::success('Competency deleted successfully', $deleted);
    }

    public function assignToEmployee(Request $request, int $competencyId): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('competency.assign')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'required|exists:employees,id',
            'proficiency_level' => 'nullable|integer|min:1|max:5',
            'assessed_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $competency = Competency::find($competencyId);

        if (!$competency) {
            return ApiResponse::error('Competency not found', null, 404);
        }

        $results = [];

        foreach ($validated['employee_ids'] as $employeeId) {
            $isAssessed = !empty($validated['proficiency_level']);

            $record = EmployeeCompetency::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'competency_id' => $competency->id,
                ],
                [
                    'proficiency_level' => $validated['proficiency_level'] ?? 1,
                    'assessed_by' => $isAssessed ? $user->id : null,
                    'assessed_at' => $isAssessed ? ($validated['assessed_at'] ?? now()) : null,
                    'notes' => $validated['notes'] ?? null,
                    'status' => $isAssessed ? 'assessed' : 'pending',
                ]
            );

            $results[] = $record;
        }

        $message = $results[0]?->status === 'assessed'
            ? 'Competency assigned and assessed successfully'
            : 'Competency assigned successfully, pending assessment';

        return ApiResponse::success($message, $results, 201);
    }

    public function assessCompetency(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('competency.assign')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'proficiency_level' => 'required|integer|min:1|max:5',
            'notes' => 'nullable|string',
        ]);

        $record = EmployeeCompetency::with(['competency', 'employee'])->find($id);

        if (!$record) {
            return ApiResponse::error('Competency assignment not found', null, 404);
        }

        $record->update([
            'proficiency_level' => $validated['proficiency_level'],
            'assessed_by' => $user->id,
            'assessed_at' => now(),
            'notes' => $validated['notes'] ?? $record->notes,
            'status' => 'assessed',
        ]);

        return ApiResponse::success('Competency assessed successfully', $record->fresh(['competency', 'employee']));
    }

    public function myCompetencies(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        $data = EmployeeCompetency::with(['competency', 'assessor:id,name,email'])
            ->where('employee_id', $employee->id)
            ->latest()
            ->get();

        return ApiResponse::success('My competencies retrieved successfully', $data);
    }

    public function employeeCompetencies(Request $request, int $employeeId): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('competency.view')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $employee = Employee::find($employeeId);

        if (!$employee) {
            return ApiResponse::error('Employee not found', null, 404);
        }

        $data = EmployeeCompetency::with(['competency', 'assessor:id,name,email'])
            ->where('employee_id', $employee->id)
            ->latest()
            ->get();

        return ApiResponse::success('Employee competencies retrieved successfully', $data);
    }
}




