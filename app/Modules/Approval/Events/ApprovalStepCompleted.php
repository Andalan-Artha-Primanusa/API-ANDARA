<?php


namespace App\Modules\Approval\Events;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalStep;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class ApprovalStepCompleted

{

    use Dispatchable, SerializesModels;

    public 
function __construct(
        public ApprovalRequest $approvalRequest,
        public ApprovalStep $step,
        public User $approver
    ) 
{

}


}


