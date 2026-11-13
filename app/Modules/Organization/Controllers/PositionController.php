<?php

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Modules\Organization\Models\Position;
use App\Services\CompanyScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PositionController extends Controller
{
    private function applyCompanyScope($query, Request $request)
    {
        $scope = new CompanyScopeService();
        $selected = $scope->selectedCompanyId($request);

        if ($selected) {
            return $query->where(fn ($q) => $q->where('company_id', $selected)->orWhereNull('company_id'));
        }

        if (!$scope->canViewAll($request->user())) {
            $ids = $scope->availableCompanyIds($request->user());
            return $query->where(fn ($q) => $q->whereIn('company_id', $ids)->orWhereNull('company_id'));
        }

        return $query;
    }

    /**
     * GET /positions - List all positions with pagination
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('position.view')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            $validated = $request->validate([
                'per_page'      => 'sometimes|integer|min:1|max:100',
                'search'        => 'sometimes|string|max:255',
                'department_id' => 'sometimes|integer',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $search = $validated['search'] ?? null;
            $departmentId = $validated['department_id'] ?? null;

            $query = Position::with(['department:id,name'])->withCount('employees');
            $this->applyCompanyScope($query, $request);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%')
                      ->orWhere('level', 'like', '%' . $search . '%');
                });
            }

            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }

            $positions = $query->latest('id')->paginate($perPage)->withQueryString();

            return ApiResponse::success(
                $positions->isEmpty() ? 'No positions available' : 'Position list',
                $positions
            );

        } catch (ValidationException $e) {
            return ApiResponse::error('Invalid query parameters', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch positions', null, 500);
        }
    }

    /**
     * POST /positions - Create new position
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('position.create')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            $validated = $request->validate([
                'name'          => 'required|string|max:255|unique:positions,name',
                'code'          => 'nullable|string|max:50|unique:positions,code',
                'level'         => 'nullable|string|max:100',
                'department_id' => 'nullable|integer|exists:departments,id',
            ]);

            $validated['company_id'] = $validated['company_id'] ?? (new CompanyScopeService())->selectedCompanyId($request);

            $position = Position::create($validated);
            $position->load('department');

            return ApiResponse::success('Position created successfully', $position, 201);

        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create position', null, 500);
        }
    }

    /**
     * GET /positions/{id} - Show specific position
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('position.view')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'Invalid position ID']);
            }

            $position = Position::with([
                'department:id,name', 
                'employees:id,user_id,employee_code,department_id,position_id',
                'employees.user:id,name,email',
                'employees.user.profile:id,user_id,profile_photo_path',
                'employees.department:id,name'
            ])
                ->withCount('employees')
                ->findOrFail($id);

            return ApiResponse::success('Position detail', $position);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Not found', 'Position not found', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Invalid request', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch position', null, 500);
        }
    }

    /**
     * PUT /positions/{id} - Update position
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('position.update')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'Invalid position ID']);
            }

            $position = Position::findOrFail($id);

            $validated = $request->validate([
                'name'          => 'sometimes|string|max:255|unique:positions,name,' . $id,
                'code'          => 'sometimes|nullable|string|max:50|unique:positions,code,' . $id,
                'level'         => 'sometimes|nullable|string|max:100',
                'department_id' => 'sometimes|nullable|integer|exists:departments,id',
            ]);

            $position->update($validated);
            $position->load('department');

            return ApiResponse::success('Position updated successfully', $position->fresh(['department:id,name']));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Not found', 'Position not found', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update position', null, 500);
        }
    }

    /**
     * DELETE /positions/{id} - Delete position
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('position.delete')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'Invalid position ID']);
            }

            $position = Position::findOrFail($id);

            if ($position->employees()->count() > 0) {
                return ApiResponse::error('Cannot delete position', 'Position has employees assigned', 400);
            }

            $deleted = $position->toArray();
            $position->delete();

            return ApiResponse::success('Position deleted successfully', $deleted);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Not found', 'Position not found', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Invalid request', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete position', null, 500);
        }
    }
}

