<?php

namespace App\Modules\Performance\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Traits\HasEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EngagementController extends Controller
{
    use HasEmployee;

    private function canUseEngagement(Request $request, string $permission): bool
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

    public function surveyIndex(Request $request): JsonResponse
    {
        if (!$this->canUseEngagement($request, 'engagement.survey.view')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        $data = DB::table('engagement_surveys as s')
            ->leftJoin('users as u', 'u.id', '=', 's.created_by')
            ->leftJoin('user_profiles as p', 'p.user_id', '=', 'u.id')
            ->select('s.*', 'u.name as creator_name', 'p.avatar as creator_avatar')
            ->orderByDesc('s.id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();
        return ApiResponse::success('Engagement surveys retrieved successfully', $data);
    }

    public function surveyStore(Request $request): JsonResponse
    {
        if (!$this->canUseEngagement($request, 'engagement.survey.create')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'survey_type' => 'sometimes|string|in:pulse,enps,custom',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'anonymous' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:draft,published,closed',
            'questions' => 'sometimes|array',
            'questions.*.question_type' => 'required_with:questions|string|in:rating,text,mcq,enps',
            'questions.*.question_text' => 'required_with:questions|string|max:1000',
            'questions.*.required' => 'sometimes|boolean',
            'questions.*.options' => 'nullable|array',
        ]);

        $surveyId = DB::table('engagement_surveys')->insertGetId([
            'title' => $validated['title'],
            'survey_type' => $validated['survey_type'] ?? 'pulse',
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'anonymous' => $validated['anonymous'] ?? true,
            'status' => $validated['status'] ?? 'draft',
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (($validated['questions'] ?? []) as $idx => $question) {
            DB::table('engagement_survey_questions')->insert([
                'survey_id' => $surveyId,
                'question_type' => $question['question_type'],
                'question_text' => $question['question_text'],
                'order_no' => $idx + 1,
                'required' => $question['required'] ?? true,
                'options' => isset($question['options']) ? json_encode($question['options']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return ApiResponse::success('Survey created successfully', DB::table('engagement_surveys')->where('id', $surveyId)->first(), 201);
    }

    public function submitResponse(Request $request, int $surveyId): JsonResponse
    {
        if (!$this->canUseEngagement($request, 'engagement.survey.respond')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        $employee = $this->getAuthenticatedEmployee();

        $validated = $request->validate([
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|integer|exists:engagement_survey_questions,id',
            'answers.*.rating_value' => 'nullable|numeric|min:0|max:10',
            'answers.*.text_answer' => 'nullable|string|max:5000',
        ]);

        foreach ($validated['answers'] as $answer) {
            DB::table('engagement_survey_responses')->insert([
                'survey_id' => $surveyId,
                'question_id' => $answer['question_id'],
                'employee_id' => $employee->id,
                'rating_value' => $answer['rating_value'] ?? null,
                'text_answer' => $answer['text_answer'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return ApiResponse::success('Survey response submitted successfully');
    }

    public function analytics(Request $request, int $surveyId): JsonResponse
    {
        if (!$this->canUseEngagement($request, 'engagement.survey.analytics')) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        $totalResponses = DB::table('engagement_survey_responses')->where('survey_id', $surveyId)->count();
        $avgRating = DB::table('engagement_survey_responses')->where('survey_id', $surveyId)->whereNotNull('rating_value')->avg('rating_value');

        $enps = DB::table('engagement_survey_responses')
            ->where('survey_id', $surveyId)
            ->whereNotNull('rating_value')
            ->selectRaw('SUM(CASE WHEN rating_value >= 9 THEN 1 ELSE 0 END) as promoters')
            ->selectRaw('SUM(CASE WHEN rating_value <= 6 THEN 1 ELSE 0 END) as detractors')
            ->selectRaw('COUNT(*) as total')
            ->first();

        $enpsScore = ($enps && $enps->total > 0)
            ? round((($enps->promoters / $enps->total) * 100) - (($enps->detractors / $enps->total) * 100), 2)
            : 0;

        $byDept = DB::table('engagement_survey_responses as r')
            ->join('employees as e', 'e.id', '=', 'r.employee_id')
            ->join('departments as d', 'd.id', '=', 'e.department_id')
            ->where('r.survey_id', $surveyId)
            ->whereNotNull('r.rating_value')
            ->select('d.name as department_name')
            ->selectRaw('AVG(r.rating_value) as avg_rating')
            ->selectRaw('COUNT(DISTINCT r.employee_id) as total_participants')
            ->groupBy('d.id', 'd.name')
            ->get();

        return ApiResponse::success('Survey analytics retrieved successfully', [
            'total_responses' => $totalResponses,
            'average_rating' => $avgRating ? round((float) $avgRating, 2) : 0,
            'enps_score' => $enpsScore,
            'by_department' => $byDept,
        ]);
    }
}
