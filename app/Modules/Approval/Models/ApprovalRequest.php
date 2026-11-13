<?php


namespace App\Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;


class ApprovalRequest extends Model

{

    protected $fillable = [
        'approval_flow_id',
        'approvable_type',
        'approvable_id',
        'requester_id',
        'current_step',
        'status',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // â”€â”€â”€ Relations â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * The polymorphic entity being approved (Leave, OvertimeRequest, Reimbursement, etc.)
     */
    public 
function approvable(): MorphTo
    
{

        
        return $this->morphTo();
    
}


    public 
function flow(): BelongsTo
    
{

        
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    
}


    public 
function requester(): BelongsTo
    
{

        
        return $this->belongsTo(\App\Modules\User\Models\User::class, 'requester_id');
    
}


    public 
function histories(): HasMany
    
{

        
        return $this->hasMany(ApprovalHistory::class)->orderBy('action_at');
    
}


    // â”€â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public 
function currentStep(): ?ApprovalStep
    
{

        
        return $this->flow->steps->where('step_order', $this->current_step)->first();
    
}


    public 
function isPending(): bool
    
{

        
        return in_array($this->status, ['pending', 'in_progress']);
    
}


    public 
function isCompleted(): bool
    
{

        
        return in_array($this->status, ['approved', 'rejected', 'cancelled']);
    
}


    // â”€â”€â”€ Scopes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public 
function scopePending($query)
    
{

        
        return $query->whereIn('status', ['pending', 'in_progress']);
    
}


    public 
function scopeForApprover($query, int $userId)
    
{

        // Requests where the current step's approver is this user
        // This is resolved at query time by the ApproverResolver
        
        return $query;
    
}


}


