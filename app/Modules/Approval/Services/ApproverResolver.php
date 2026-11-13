<?php


namespace App\Modules\Approval\Services;

use App\Modules\Approval\Models\ApprovalStep;
use App\Modules\Employee\Models\Employee;
use App\Modules\User\Models\User;
use App\Modules\Administration\Models\Role;

/**
 * Translates abstract approver types (direct_manager, hr, finance, etc.)
 * into actual user IDs who should receive approval notifications.
 */

class ApproverResolver

{

    /**
     * Resolve the actual approver user ID for a given step and requester.
     *
     * @
        return int|null The user_id of the resolved approver
     */
    public 
function resolve(ApprovalStep $step, User $requester): ?int
    
{

        
        return match ($step->approver_type) 
{

            'user'             => $this->resolveUser($step),
            'role'             => $this->resolveByRole($step),
            'position'         => $this->resolveByPosition($step, $requester),
            'direct_manager'   => $this->resolveDirectManager($requester),
            'department_head'  => $this->resolveDepartmentHead($requester),
            'hr'               => $this->resolveHR(),
            'finance'          => $this->resolveFinance(),
            default            => null,
        
}
;
    
}


    /**
     * Specific user assigned to this step.
     */
    protected 
function resolveUser(ApprovalStep $step): ?int
    
{

        
        return $step->user_id;
    
}


    /**
     * First user with the specified role.
     */
    protected 
function resolveByRole(ApprovalStep $step): ?int
    
{

        if (!$step->role_id) 
{

            
        return null;
        
}


        $user = User::whereHas('roles', 
function ($q) use ($step) 
{

            $q->where('roles.id', $step->role_id);
        
}
)->first();

        
        return $user?->id;
    
}


    /**
     * User whose employee record matches the specified position,
     * ideally within the same department as the requester.
     */
    protected 
function resolveByPosition(ApprovalStep $step, User $requester): ?int
    
{

        if (!$step->position_id) 
{

            
        return null;
        
}


        $employee = Employee::where('position_id', $step->position_id);

        // Prefer someone in the same department
        $requesterEmployee = $requester->employee;
        if ($requesterEmployee && $requesterEmployee->department_id) 
{

            $employee->orderByRaw(
                "CASE WHEN department_id = ? THEN 0 ELSE 1 END",
                [$requesterEmployee->department_id]
            );
        
}


        $found = $employee->first();
        
        return $found?->user_id;
    
}


    /**
     * The requester's direct manager (employee.manager_id â†’ user_id).
     */
    protected 
function resolveDirectManager(User $requester): ?int
    
{

        $employee = $requester->employee;
        if (!$employee || !$employee->manager_id) 
{

            
        return null;
        
}


        // manager_id points to another employee record
        $manager = Employee::find($employee->manager_id);
        
        return $manager?->user_id;
    
}


    /**
     * The head of the requester's department.
     * Uses the department.head_id field if available, 
     * otherwise falls back to the first manager-level employee in the department.
     */
    protected 
function resolveDepartmentHead(User $requester): ?int
    
{

        $employee = $requester->employee;
        if (!$employee || !$employee->department_id) 
{

            
        return null;
        
}


        $department = $employee->department;
        if ($department && $department->head_id) 
{

            $head = Employee::find($department->head_id);
            
        return $head?->user_id;
        
}


        
        return null;
    
}


    /**
     * First user with the hr_admin role.
     */
    protected 
function resolveHR(): ?int
    
{

        $user = User::whereHas('roles', 
function ($q) 
{

            $q->where('slug', 'hr_admin');
        
}
)->first();

        
        return $user?->id;
    
}


    /**
     * First user with the finance role.
     */
    protected 
function resolveFinance(): ?int
    
{

        $user = User::whereHas('roles', 
function ($q) 
{

            $q->where('slug', 'finance');
        
}
)->first();

        
        return $user?->id;
    
}


}




