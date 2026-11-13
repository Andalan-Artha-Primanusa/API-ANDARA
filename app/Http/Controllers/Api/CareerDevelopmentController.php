<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CareerDevelopmentController extends Controller
{
    private function canUseCareer(Request $request, string $permission): bool
    {
        $user = $request->user();

        return $user && (
            $user->isAdmin()
            || $user->isHR()
            || $user->isManager()
            || $user->isSuperAdmin()
            || $user->hasPermission($permission)
        );
    }

    public function idpIndex(Request $request): JsonResponse
    {
        if (!$this->canUseCareer($request, 'career.idp.view')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        $data = DB::table('individual_development_plans as i')
            ->leftJoin('employees as e', 'e.id', '=', 'i.employee_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->leftJoin('user_profiles as p', 'p.user_id', '=', 'u.id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->leftJoin('positions as pos', 'pos.id', '=', 'e.position_id')
            ->select(
                'i.*', 
                'u.name as employee_name', 
                'u.email as employee_email',
                'p.avatar as employee_avatar',
                'd.name as department_name',
                'pos.name as position_name'
            )
            ->orderByDesc('i.created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return ApiResponse::success('IDP list retrieved successfully', $data);
    }

    public function idpStore(Request $request): JsonResponse
    {
        if (!$this->canUseCareer($request, 'career.idp.create')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'review_cycle_id' => 'nullable|integer|exists:review_cycles,id',
            'goal_title' => 'required|string|max:255',
            'goal_description' => 'nullable|string|max:5000',
            'status' => 'sometimes|string|in:draft,in_progress,completed,cancelled',
            'target_date' => 'nullable|date',
            'mentor_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $id = DB::table('individual_development_plans')->insertGetId([
            ...$validated,
            'status' => $validated['status'] ?? 'draft',
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('IDP created successfully', DB::table('individual_development_plans')->where('id', $id)->first(), 201);
    }

    public function idpUpdate(Request $request, int $id): JsonResponse
    {
        if (!$this->canUseCareer($request, 'career.idp.update')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        $validated = $request->validate([
            'goal_title' => 'sometimes|string|max:255',
            'goal_description' => 'sometimes|nullable|string|max:5000',
            'status' => 'sometimes|string|in:draft,in_progress,completed,cancelled',
            'target_date' => 'sometimes|nullable|date',
            'mentor_user_id' => 'sometimes|nullable|integer|exists:users,id',
        ]);

        if (!DB::table('individual_development_plans')->where('id', $id)->exists()) {
            return ApiResponse::error('IDP not found', null, 404);
        }

        DB::table('individual_development_plans')->where('id', $id)->update([
            ...$validated,
            'updated_at' => now(),
        ]);

        return ApiResponse::success('IDP updated successfully', DB::table('individual_development_plans')->where('id', $id)->first());
    }

    public function successionMatrix(Request $request): JsonResponse
    {
        if (!$this->canUseCareer($request, 'career.succession.view')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        $data = DB::table('succession_candidates as s')
            ->leftJoin('employees as e', 'e.id', '=', 's.employee_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->leftJoin('user_profiles as p', 'p.user_id', '=', 'u.id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->leftJoin('positions as pos', 'pos.id', '=', 'e.position_id')
            ->select(
                's.*', 
                'u.name as employee_name', 
                'u.email as employee_email',
                'p.avatar as employee_avatar',
                'd.name as department_name',
                'pos.name as position_name'
            )
            ->orderByDesc('s.talent_score')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return ApiResponse::success('Succession matrix retrieved successfully', $data);
    }

    public function successionStore(Request $request): JsonResponse
    {
        if (!$this->canUseCareer($request, 'career.succession.manage')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        $validated = $request->validate([
            'position_key' => 'required|string|max:255',
            'employee_id' => 'required|integer|exists:employees,id',
            'readiness' => 'sometimes|string|in:ready_now,ready_1_2_years,ready_3_5_years,not_ready',
            'talent_score' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:5000',
        ]);

        DB::table('succession_candidates')->updateOrInsert(
            [
                'position_key' => $validated['position_key'],
                'employee_id' => $validated['employee_id'],
            ],
            [
                'readiness' => $validated['readiness'] ?? 'ready_1_2_years',
                'talent_score' => $validated['talent_score'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'assessed_by' => $request->user()->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return ApiResponse::success('Succession candidate saved successfully');
    }
}
