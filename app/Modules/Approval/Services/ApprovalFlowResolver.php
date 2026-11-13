<?php


namespace App\Modules\Approval\Services;

use App\Modules\Approval\Models\ApprovalFlow;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves which ApprovalFlow should be used for a given request.
 *
 * Resolution priority:
 * 1. Module + Company + Department + Amount range (most specific)
 * 2. Module + Company + Amount range
 * 3. Module + Company + Department
 * 4. Module + Company
 * 5. Module only (global fallback)
 */

class ApprovalFlowResolver

{

    /**
     * Find the best matching active workflow for the given context.
     */
    public 
function resolve(
        string $module,
        ?int $companyId = null,
        ?int $departmentId = null,
        ?float $amount = null,
        ?string $requestType = null
    ): ?ApprovalFlow 
{

        $query = ApprovalFlow::active()
            ->forModule($module)
            ->with('steps')
            ->orderByRaw('
                (CASE WHEN department_id IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN min_amount IS NOT NULL OR max_amount IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN company_id IS NOT NULL THEN 1 ELSE 0 END)
                DESC
            ');

        // Filter by request_type if provided
        if ($requestType) 
{

            $query->where(
function ($q) use ($requestType) 
{

                $q->where('request_type', $requestType)
                  ->orWhereNull('request_type');
            
}
);
        
}


        // Filter by company
        if ($companyId) 
{

            $query->where(
function ($q) use ($companyId) 
{

                $q->where('company_id', $companyId)
                  ->orWhereNull('company_id');
            
}
);
        
}
 else 
{

            $query->whereNull('company_id');
        
}


        // Filter by department
        if ($departmentId) 
{

            $query->where(
function ($q) use ($departmentId) 
{

                $q->where('department_id', $departmentId)
                  ->orWhereNull('department_id');
            
}
);
        
}


        // Filter by amount range
        if ($amount !== null) 
{

            $query->where(
function ($q) use ($amount) 
{

                $q->where(
function ($inner) use ($amount) 
{

                    $inner->where('min_amount', '<=', $amount)
                          ->where('max_amount', '>=', $amount);
                
}
)->orWhere(
function ($inner) use ($amount) 
{

                    $inner->where('min_amount', '<=', $amount)
                          ->whereNull('max_amount');
                
}
)->orWhere(
function ($inner) use ($amount) 
{

                    $inner->whereNull('min_amount')
                          ->where('max_amount', '>=', $amount);
                
}
)->orWhere(
function ($inner) 
{

                    $inner->whereNull('min_amount')
                          ->whereNull('max_amount');
                
}
);
            
}
);
        
}


        
        return $query->first();
    
}


}

