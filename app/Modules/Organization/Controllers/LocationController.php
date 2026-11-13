<?php

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Location;
use App\Services\CompanyScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
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
     * GET /locations - List all locations with pagination
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('location.view')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            $validated = $request->validate([
                'per_page' => 'sometimes|integer|min:1|max:100',
                'search'   => 'sometimes|string|max:255',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $search = $validated['search'] ?? null;

            $query = Location::select(['id', 'name', 'latitude', 'longitude', 'radius', 'company_id', 'created_at', 'updated_at']);
            $this->applyCompanyScope($query, $request);

            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            $locations = $query->latest('id')->paginate($perPage)->withQueryString();

            return ApiResponse::success(
                $locations->isEmpty() ? 'No locations available' : 'Location list',
                $locations
            );

        } catch (ValidationException $e) {
            return ApiResponse::error('Invalid query parameters', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch locations', null, 500);
        }
    }

    /**
     * GET /attendance/locations - List active locations for attendance check-in (employee accessible)
     */
    public function activeLocations(Request $request): JsonResponse
    {
        try {
            $locations = Location::select(['id', 'name', 'latitude', 'longitude', 'radius'])->get();
            return ApiResponse::success('Location list', $locations);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch locations', null, 500);
        }
    }

    /**
     * POST /locations - Create new location
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('location.create')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            $validated = $request->validate([
                'name'      => 'required|string|max:255|unique:locations,name',
                'latitude'  => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius'    => 'required|integer|min:10|max:5000',
            ]);

            $validated['company_id'] = $validated['company_id'] ?? (new CompanyScopeService())->selectedCompanyId($request);

            $location = Location::create($validated);

            return ApiResponse::success('Location created successfully', $location, 201);

        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create location', null, 500);
        }
    }

    /**
     * GET /locations/{id} - Show specific location
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('location.view')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'Invalid location ID']);
            }

            $location = Location::select(['id', 'name', 'latitude', 'longitude', 'radius', 'created_at', 'updated_at'])
                ->findOrFail($id);

            return ApiResponse::success('Location detail', $location);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Not found', 'Location not found', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Invalid request', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch location', null, 500);
        }
    }

    /**
     * PUT /locations/{id} - Update location
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('location.update')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'Invalid location ID']);
            }

            $location = Location::findOrFail($id);

            $validated = $request->validate([
                'name'      => 'sometimes|string|max:255|unique:locations,name,' . $id,
                'latitude'  => 'sometimes|numeric|between:-90,90',
                'longitude' => 'sometimes|numeric|between:-180,180',
                'radius'    => 'sometimes|integer|min:10|max:5000',
            ]);

            $location->update($validated);

            return ApiResponse::success('Location updated successfully', $location->fresh());

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Not found', 'Location not found', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update location', null, 500);
        }
    }

    /**
     * DELETE /locations/{id} - Delete location
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('location.delete')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'Invalid location ID']);
            }

            $location = Location::select(['id', 'name', 'latitude', 'longitude', 'radius'])
                ->findOrFail($id);

            $deleted = $location->toArray();
            $location->delete();

            return ApiResponse::success('Location deleted successfully', $deleted);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Not found', 'Location not found', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Invalid request', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete location', null, 500);
        }
    }
}
