<?php


namespace App\Modules\Approval\Events;

use App\Modules\Approval\Models\ApprovalRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class ApprovalSubmitted

{

    use Dispatchable, SerializesModels;

    public 
function __construct(
        public ApprovalRequest $approvalRequest
    ) 
{

}


}

