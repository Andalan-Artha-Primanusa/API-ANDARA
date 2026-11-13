<?php

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Company;
use App\Models\UserCompanyAccess;
use App\Services\CompanyScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    public function __construct(
        protected CompanyScopeService $companyScope
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (!$this->canViewCompanies($request->user())) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $query = Company::query()->latest();

        if (!$this->companyScope->canViewAll($request->user())) {
            $query->whereIn('id', $this->companyScope->availableCompanyIds($request->user()));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return ApiResponse::success('Companies retrieved', $query->paginate($request->integer('per_page', 20)));
    }

    public function context(Request $request): JsonResponse
    {
        if (!$this->canViewCompanies($request->user())) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        return ApiResponse::success('Company context retrieved', $this->companyScope->context($request));
    }

    /**
     * POST /company - Create company data
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('company.create') && !$request->user()->hasPermission('admin.company.update')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            $validated = $request->validate([
                'code'        => 'nullable|string|max:50|unique:companies,code',
                'name'        => 'required|string|max:255',
                'legal_name'  => 'nullable|string|max:255',
                'tax_number'  => 'nullable|string|max:100',
                'email'       => 'nullable|email|max:255',
                'phone'       => 'nullable|string|max:50',
                'website'     => 'nullable|string|max:255',
                'address'     => 'nullable|string|max:1000',
                'city'        => 'nullable|string|max:100',
                'state'       => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'country'     => 'nullable|string|max:100',
                'status'      => 'nullable|string|in:active,draft,inactive',
                'timezone'    => 'nullable|string|max:100',
                'currency'    => 'nullable|string|max:8',
                'parent_company_id' => 'nullable|exists:companies,id',
                'logo'        => 'nullable|image|max:2048',
            ]);

            $company = new Company();
            $company->fill($validated);

            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                $storedName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
                $company->logo_path = $file->storeAs('company/logo', $storedName, 'public');
            }

$company->save();

            // Auto-assign creator when they are not Head Office (cannot view all companies)
            if (!$this->companyScope->canViewAll($request->user())) {
                UserCompanyAccess::updateOrCreate(
                    ['user_id' => $request->user()->id, 'company_id' => $company->id],
                    ['scope_role' => 'owner', 'is_default' => true]
                );
            }

            return ApiResponse::success('Company data created successfully', $company->fresh(), 201);

        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create company data', null, 500);
        }
    }

    /**
     * GET /company - Get company data
     */
    public function show(Request $request): JsonResponse
    {
        if (!$this->canViewCompanies($request->user())) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            $companyId = $this->companyScope->selectedCompanyId($request);
            $company = $companyId ? Company::find($companyId) : Company::first();

            if (!$company) {
                return ApiResponse::error('Company not found', null, 404);
            }

            return ApiResponse::success('Company data retrieved', $company);

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch company data', null, 500);
        }
    }

    /**
     * PUT /company/{id} - Update company data (ID hardcoded = 1)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('company.update') && !$request->user()->hasPermission('admin.company.update')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'Invalid company ID']);
            }

            $company = Company::findOrFail($id);

            $validated = $request->validate([
                'code'        => 'sometimes|nullable|string|max:50|unique:companies,code,' . $id,
                'name'        => 'sometimes|string|max:255',
                'legal_name'  => 'sometimes|nullable|string|max:255',
                'tax_number'  => 'sometimes|nullable|string|max:100',
                'email'       => 'sometimes|nullable|email|max:255',
                'phone'       => 'sometimes|nullable|string|max:50',
                'website'     => 'sometimes|nullable|string|max:255',
                'address'     => 'sometimes|nullable|string|max:1000',
                'city'        => 'sometimes|nullable|string|max:100',
                'state'       => 'sometimes|nullable|string|max:100',
                'postal_code' => 'sometimes|nullable|string|max:20',
                'country'     => 'sometimes|nullable|string|max:100',
                'status'      => 'sometimes|nullable|string|in:active,draft,inactive',
                'timezone'    => 'sometimes|nullable|string|max:100',
                'currency'    => 'sometimes|nullable|string|max:8',
                'parent_company_id' => 'sometimes|nullable|exists:companies,id',
                'logo'        => 'sometimes|nullable|image|max:2048',
            ]);

            if ($request->hasFile('logo')) {
                if ($company->logo_path) {
                    Storage::disk('public')->delete($company->logo_path);
                }

                $file = $request->file('logo');
                $storedName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
                $company->logo_path = $file->storeAs('company/logo', $storedName, 'public');
            }

            unset($validated['logo']);

            $company->update($validated);

            return ApiResponse::success('Company data updated successfully', $company->fresh());

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Not found', 'Company not found', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update company data', null, 500);
        }
    }

    /**
     * POST /company/{id}/logo - Upload/update company logo only
     */
    public function uploadLogo(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('admin.company.update')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'Invalid company ID']);
            }

            $company = Company::findOrFail($id);

            $validated = $request->validate([
                'logo' => 'required|image|max:2048',
            ]);

            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }

            $file = $request->file('logo');
            $storedName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
            $company->logo_path = $file->storeAs('company/logo', $storedName, 'public');
            $company->save();

            return ApiResponse::success('Company logo updated successfully', $company->fresh());

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Not found', 'Company not found', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to upload company logo', null, 500);
        }
    }

    /**
     * DELETE /company/{id}/logo - Delete company logo
     */
    public function deleteLogo(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('admin.company.update')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        try {
            if ($id <= 0) {
                throw ValidationException::withMessages(['id' => 'Invalid company ID']);
            }

            $company = Company::findOrFail($id);

            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
                $company->logo_path = null;
                $company->save();
            }

            return ApiResponse::success('Company logo deleted successfully', $company->fresh());

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error('Not found', 'Company not found', 404);
        } catch (ValidationException $e) {
            return ApiResponse::error('Invalid request', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete company logo', null, 500);
        }
    }

    public function deactivate(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('company.deactivate')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $company = Company::findOrFail($id);
        $company->update(['status' => 'inactive']);

        return ApiResponse::success('Company deactivated', $company->fresh());
    }

public function listUsers(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('company.assign_user')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $company = Company::findOrFail($id);

        $accesses = UserCompanyAccess::query()
            ->where('company_id', $company->id)
            ->with(['user:id,name,email', 'company:id,name,code'])
            ->orderBy('is_default', 'desc')
            ->orderBy('id')
            ->get();

        return ApiResponse::success('Company user access retrieved', [
            'company' => $company->only(['id', 'name', 'code']),
            'accesses' => $accesses,
        ]);
    }

    public function assignUser(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('company.assign_user')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'scope_role' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
        ]);

        $company = Company::findOrFail($id);

        if (!empty($validated['is_default'])) {
            UserCompanyAccess::where('user_id', $validated['user_id'])->update(['is_default' => false]);
        }

        $access = UserCompanyAccess::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'company_id' => $company->id,
            ],
            [
                'scope_role' => $validated['scope_role'] ?? 'member',
                'is_default' => $validated['is_default'] ?? false,
            ]
        );

        return ApiResponse::success('Company access assigned', $access->load('company'));
    }

    public function removeUser(Request $request, int $id, int $userId): JsonResponse
    {
        if (!$request->user()->hasPermission('company.assign_user')) {
            return ApiResponse::error('Forbidden', 'Insufficient permissions', 403);
        }

        UserCompanyAccess::where('company_id', $id)->where('user_id', $userId)->delete();

        return ApiResponse::success('Company access removed');
    }

    private function canViewCompanies($user): bool
    {
        return $user->hasPermission('company.view')
            || $user->hasPermission('company.view_all')
            || $user->hasPermission('admin.company.view');
    }

    private function canViewAllCompanies($user): bool
    {
        return $this->companyScope->canViewAll($user);
    }
}
