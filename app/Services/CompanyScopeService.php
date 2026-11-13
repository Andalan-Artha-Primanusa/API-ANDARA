<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CompanyScopeService
{
    public const HEADER = 'X-Company-Id';

    public function canViewAll($user): bool
    {
        return $user?->isSuperAdmin()
            || $user?->hasPermission('company.view_all')
            || $user?->hasPermission('dashboard.view_all_company');
    }

    public function availableCompanyIds($user): Collection
    {
        if (!$user) {
            return collect();
        }

        if ($this->canViewAll($user)) {
            return Company::query()->pluck('id');
        }

        $ids = $user->companies()->pluck('companies.id');
        $employeeCompanyId = $user->employee?->company_id;

        if ($employeeCompanyId) {
            $ids->push($employeeCompanyId);
        }

        return $ids->unique()->values();
    }

    public function selectedCompanyId(Request $request): ?int
    {
        $user = $request->user();
        $raw = $request->header(self::HEADER, $request->query('company_id'));

        if ($raw === null || $raw === '' || strtolower((string) $raw) === 'all') {
            return $this->canViewAll($user) ? null : $this->defaultCompanyId($user);
        }

        $companyId = (int) $raw;
        if ($companyId <= 0) {
            return $this->canViewAll($user) ? null : $this->defaultCompanyId($user);
        }

        if ($this->canViewAll($user)) {
            return $companyId;
        }

        return $this->availableCompanyIds($user)->contains($companyId)
            ? $companyId
            : $this->defaultCompanyId($user);
    }

    public function defaultCompanyId($user): ?int
    {
        if (!$user) {
            return null;
        }

        $defaultAccess = $user->companyAccesses()
            ->where('is_default', true)
            ->first();

        return $defaultAccess?->company_id
            ?? $user->employee?->company_id
            ?? $user->companies()->value('companies.id');
    }

    public function context(Request $request): array
    {
        $user = $request->user();
        $canViewAll = $this->canViewAll($user);
        $selectedCompanyId = $this->selectedCompanyId($request);
        $availableIds = $this->availableCompanyIds($user);

        $companies = Company::query()
            ->when(!$canViewAll, fn (Builder $query) => $query->whereIn('id', $availableIds))
            ->orderBy('name')
            ->get();

        return [
            'mode' => $selectedCompanyId ? 'company' : 'all',
            'can_view_all' => $canViewAll,
            'selected_company_id' => $selectedCompanyId,
            'default_company_id' => $this->defaultCompanyId($user),
            'companies' => $companies,
        ];
    }

    public function applyEmployeeScope(Builder $query, Request $request, string $column = 'company_id'): Builder
    {
        $selectedCompanyId = $this->selectedCompanyId($request);

        if ($selectedCompanyId) {
            return $query->where($column, $selectedCompanyId);
        }

        if (!$this->canViewAll($request->user())) {
            return $query->whereIn($column, $this->availableCompanyIds($request->user()));
        }

        return $query;
    }

    public function applyThroughEmployee(Builder $query, Request $request, string $relation = 'employee'): Builder
    {
        $selectedCompanyId = $this->selectedCompanyId($request);

        if ($selectedCompanyId) {
            return $query->whereHas($relation, fn (Builder $employeeQuery) => $employeeQuery->where('company_id', $selectedCompanyId));
        }

        if (!$this->canViewAll($request->user())) {
            $allowedIds = $this->availableCompanyIds($request->user());
            return $query->whereHas($relation, fn (Builder $employeeQuery) => $employeeQuery->whereIn('company_id', $allowedIds));
        }

        return $query;
    }

    public function canAccessCompany(?int $companyId, $user): bool
    {
        if (!$companyId) {
            return $this->canViewAll($user);
        }

        if ($this->canViewAll($user)) {
            return true;
        }

        return $this->availableCompanyIds($user)->contains($companyId);
    }

    public function canAccessEmployeeCompany(int $employeeId, $user): bool
    {
        $companyId = \App\Modules\Employee\Models\Employee::whereKey($employeeId)->value('company_id');

        return $this->canAccessCompany($companyId, $user);
    }
}
