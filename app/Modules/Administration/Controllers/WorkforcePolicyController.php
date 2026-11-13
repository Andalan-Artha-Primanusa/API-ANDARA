<?php

namespace App\Modules\Administration\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ShiftSwapRequest;
use App\Services\ApprovalFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkforcePolicyController extends Controller
{
    private function holidayCalendarPayload(object $calendar): array
    {
        $dates = DB::table('holiday_dates')
            ->where('holiday_calendar_id', $calendar->id)
            ->orderBy('holiday_date')
            ->get();

        $firstDate = $dates->first();

        return [
            'id' => $calendar->id,
            'name' => $calendar->name,
            'year' => (int) $calendar->year,
            'active' => (bool) $calendar->active,
            'date' => $firstDate?->holiday_date,
            'description' => $firstDate?->name,
            'type' => ($firstDate && (bool) $firstDate->is_national) ? 'national' : 'company',
            'is_recurring' => false,
            'applicable_locations' => ['All'],
            'dates' => $dates->map(function ($date) {
                return [
                    'holiday_date' => $date->holiday_date,
                    'name' => $date->name,
                    'is_national' => (bool) $date->is_national,
                ];
            })->values()->all(),
        ];
    }

    private function overtimeRulePayload(object $rule): array
    {
        $minMinutes = (int) ($rule->min_minutes ?? 0);

        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'department' => $rule->department,
            'location_id' => $rule->location_id,
            'min_minutes' => $minMinutes,
            'multiplier' => (float) $rule->multiplier,
            'requires_approval' => (bool) $rule->requires_approval,
            'active' => (bool) $rule->active,
            'status' => (bool) $rule->active ? 'active' : 'inactive',
            'max_hours_per_day' => $minMinutes > 0 ? round($minMinutes / 60, 2) : 0,
            'max_hours_per_week' => null,
            'eligibility' => $rule->department ?: 'All Staff',
            'description' => $rule->description ?? null,
        ];
    }

    public function holidayCalendarIndex(Request $request): JsonResponse
    {
        $paginator = DB::table('holiday_calendars')->orderByDesc('id')->paginate($request->integer('per_page', 10))->withQueryString();
        $paginator->getCollection()->transform(function ($calendar) {
            return $this->holidayCalendarPayload($calendar);
        });

        return ApiResponse::success('Holiday calendars retrieved successfully', $paginator);
    }

    public function holidayCalendarStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'year' => 'nullable|integer|min:2000|max:2100',
            'date' => 'nullable|date',
            'active' => 'sometimes|boolean',
            'type' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'is_recurring' => 'sometimes|boolean',
            'applicable_locations' => 'sometimes|array',
            'dates' => 'sometimes|array',
            'dates.*.holiday_date' => 'required_with:dates|date',
            'dates.*.name' => 'required_with:dates|string|max:255',
            'dates.*.is_national' => 'sometimes|boolean',
        ]);

        $dateRows = $validated['dates'] ?? [];
        if (empty($dateRows) && !empty($validated['date'])) {
            $dateRows = [[
                'holiday_date' => $validated['date'],
                'name' => $validated['description'] ?? $validated['name'],
                'is_national' => !isset($validated['type']) || strtolower((string) $validated['type']) !== 'company',
            ]];
        }

        $baseDate = $dateRows[0]['holiday_date'] ?? $validated['date'] ?? null;
        $year = $validated['year'] ?? ($baseDate ? (int) date('Y', strtotime((string) $baseDate)) : (int) date('Y'));

        $calendarId = DB::table('holiday_calendars')->insertGetId([
            'name' => $validated['name'],
            'year' => $year,
            'active' => $validated['active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($dateRows as $date) {
            DB::table('holiday_dates')->insert([
                'holiday_calendar_id' => $calendarId,
                'holiday_date' => $date['holiday_date'],
                'name' => $date['name'] ?? $validated['description'] ?? $validated['name'],
                'is_national' => $date['is_national'] ?? true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $calendar = DB::table('holiday_calendars')->where('id', $calendarId)->first();

        return ApiResponse::success('Holiday calendar created successfully', $this->holidayCalendarPayload($calendar), 201);
    }

    public function holidayCalendarShow(int $id): JsonResponse
    {
        $calendar = DB::table('holiday_calendars')->where('id', $id)->first();

        if (!$calendar) {
            return ApiResponse::error('Holiday calendar not found', null, 404);
        }

        return ApiResponse::success('Holiday calendar retrieved successfully', $this->holidayCalendarPayload($calendar));
    }

    public function holidayCalendarUpdate(Request $request, int $id): JsonResponse
    {
        $calendar = DB::table('holiday_calendars')->where('id', $id)->first();

        if (!$calendar) {
            return ApiResponse::error('Holiday calendar not found', null, 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'year' => 'nullable|integer|min:2000|max:2100',
            'date' => 'nullable|date',
            'active' => 'sometimes|boolean',
            'type' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'is_recurring' => 'sometimes|boolean',
            'applicable_locations' => 'sometimes|array',
            'dates' => 'sometimes|array',
            'dates.*.holiday_date' => 'required_with:dates|date',
            'dates.*.name' => 'required_with:dates|string|max:255',
            'dates.*.is_national' => 'sometimes|boolean',
        ]);

        $dateRows = $validated['dates'] ?? [];
        if (empty($dateRows) && !empty($validated['date'])) {
            $dateRows = [[
                'holiday_date' => $validated['date'],
                'name' => $validated['description'] ?? ($validated['name'] ?? $calendar->name),
                'is_national' => !isset($validated['type']) || strtolower((string) $validated['type']) !== 'company',
            ]];
        }

        $baseDate = $dateRows[0]['holiday_date'] ?? $validated['date'] ?? null;
        $year = $validated['year'] ?? ($baseDate ? (int) date('Y', strtotime((string) $baseDate)) : (int) $calendar->year);

        DB::table('holiday_calendars')->where('id', $id)->update([
            'name' => $validated['name'] ?? $calendar->name,
            'year' => $year,
            'active' => $validated['active'] ?? $calendar->active,
            'updated_at' => now(),
        ]);

        if (!empty($dateRows)) {
            DB::table('holiday_dates')->where('holiday_calendar_id', $id)->delete();

            foreach ($dateRows as $date) {
                DB::table('holiday_dates')->insert([
                    'holiday_calendar_id' => $id,
                    'holiday_date' => $date['holiday_date'],
                    'name' => $date['name'] ?? $validated['description'] ?? ($validated['name'] ?? $calendar->name),
                    'is_national' => $date['is_national'] ?? true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $updatedCalendar = DB::table('holiday_calendars')->where('id', $id)->first();

        return ApiResponse::success('Holiday calendar updated successfully', $this->holidayCalendarPayload($updatedCalendar));
    }

    public function holidayCalendarDestroy(int $id): JsonResponse
    {
        $calendar = DB::table('holiday_calendars')->where('id', $id)->first();

        if (!$calendar) {
            return ApiResponse::error('Holiday calendar not found', null, 404);
        }

        DB::table('holiday_dates')->where('holiday_calendar_id', $id)->delete();
        DB::table('holiday_calendars')->where('id', $id)->delete();

        return ApiResponse::success('Holiday calendar deleted successfully');
    }

    public function advancedLeavePolicyUpdate(Request $request, int $policyId): JsonResponse
    {
        $validated = $request->validate([
            'carry_over_enabled' => 'sometimes|boolean',
            'encashment_enabled' => 'sometimes|boolean',
            'blackout_ranges' => 'sometimes|array',
            'holiday_calendar_id' => 'sometimes|nullable|integer|exists:holiday_calendars,id',
        ]);

        if (!DB::table('leave_policies')->where('id', $policyId)->exists()) {
            return ApiResponse::error('Leave policy not found', null, 404);
        }

        $updateData = ['updated_at' => now()];
        if (array_key_exists('carry_over_enabled', $validated)) {
            $updateData['carry_over_enabled'] = $validated['carry_over_enabled'];
        }
        if (array_key_exists('encashment_enabled', $validated)) {
            $updateData['encashment_enabled'] = $validated['encashment_enabled'];
        }
        if (array_key_exists('blackout_ranges', $validated)) {
            $updateData['blackout_ranges'] = json_encode($validated['blackout_ranges']);
        }
        if (array_key_exists('holiday_calendar_id', $validated)) {
            $updateData['holiday_calendar_id'] = $validated['holiday_calendar_id'];
        }

        DB::table('leave_policies')->where('id', $policyId)->update($updateData);

        return ApiResponse::success('Advanced leave policy updated successfully', DB::table('leave_policies')->where('id', $policyId)->first());
    }

    public function shiftSwapIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = ShiftSwapRequest::with([
            'requester:id,user_id,employee_code,department_id,position_id',
            'requester.user:id,name,email',
            'requester.user.profile:id,user_id',
            'requester.department:id,name',
            'requester.position:id,name',
            'target:id,user_id,employee_code,department_id,position_id',
            'target.user:id,name,email',
            'target.user.profile:id,user_id',
            'target.department:id,name',
            'target.position:id,name',
            'approvalFlow.steps.role',
            'approvalFlow.steps.user'
        ])->orderByDesc('id')->paginate($request->integer('per_page', 10))->withQueryString();
        $service = app(ApprovalFlowService::class);
        $data->getCollection()->transform(function ($item) use ($service, $user) {
            $item->can_act = $service->canUserAct($item, $user);
            return $item;
        });
        return ApiResponse::success('Shift swap requests retrieved successfully', $data);
    }

    public function shiftSwapStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'requester_employee_id' => 'required|integer|exists:employees,id',
            'target_employee_id' => 'required|integer|exists:employees,id|different:requester_employee_id',
            'swap_date' => 'required|date',
            'reason' => 'nullable|string|max:5000',
        ]);

        $id = DB::table('shift_swap_requests')->insertGetId([
            ...$validated,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $swap = ShiftSwapRequest::find($id);
            if ($swap) {
                $approvalService = app(ApprovalFlowService::class);
                $approvalService->applyToModel('shift_swap', $swap);
            }
        } catch (\RuntimeException $e) {
            // No approval flow configured — fall back to direct pending status
        }

        return ApiResponse::success('Shift swap request created successfully', ShiftSwapRequest::with([
            'requester:id,user_id,employee_code,department_id,position_id',
            'requester.user:id,name,email',
            'requester.user.profile:id,user_id',
            'requester.department:id,name',
            'requester.position:id,name',
            'target:id,user_id,employee_code,department_id,position_id',
            'target.user:id,name,email',
            'target.user.profile:id,user_id',
            'target.department:id,name',
            'target.position:id,name',
        ])->find($id), 201);
    }

    public function shiftSwapApprove(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:approved,rejected,cancelled',
        ]);

        if (!DB::table('shift_swap_requests')->where('id', $id)->exists()) {
            return ApiResponse::error('Shift swap request not found', null, 404);
        }

        DB::table('shift_swap_requests')->where('id', $id)->update([
            'status' => $validated['status'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Shift swap request updated successfully', ShiftSwapRequest::with([
            'requester:id,user_id,employee_code,department_id,position_id',
            'requester.user:id,name,email',
            'requester.user.profile:id,user_id',
            'requester.department:id,name',
            'requester.position:id,name',
            'target:id,user_id,employee_code,department_id,position_id',
            'target.user:id,name,email',
            'target.user.profile:id,user_id',
            'target.department:id,name',
            'target.position:id,name',
        ])->find($id));
    }

    public function shiftSwapApproveAction(Request $request, int $id): JsonResponse
    {
        $swap = ShiftSwapRequest::with('approvalFlow.steps.role', 'approvalFlow.steps.user')->findOrFail($id);

        if (!$swap->approval_flow_id) {
            return ApiResponse::error('No approval flow configured for this request', null, 400);
        }

        try {
            $approvalService = app(ApprovalFlowService::class);
            $result = $approvalService->processApproval($swap, $request->user(), 'approved', $request->note);
            return ApiResponse::success('Shift swap approved', $result['model']->fresh([
                'requester:id,user_id,employee_code,department_id,position_id',
                'requester.user:id,name,email',
                'requester.user.profile:id,user_id',
                'requester.department:id,name',
                'requester.position:id,name',
                'target:id,user_id,employee_code,department_id,position_id',
                'target.user:id,name,email',
                'target.user.profile:id,user_id',
                'target.department:id,name',
                'target.position:id,name',
            ]));
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function shiftSwapRejectAction(Request $request, int $id): JsonResponse
    {
        $swap = ShiftSwapRequest::with('approvalFlow.steps.role', 'approvalFlow.steps.user')->findOrFail($id);

        if (!$swap->approval_flow_id) {
            return ApiResponse::error('No approval flow configured for this request', null, 400);
        }

        try {
            $approvalService = app(ApprovalFlowService::class);
            $result = $approvalService->processApproval($swap, $request->user(), 'rejected', $request->note ?? $request->input('note'));
            return ApiResponse::success('Shift swap rejected', $result['model']->fresh([
                'requester:id,user_id,employee_code,department_id,position_id',
                'requester.user:id,name,email',
                'requester.user.profile:id,user_id',
                'requester.department:id,name',
                'requester.position:id,name',
                'target:id,user_id,employee_code,department_id,position_id',
                'target.user:id,name,email',
                'target.user.profile:id,user_id',
                'target.department:id,name',
                'target.position:id,name',
            ]));
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function overtimeRuleIndex(Request $request): JsonResponse
    {
        $paginator = DB::table('overtime_rules')->orderByDesc('id')->paginate($request->integer('per_page', 10))->withQueryString();
        $paginator->getCollection()->transform(function ($rule) {
            return $this->overtimeRulePayload($rule);
        });

        return ApiResponse::success('Overtime rules retrieved successfully', $paginator);
    }

    public function overtimeRuleStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'location_id' => 'nullable|integer|exists:locations,id',
            'min_minutes' => 'sometimes|integer|min:0|max:1000',
            'multiplier' => 'sometimes|numeric|min:0',
            'requires_approval' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
            'max_hours_per_day' => 'nullable|numeric|min:0',
            'max_hours_per_week' => 'nullable|numeric|min:0',
            'eligibility' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:5000',
        ]);

        $department = $validated['department'] ?? null;
        if (!$department && !empty($validated['eligibility']) && strtolower((string) $validated['eligibility']) !== 'all staff') {
            $department = $validated['eligibility'];
        }

        $active = $validated['active'] ?? true;
        if (!empty($validated['status'])) {
            $active = strtolower((string) $validated['status']) !== 'inactive';
        }

        $minMinutes = $validated['min_minutes'] ?? null;
        if ($minMinutes === null && isset($validated['max_hours_per_day'])) {
            $minMinutes = (int) round(((float) $validated['max_hours_per_day']) * 60);
        }

        $id = DB::table('overtime_rules')->insertGetId([
            'name' => $validated['name'],
            'department' => $department,
            'location_id' => $validated['location_id'] ?? null,
            'min_minutes' => $minMinutes ?? 0,
            'multiplier' => $validated['multiplier'] ?? 1,
            'requires_approval' => $validated['requires_approval'] ?? true,
            'active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Overtime rule created successfully', $this->overtimeRulePayload(DB::table('overtime_rules')->where('id', $id)->first()), 201);
    }

    public function overtimeRuleShow(int $id): JsonResponse
    {
        $rule = DB::table('overtime_rules')->where('id', $id)->first();

        if (!$rule) {
            return ApiResponse::error('Overtime rule not found', null, 404);
        }

        return ApiResponse::success('Overtime rule retrieved successfully', $this->overtimeRulePayload($rule));
    }

    public function overtimeRuleUpdate(Request $request, int $id): JsonResponse
    {
        $rule = DB::table('overtime_rules')->where('id', $id)->first();

        if (!$rule) {
            return ApiResponse::error('Overtime rule not found', null, 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'department' => 'nullable|string|max:255',
            'location_id' => 'nullable|integer|exists:locations,id',
            'min_minutes' => 'nullable|integer|min:0|max:1000',
            'multiplier' => 'nullable|numeric|min:0',
            'requires_approval' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'max_hours_per_day' => 'nullable|numeric|min:0',
            'max_hours_per_week' => 'nullable|numeric|min:0',
            'eligibility' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:5000',
        ]);

        $department = $validated['department'] ?? $rule->department;
        if (!empty($validated['eligibility'])) {
            $department = strtolower((string) $validated['eligibility']) === 'all staff' ? null : $validated['eligibility'];
        }

        $active = array_key_exists('active', $validated) ? (bool) $validated['active'] : (bool) $rule->active;
        if (!empty($validated['status'])) {
            $active = strtolower((string) $validated['status']) !== 'inactive';
        }

        $minMinutes = $validated['min_minutes'] ?? null;
        if ($minMinutes === null && isset($validated['max_hours_per_day'])) {
            $minMinutes = (int) round(((float) $validated['max_hours_per_day']) * 60);
        }

        DB::table('overtime_rules')->where('id', $id)->update([
            'name' => $validated['name'] ?? $rule->name,
            'department' => $department,
            'location_id' => $validated['location_id'] ?? $rule->location_id,
            'min_minutes' => $minMinutes ?? $rule->min_minutes,
            'multiplier' => $validated['multiplier'] ?? $rule->multiplier,
            'requires_approval' => $validated['requires_approval'] ?? $rule->requires_approval,
            'active' => $active,
            'updated_at' => now(),
        ]);

        $updatedRule = DB::table('overtime_rules')->where('id', $id)->first();

        return ApiResponse::success('Overtime rule updated successfully', $this->overtimeRulePayload($updatedRule));
    }

    public function overtimeRuleDestroy(int $id): JsonResponse
    {
        $rule = DB::table('overtime_rules')->where('id', $id)->first();

        if (!$rule) {
            return ApiResponse::error('Overtime rule not found', null, 404);
        }

        DB::table('overtime_rules')->where('id', $id)->delete();

        return ApiResponse::success('Overtime rule deleted successfully');
    }
}
