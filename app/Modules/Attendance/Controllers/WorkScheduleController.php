<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WorkScheduleController extends Controller
{
    /**
     * GET /work-schedules
     */
    public function index(Request $request): JsonResponse
    {
        $schedules = WorkSchedule::latest()->paginate($request->integer('per_page', 10))->withQueryString();

        return ApiResponse::success('Work schedules list', $schedules);
    }

    /**
     * POST /work-schedules
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'check_in_time' => ['required', 'regex:/^([01][0-9]|2[0-3]):([0-5][0-9])(:[0-5][0-9])?$/'],
            'grace_period' => 'required|integer|min:0',
            'check_out_time' => ['required', 'regex:/^([01][0-9]|2[0-3]):([0-5][0-9])(:[0-5][0-9])?$/'],
        ]);

        $schedule = WorkSchedule::create($validated);

        return ApiResponse::success('Work schedule created', $schedule, 201);
    }

    /**
     * GET /work-schedules/{id}
     */
    public function show($id): JsonResponse
    {
        $schedule = WorkSchedule::findOrFail($id);

        return ApiResponse::success('Work schedule detail', $schedule);
    }

    /**
     * PUT /work-schedules/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $schedule = WorkSchedule::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'check_in_time' => ['sometimes', 'regex:/^([01][0-9]|2[0-3]):([0-5][0-9])(:[0-5][0-9])?$/'],
            'grace_period' => 'sometimes|integer|min:0',
            'check_out_time' => ['sometimes', 'regex:/^([01][0-9]|2[0-3]):([0-5][0-9])(:[0-5][0-9])?$/'],
        ]);

        $schedule->update($validated);

        return ApiResponse::success('Work schedule updated', $schedule->fresh());
    }

    /**
     * DELETE /work-schedules/{id}
     */
    public function destroy($id): JsonResponse
    {
        $schedule = WorkSchedule::findOrFail($id);
        $deleted = $schedule->toArray();

        $schedule->delete();

        return ApiResponse::success('Work schedule deleted', $deleted);
    }
}
