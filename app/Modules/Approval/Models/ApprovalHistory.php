<?php


namespace App\Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class ApprovalHistory extends Model

{

    protected $table = 'approval_histories';

    protected $fillable = [
        'approval_request_id',
        'approval_step_id',
        'approver_id',
        'action',
        'comment',
        'action_at',
    ];

    protected $casts = [
        'action_at' => 'datetime',
    ];

    // â”€â”€â”€ Relations â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public 
function request(): BelongsTo
    
{

        
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    
}


    public 
function step(): BelongsTo
    
{

        
        return $this->belongsTo(ApprovalStep::class, 'approval_step_id');
    
}


    public 
function approver(): BelongsTo
    
{

        
        return $this->belongsTo(\App\Modules\User\Models\User::class, 'approver_id');
    
}


}


