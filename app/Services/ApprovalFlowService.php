<?php

namespace App\Services;

use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowHistory;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApprovalFlowService
{
    /**
     * Apply approval flow to a newly created model.
     * Sets approval_flow_id and current_step = 1.
     *
     * @throws \RuntimeException if no approval flow is configured for the module
     */
    public function applyToModel(string $module, Model $model): Model
    {
        // Derive the model's company so we can prefer a company-specific flow
        // and fall back to the global (company_id IS NULL) flow.
        $companyId = $this->resolveCompanyId($model);

        $query = ApprovalFlow::query()
            ->where('module', $module)
            ->where('is_active', true)
            ->with('steps.role', 'steps.user');

        if ($companyId) {
            // Company-specific flow takes precedence; otherwise global flow.
            $flow = (clone $query)->where('company_id', $companyId)->orderByDesc('id')->first()
                ?? (clone $query)->whereNull('company_id')->first();
        } else {
            $flow = (clone $query)->whereNull('company_id')->first();
        }

        if (!$flow) {
            throw new \RuntimeException("Approval flow for '{$module}' has not been configured.");
        }

        $model->approval_flow_id = $flow->id;
        $model->current_step = 1;
        $model->save();

        // Record initial pending history for first step
        $firstStep = $flow->steps->where('step_order', 1)->first();
        if ($firstStep) {
            ApprovalFlowHistory::create([
                'module' => $module,
                'module_id' => $model->id,
                'approval_flow_id' => $flow->id,
                'step_order' => 1,
                'role_id' => $firstStep->role_id,
                'user_id' => $firstStep->user_id,
                'action' => 'pending',
                'acted_at' => now(),
            ]);
        }

        return $model;
    }

    /**
     * Process an approval action for a model at its current step.
     *
     * @param Model $model Must have approvalFlow, current_step properties and approvalFlow() relationship
     * @param User $approver
     * @param string $action 'approved' or 'rejected'
     * @param string|null $note
     * @return array{model: Model, final: bool, action: string, current_step?: int, next_role?: string}
     *
     * @throws \DomainException for business rule violations
     * @throws \RuntimeException for system configuration issues
     */
    public function processApproval(Model $model, User $approver, string $action, ?string $note = null): array
    {
        $flow = $model->approvalFlow;

        if (!$flow) {
            throw new \RuntimeException('Approval flow not found on this model.');
        }

        $flow->loadMissing('steps.role', 'steps.user');

        $step = $flow->steps->where('step_order', $model->current_step)->first();

        if (!$step) {
            throw new \RuntimeException('Approval step not found.');
        }

        // Check if user has the required role
        if (!$approver->hasRole($step->role->name)) {
            throw new \DomainException('Bukan giliran Anda untuk menyetujui permintaan ini.');
        }

        // Check if specific user is assigned to this step
        if ($step->user_id && $step->user_id !== $approver->id) {
            throw new \DomainException('Approver khusus telah ditugaskan untuk langkah ini.');
        }

        $this->guardCanAccessModelCompany($model, $approver);
        $this->guardNotSelfApproval($model, $approver);

        // Record approval history
        ApprovalFlowHistory::create([
            'module' => $flow->module,
            'module_id' => $model->id,
            'approval_flow_id' => $flow->id,
            'step_order' => $step->step_order,
            'role_id' => $step->role_id,
            'user_id' => $approver->id,
            'action' => $action,
            'note' => $note,
            'acted_at' => now(),
        ]);

        // Rejection â€” finalize immediately
        if ($action === 'rejected') {
            $model->status = 'rejected';
            $model->save();

            return [
                'model' => $model->fresh(),
                'final' => true,
                'action' => 'rejected',
            ];
        }

        // Check if there's a next step
        $nextStep = $flow->steps->where('step_order', $model->current_step + 1)->first();

        if ($nextStep) {
            $model->current_step = $model->current_step + 1;
            $model->save();

            // Record pending for next step
            ApprovalFlowHistory::create([
                'module' => $flow->module,
                'module_id' => $model->id,
                'approval_flow_id' => $flow->id,
                'step_order' => $nextStep->step_order,
                'role_id' => $nextStep->role_id,
                'user_id' => $nextStep->user_id,
                'action' => 'pending',
                'acted_at' => now(),
            ]);

            $model->refresh();

            return [
                'model' => $model,
                'final' => false,
                'action' => 'approved',
                'current_step' => $model->current_step,
                'next_role' => $nextStep->role->name,
            ];
        }

        // No next step â€” final approval
        $model->status = 'approved';
        $model->save();

        return [
            'model' => $model->fresh(),
            'final' => true,
            'action' => 'approved',
        ];
    }

    /**
     * Return a model to its requester for revision.
     * The requester can then call resubmit() to restart the flow at step 1.
     *
     * @param Model $model Must have approvalFlow(), current_step, status
     * @param User $approver
     * @param string|null $note
     * @return Model
     * @throws \DomainException for business rule violations
     */
    public function returnForRevision(Model $model, User $approver, ?string $note = null): Model
    {
        $flow = $model->approvalFlow;
        if (!$flow) {
            throw new \RuntimeException('Approval flow not found on this model.');
        }

        $flow->loadMissing('steps.role', 'steps.user');
        $step = $flow->steps->where('step_order', $model->current_step)->first();
        if (!$step) {
            throw new \RuntimeException('Approval step not found.');
        }

        if (!$approver->hasRole($step->role->name)) {
            throw new \DomainException('Bukan giliran Anda untuk menyetujui permintaan ini.');
        }
        if ($step->user_id && $step->user_id !== $approver->id) {
            throw new \DomainException('Approver khusus telah ditugaskan untuk langkah ini.');
        }
        $this->guardCanAccessModelCompany($model, $approver);
        $this->guardNotSelfApproval($model, $approver, 'Anda tidak dapat mengembalikan permintaan milik Anda sendiri.');

        ApprovalFlowHistory::create([
            'module' => $flow->module,
            'module_id' => $model->id,
            'approval_flow_id' => $flow->id,
            'step_order' => $step->step_order,
            'role_id' => $step->role_id,
            'user_id' => $approver->id,
            'action' => 'returned',
            'note' => $note,
            'acted_at' => now(),
        ]);

        $model->status = 'returned';
        $model->save();

        return $model->fresh();
    }

    /**
     * Allow the requester/owner to resubmit a returned model, restarting at step 1.
     *
     * @param Model $model Must have approvalFlow(), current_step, status
     * @param User $requester
     * @param string|null $note
     * @return Model
     * @throws \DomainException for business rule violations
     */
    public function resubmit(Model $model, User $requester, ?string $note = null): Model
    {
        $flow = $model->approvalFlow;
        if (!$flow) {
            throw new \RuntimeException('Approval flow not found on this model.');
        }

        $ownerUserId = $this->resolveOwnerUserId($model);
        if (!$ownerUserId || (int) $ownerUserId !== (int) $requester->id) {
            throw new \DomainException('Hanya pemilik pengajuan yang dapat mengajukan ulang.');
        }

        if ($model->status !== 'returned') {
            throw new \DomainException('Pengajuan hanya dapat diajukan ulang setelah dikembalikan (returned).');
        }

        $model->current_step = 1;
        $model->status = 'pending';
        $model->save();

        $flow->loadMissing('steps.role', 'steps.user');
        $firstStep = $flow->steps->where('step_order', 1)->first();
        if ($firstStep) {
            ApprovalFlowHistory::create([
                'module' => $flow->module,
                'module_id' => $model->id,
                'approval_flow_id' => $flow->id,
                'step_order' => 1,
                'role_id' => $firstStep->role_id,
                'user_id' => $firstStep->user_id,
                'action' => 'resubmitted',
                'note' => $note,
                'acted_at' => now(),
            ]);
        }

        return $model->fresh();
    }

    /**
     * Get approval history for a specific module item.
     */
    public function getHistory(string $module, int $moduleId)
    {
        return ApprovalFlowHistory::where('module', $module)
            ->where('module_id', $moduleId)
            ->with(['role', 'user.employee', 'flow'])
            ->orderBy('step_order')
            ->orderBy('acted_at')
            ->get();
    }

    /**
     * Check if the given user can act on the current step of the model.
     */
    public function canUserAct(Model $model, User $user): bool
    {
        if (!$model->approval_flow_id || !$model->approvalFlow) {
            return false;
        }

        $flow = $model->approvalFlow;
        $flow->loadMissing('steps.role', 'steps.user');

        $step = $flow->steps->where('step_order', $model->current_step)->first();
        if (!$step) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (!$this->canAccessModelCompany($model, $user)) {
            return false;
        }

        $ownerUserId = $this->resolveOwnerUserId($model);
        if ($ownerUserId && (int) $ownerUserId === (int) $user->id) {
            return false;
        }

        if (!$user->hasRole($step->role->name)) {
            return false;
        }

        if ($step->user_id && $step->user_id !== $user->id) {
            return false;
        }

        return true;
    }

    public function guardNotSelfApproval(Model $model, User $approver, string $message = 'Anda tidak dapat menyetujui permintaan milik Anda sendiri.'): void
    {
        if ($approver->isSuperAdmin()) {
            return;
        }

        $ownerUserId = $this->resolveOwnerUserId($model);
        if ($ownerUserId && (int) $ownerUserId === (int) $approver->id) {
            throw new \DomainException($message);
        }
    }

    public function resolveOwnerUserId(Model $model): ?int
    {
        $this->safeLoadMissing($model, ['employee.user', 'requester.user', 'user']);

        return $model->employee?->user_id
            ?? $model->requester?->user_id
            ?? $model->user_id
            ?? null;
    }

    public function guardCanAccessModelCompany(Model $model, User $user): void
    {
        if (!$this->canAccessModelCompany($model, $user)) {
            throw new \DomainException('Anda tidak dapat memproses pengajuan dari company lain.');
        }
    }

    public function canAccessModelCompany(Model $model, User $user): bool
    {
        $companyId = $this->resolveCompanyId($model);

        return app(\App\Services\CompanyScopeService::class)->canAccessCompany($companyId, $user);
    }

    public function resolveCompanyId(Model $model): ?int
    {
        $this->safeLoadMissing($model, ['employee', 'requester', 'user.employee']);

        return $model->employee?->company_id
            ?? $model->requester?->company_id
            ?? $model->user?->employee?->company_id
            ?? $model->company_id
            ?? null;
    }

    private function safeLoadMissing(Model $model, array $relations): void
    {
        $available = array_values(array_filter($relations, function (string $relation) use ($model) {
            $root = explode('.', $relation)[0];
            return method_exists($model, $root);
        }));

        if (!empty($available)) {
            $model->loadMissing($available);
        }
    }

    /**
     * Add can_act flag to a collection of models for the given user.
     * Also eager-loads approvalFlow.steps.role if not already loaded.
     */
    public function addCanActToListings(Collection $items, User $user): Collection
    {
        return $items->map(function ($item) use ($user) {
            if ($item instanceof Model) {
                $item->can_act = $this->canUserAct($item, $user);
            } else {
                $item->can_act = false;
            }
            return $item;
        });
    }
}

