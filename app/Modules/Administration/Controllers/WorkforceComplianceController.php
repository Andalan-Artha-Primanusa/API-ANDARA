<?php

namespace App\Modules\Administration\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkforceComplianceController extends Controller
{
    /**
     * Get workforce compliance statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $totalDocs = \App\Models\EmployeeDocument::count();
        $expiring = \App\Models\EmployeeDocument::whereNotNull('expires_at')
                        ->where('expires_at', '<=', now()->addDays(30))
                        ->where('expires_at', '>=', now())
                        ->count();
        $expired = \App\Models\EmployeeDocument::whereNotNull('expires_at')
                        ->where('expires_at', '<', now())
                        ->count();
                        
        $score = $totalDocs > 0 ? round((($totalDocs - $expired) / $totalDocs) * 100, 1) . '%' : '100%';

        $data = [
            [
                'label' => 'Compliance Score',
                'value' => $score,
                'color' => '#10b981'
            ],
            [
                'label' => 'Expiring Docs',
                'value' => (string)$expiring,
                'color' => '#f59e0b'
            ],
            [
                'label' => 'Critical Gap',
                'value' => str_pad($expired, 2, '0', STR_PAD_LEFT),
                'color' => '#ef4444'
            ],
            [
                'label' => 'Audit Readiness',
                'value' => '92%', // Mocked for now as it requires complex logic
                'color' => '#6366f1'
            ]
        ];

        return ApiResponse::success('Compliance stats retrieved successfully', $data);
    }

    /**
     * Get compliance documents list
     */
    public function documents(Request $request): JsonResponse
    {
        $documents = \App\Models\EmployeeDocument::with('employee.user')->get();
        
        $data = $documents->map(function ($doc) {
            $risk = 'LOW';
            $dateStr = 'No Expiry';
            
            if ($doc->expires_at) {
                $days = now()->diffInDays($doc->expires_at, false);
                if ($days < 0) {
                    $risk = 'CRITICAL';
                    $dateStr = 'Expired';
                } elseif ($days <= 30) {
                    $risk = 'MEDIUM';
                    $dateStr = $doc->expires_at->format('d M Y');
                } else {
                    $risk = 'LOW';
                    $dateStr = $doc->expires_at->format('d M Y');
                }
            }

            return [
                'id' => $doc->id,
                'name' => $doc->employee?->user?->name ?? 'Unknown',
                'emp_id' => $doc->employee?->employee_code ?? 'N/A',
                'doc' => $doc->document_type ?? $doc->title,
                'date' => $dateStr,
                'risk' => $risk,
            ];
        });

        return ApiResponse::success('Compliance documents retrieved successfully', $data);
    }
}
