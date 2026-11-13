<?php


namespace App\Modules\Approval\Services;

use App\Modules\Approval\Models\ApprovalFlow;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalHistory;
use App\Modules\Approval\Models\ApprovalStep;
use App\Modules\Approval\Events\ApprovalSubmitted;
use App\Modules\Approval\Events\ApprovalStepCompleted;
use App\Modules\Approval\Events\ApprovalCompleted;
use App\Modules\Approval\Events\ApprovalRejected;
use App\Modules\Approval\Events\ApprovalReturned;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Core Approval Engine.
 *
 * Orchestrates the full lifecycle of an approval request:
 * submit Ã¢â€ â€™ approve/reject/
        return Ã¢â€ â€™ next step Ã¢â€ â€™ completed
 *
 * Completely decoupled from business modules via Events.
 */

class ApprovalService

{

    public 
function __construct(
        protected ApprovalFlowResolver $flowResolver,
        protected ApproverResolver $approverResolver,
    ) 
{

}


    // Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Submit Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

    /**
     * Submit a model for approval.
     *
     * @param Model  $approvable  The entity being submitted (Leave, Overtime, etc.)
     * @param User   $requester   The user who initiated the request
     * @param string $module      The module name (leave, overtime, reimbursement, etc.)
     * @param float|null $amount  Optional amount for amount-based workflow resolution
     */
    public 
function submit(
        Model $approvable,
        User $requester,
        string $module,
        ?float $amount = null,
        ?string $requestType = null,
    ): ApprovalRequest 
{

        
        return DB::transaction(
function () use ($approvable, $requester, $module, $amount, $requestType) 
{

            $employee = $requester->employee;
            $companyId = $employee?->company_id;
            $departmentId = $employee?->department_id;

            // 1. Resolve which workflow to use
            $flow = $this->flowResolver->resolve(
                module: $module,
                companyId: $companyId,
                departmentId: $departmentId,
                amount: $amount,
                requestType: $requestType,
            );

            if (!$flow || $flow->steps->isEmpty()) 
{

                // No workflow found Ã¢â‚¬â€ auto-approve
                
        return $this->autoApprove($approvable, $requester);
            
}


            // 2. Create approval request
            $approvalRequest = ApprovalRequest::create([
                'approval_flow_id' => $flow->id,
                'approvable_type'  => get_class($approvable),
                'approvable_id'    => $approvable->getKey(),
                'requester_id'     => $requester->id,
                'current_step'     => 1,
                'status'           => 'in_progress',
                'submitted_at'     => now(),
            ]);

            // 3. Create submission history
            $firstStep = $flow->steps->first();
            ApprovalHistory::create([
                'approval_request_id' => $approvalRequest->id,
                'approval_step_id'    => $firstStep->id,
                'approver_id'         => $requester->id,
                'action'              => 'submitted',
                'comment'             => null,
                'action_at'           => now(),
            ]);

            // 4. Fire event
            event(new ApprovalSubmitted($approvalRequest));

            
        return $approvalRequest;
        
}
);
    
}


    // Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Approve Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

    /**
     * Approve the current step of an approval request.
     */
    public 
function approve(ApprovalRequest $approvalRequest, User $approver, ?string $comment = null): ApprovalRequest
    
{

        
        return DB::transaction(
function () use ($approvalRequest, $approver, $comment) 
{

            $currentStep = $approvalRequest->currentStep();

            if (!$currentStep) 
{

                throw new \RuntimeException('No active approval step found.');
            
}


            $this->guardCanAct($approvalRequest, $currentStep, $approver);

            // Record the approval action
            ApprovalHistory::create([
                'approval_request_id' => $approvalRequest->id,
                'approval_step_id'    => $currentStep->id,
                'approver_id'         => $approver->id,
                'action'              => 'approved',
                'comment'             => $comment,
                'action_at'           => now(),
            ]);

            event(new ApprovalStepCompleted($approvalRequest, $currentStep, $approver));

            // Check if there are more steps
            $flow = $approvalRequest->flow;
            $nextStep = $flow->steps
                ->where('step_order', '>', $currentStep->step_order)
                ->first();

            if ($nextStep) 
{

                // Move to next step
                $approvalRequest->update([
                    'current_step' => $nextStep->step_order,
                ]);
            
}
 else 
{

                // All steps completed Ã¢â‚¬â€ mark as approved
                $approvalRequest->update([
                    'status'       => 'approved',
                    'completed_at' => now(),
                ]);

                event(new ApprovalCompleted($approvalRequest));
            
}


            
        return $approvalRequest->fresh();
        
}
);
    
}


    // Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Reject Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

    /**
     * Reject the approval request at the current step.
     */
    public 
function reject(ApprovalRequest $approvalRequest, User $approver, ?string $comment = null): ApprovalRequest
    
{

        
        return DB::transaction(
function () use ($approvalRequest, $approver, $comment) 
{

            $currentStep = $approvalRequest->currentStep();

            $this->guardCanAct($approvalRequest, $currentStep, $approver);

            ApprovalHistory::create([
                'approval_request_id' => $approvalRequest->id,
                'approval_step_id'    => $currentStep->id,
                'approver_id'         => $approver->id,
                'action'              => 'rejected',
                'comment'             => $comment,
                'action_at'           => now(),
            ]);

            $approvalRequest->update([
                'status'       => 'rejected',
                'completed_at' => now(),
            ]);

            event(new ApprovalRejected($approvalRequest));

            
        return $approvalRequest->fresh();
        
}
);
    
}


    // Return for revision.

    /**
     * Return the request to the requester for revision.
     */
    public 
function returnForRevision(ApprovalRequest $approvalRequest, User $approver, ?string $comment = null): ApprovalRequest
    
{

        
        return DB::transaction(
function () use ($approvalRequest, $approver, $comment) 
{

            $currentStep = $approvalRequest->currentStep();

            $this->guardCanAct($approvalRequest, $currentStep, $approver);

            ApprovalHistory::create([
                'approval_request_id' => $approvalRequest->id,
                'approval_step_id'    => $currentStep->id,
                'approver_id'         => $approver->id,
                'action'              => 'returned',
                'comment'             => $comment,
                'action_at'           => now(),
            ]);

            $approvalRequest->update([
                'status' => 'returned',
            ]);

            event(new ApprovalReturned($approvalRequest));

            
        return $approvalRequest->fresh();
        
}
);
    
}


    // Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Cancel Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

    /**
     * Cancel the approval request (by the requester).
     */
    public 
function cancel(ApprovalRequest $approvalRequest, User $requester, ?string $comment = null): ApprovalRequest
    
{

        
        return DB::transaction(
function () use ($approvalRequest, $requester, $comment) 
{

            $currentStep = $approvalRequest->currentStep();

            if ($currentStep) 
{

                ApprovalHistory::create([
                    'approval_request_id' => $approvalRequest->id,
                    'approval_step_id'    => $currentStep->id,
                    'approver_id'         => $requester->id,
                    'action'              => 'cancelled',
                    'comment'             => $comment,
                    'action_at'           => now(),
                ]);
            
}


            $approvalRequest->update([
                'status'       => 'cancelled',
                'completed_at' => now(),
            ]);

            
        return $approvalRequest->fresh();
        
}
);
    
}


    // Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Queries Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

    /**
     * Get pending approvals assigned to a specific user.
     */
    public 
function getPendingApprovals(User $user): \Illuminate\Database\Eloquent\Collection
    
{

        // Get all in-progress requests, then filter by approver resolution
        $pending = ApprovalRequest::where('status', 'in_progress')
            ->with(['flow.steps', 'requester', 'histories'])
            ->get();

        
        return $pending->filter(
function (ApprovalRequest $request) use ($user) 
{

            $currentStep = $request->currentStep();
            if (!$currentStep) 
        return false;

            $resolverId = $this->approverResolver->resolve($currentStep, $request->requester);
            
        return $resolverId === $user->id;
        
}
)->values();
    
}


    /**
     * Get approval history for a specific approvable entity.
     */
    public 
function getApprovalHistory(Model $approvable): ?ApprovalRequest
    
{

        
        return ApprovalRequest::where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->getKey())
            ->with(['flow.steps', 'histories.approver', 'histories.step'])
            ->latest()
            ->first();
    
}


    // Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ Internal Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

    /**
     * Auto-approve when no workflow is configured.
     */
    protected 
function autoApprove(Model $approvable, User $requester): ApprovalRequest
    
{

        $approvalRequest = new ApprovalRequest([
            'approval_flow_id' => null,
            'approvable_type'  => get_class($approvable),
            'approvable_id'    => $approvable->getKey(),
            'requester_id'     => $requester->id,
            'current_step'     => 0,
            'status'           => 'approved',
            'submitted_at'     => now(),
            'completed_at'     => now(),
        ]);

        // Don't save Ã¢â‚¬â€ this is a virtual auto-approval
        // The caller should handle the business logic directly
        Log::info("Auto-approved 
{
$approvable->getTable()
}
#
{
$approvable->getKey()
}
 Ã¢â‚¬â€ no workflow configured.");

        event(new ApprovalCompleted($approvalRequest));

        
        return $approvalRequest;
    
}


    protected function guardCanAct(ApprovalRequest $approvalRequest, ApprovalStep $currentStep, User $approver): void
    {
        if ($approvalRequest->requester_id === $approver->id) {
            throw new \DomainException('Anda tidak dapat menyetujui permintaan milik Anda sendiri.');
        }
        $resolvedId = $this->approverResolver->resolve($currentStep, $approvalRequest->requester);
        if ($resolvedId !== null && (int) $resolvedId !== (int) $approver->id) {
            throw new \DomainException('Bukan giliran Anda untuk menyetujui permintaan ini.');
        }
    }

}


