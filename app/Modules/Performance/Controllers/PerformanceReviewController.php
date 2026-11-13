<?php

namespace App\Modules\Performance\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PerformanceReview;
use App\Models\ReviewCycle;
use App\Traits\HasEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceReviewController extends Controller
{
    use HasEmployee;

    public function cyclesIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:draft,open,closed',
            'year' => 'sometimes|integer|min:2000|max:2100',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = ReviewCycle::with([
                'creator:id,name,email',
                'creator.profile:id,user_id,profile_photo_path'
            ])
            ->withCount('reviews')
            ->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['year'])) {
            $query->where('year', $validated['year']);
        }

        return ApiResponse::success('Review cycles retrieved successfully', $query->paginate($validated['per_page'] ?? 10)->withQueryString());
    }

    public function cyclesStore(Request $request): JsonResponse
    {
        $this->authorizeManage($request, 'performance.cycle.create');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'period_type' => 'sometimes|string|in:monthly,quarterly,semiannual,annual,custom',
            'year' => 'required|integer|min:2000|max:2100',
            'quarter' => 'nullable|integer|min:1|max:4',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'sometimes|string|in:draft,open,closed',
            'description' => 'nullable|string|max:5000',
        ]);

        $cycle = ReviewCycle::create([
            ...$validated,
            'period_type' => $validated['period_type'] ?? 'quarterly',
            'status' => $validated['status'] ?? ReviewCycle::STATUS_DRAFT,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::success('Review cycle created successfully', $cycle->load([
            'creator:id,name,email',
            'creator.profile:id,user_id,profile_photo_path'
        ]), 201);
    }

    public function cyclesShow(Request $request, int $id): JsonResponse
    {
        $cycle = ReviewCycle::with([
                'reviews.employee:id,user_id,employee_code,department_id,position_id',
                'reviews.employee.user:id,name,email',
                'reviews.employee.user.profile:id,user_id,profile_photo_path',
                'reviews.employee.department:id,name',
                'reviews.employee.position:id,name',
                'reviews.reviewer:id,name,email',
                'reviews.reviewer.profile:id,user_id,profile_photo_path',
                'creator:id,name,email',
                'creator.profile:id,user_id,profile_photo_path'
            ])
            ->withCount('reviews')
            ->find($id);

        if (!$cycle) {
            return ApiResponse::error('Review cycle not found', null, 404);
        }

        return ApiResponse::success('Review cycle detail', $cycle);
    }

    public function cyclesUpdate(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request, 'performance.cycle.manage');

        $cycle = ReviewCycle::find($id);

        if (!$cycle) {
            return ApiResponse::error('Review cycle not found', null, 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'period_type' => 'sometimes|string|in:monthly,quarterly,semiannual,annual,custom',
            'year' => 'sometimes|integer|min:2000|max:2100',
            'quarter' => 'sometimes|nullable|integer|min:1|max:4',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'status' => 'sometimes|string|in:draft,open,closed',
            'description' => 'sometimes|nullable|string|max:5000',
        ]);

        $cycle->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::success('Review cycle updated successfully', $cycle->fresh([
            'creator:id,name,email',
            'creator.profile:id,user_id,profile_photo_path'
        ]));
    }

    public function cyclesClose(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request, 'performance.cycle.manage');

        $cycle = ReviewCycle::find($id);

        if (!$cycle) {
            return ApiResponse::error('Review cycle not found', null, 404);
        }

        $cycle->update([
            'status' => ReviewCycle::STATUS_CLOSED,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::success('Review cycle closed successfully', $cycle->fresh([
            'creator:id,name,email',
            'creator.profile:id,user_id,profile_photo_path'
        ]));
    }

    public function reviewsIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'review_cycle_id' => 'sometimes|integer|exists:review_cycles,id',
            'employee_id' => 'sometimes|integer|exists:employees,id',
            'reviewer_user_id' => 'sometimes|integer|exists:users,id',
            'status' => 'sometimes|string|in:draft,submitted,reviewed,approved,rejected',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = PerformanceReview::with([
                'cycle:id,name,period_type,year', 
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'reviewer:id,name,email', 
                'reviewer.profile:id,user_id,profile_photo_path',
                'kpi:id,title,target,achievement,score'
            ])
            ->latest();

        if (!empty($validated['review_cycle_id'])) {
            $query->where('review_cycle_id', $validated['review_cycle_id']);
        }

        if (!empty($validated['employee_id'])) {
            $query->where('employee_id', $validated['employee_id']);
        }

        if (!empty($validated['reviewer_user_id'])) {
            $query->where('reviewer_user_id', $validated['reviewer_user_id']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return ApiResponse::success('Performance reviews retrieved successfully', $query->paginate($validated['per_page'] ?? 10)->withQueryString());
    }

    public function reviewsStore(Request $request): JsonResponse
    {
        $this->authorizeManage($request, 'performance.review.create');

        $validated = $request->validate([
            'review_cycle_id' => 'required|integer|exists:review_cycles,id',
            'employee_id' => 'required|integer|exists:employees,id',
            'reviewer_user_id' => 'required|integer|exists:users,id',
            'kpi_id' => 'nullable|integer|exists:kpis,id',
            'score' => 'nullable|numeric|min:0|max:100',
            'status' => 'sometimes|string|in:draft,submitted,reviewed,approved,rejected',
            'strengths' => 'nullable|string|max:5000',
            'improvements' => 'nullable|string|max:5000',
            'feedback' => 'nullable|string|max:5000',
            'reviewer_comment' => 'nullable|string|max:5000',
        ]);

        $review = PerformanceReview::create([
            ...$validated,
            'status' => $validated['status'] ?? PerformanceReview::STATUS_DRAFT,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::success('Performance review created successfully', $review->load([
            'cycle:id,name,period_type,year', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'reviewer:id,name,email', 
            'reviewer.profile:id,user_id,profile_photo_path',
            'kpi:id,title,target,achievement,score'
        ]), 201);
    }

    public function reviewsShow(Request $request, int $id): JsonResponse
    {
        $review = PerformanceReview::with([
            'cycle:id,name,period_type,year', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'reviewer:id,name,email', 
            'reviewer.profile:id,user_id,profile_photo_path',
            'kpi:id,title,target,achievement,score'
        ])
            ->find($id);

        if (!$review) {
            return ApiResponse::error('Performance review not found', null, 404);
        }

        return ApiResponse::success('Performance review detail', $review);
    }

    public function reviewsUpdate(Request $request, int $id): JsonResponse
    {
        $review = PerformanceReview::find($id);

        if (!$review) {
            return ApiResponse::error('Performance review not found', null, 404);
        }

        $user = $request->user();
        $employee = $this->getAuthenticatedEmployee();
        $isOwner = $employee->id === $review->employee_id;

        if (!($isOwner || $user->hasPermission('performance.review.update'))) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'review_cycle_id' => 'sometimes|integer|exists:review_cycles,id',
            'reviewer_user_id' => 'sometimes|integer|exists:users,id',
            'kpi_id' => 'sometimes|nullable|integer|exists:kpis,id',
            'score' => 'sometimes|nullable|numeric|min:0|max:100',
            'status' => 'sometimes|string|in:draft,submitted,reviewed,approved,rejected',
            'strengths' => 'sometimes|nullable|string|max:5000',
            'improvements' => 'sometimes|nullable|string|max:5000',
            'feedback' => 'sometimes|nullable|string|max:5000',
            'reviewer_comment' => 'sometimes|nullable|string|max:5000',
        ]);

        $review->update([
            ...$validated,
            'updated_by' => $user->id,
        ]);

        return ApiResponse::success('Performance review updated successfully', $review->fresh([
            'cycle:id,name,period_type,year', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'reviewer:id,name,email', 
            'reviewer.profile:id,user_id,profile_photo_path',
            'kpi:id,title,target,achievement,score'
        ]));
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $review = PerformanceReview::find($id);

        if (!$review) {
            return ApiResponse::error('Performance review not found', null, 404);
        }

        $employee = $this->getAuthenticatedEmployee();

        if ($employee->id !== $review->employee_id) {
            return ApiResponse::error('Forbidden', 'You cannot submit this review', 403);
        }

        if ($review->status !== PerformanceReview::STATUS_DRAFT) {
            return ApiResponse::error('Invalid status', 'Only draft review can be submitted', 400);
        }

        $review->update([
            'status' => PerformanceReview::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::success('Performance review submitted successfully', $review->fresh([
            'cycle:id,name,period_type,year', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'reviewer:id,name,email', 
            'reviewer.profile:id,user_id,profile_photo_path',
            'kpi:id,title,target,achievement,score'
        ]));
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('performance.review.update')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $record = PerformanceReview::find($id);

        if (!$record) {
            return ApiResponse::error('Performance review not found', null, 404);
        }

        $validated = $request->validate([
            'score' => 'nullable|numeric|min:0|max:100',
            'reviewer_comment' => 'nullable|string|max:5000',
            'feedback' => 'nullable|string|max:5000',
        ]);

        $record->update([
            'score' => $validated['score'] ?? $record->score,
            'reviewer_comment' => $validated['reviewer_comment'] ?? $record->reviewer_comment,
            'feedback' => $validated['feedback'] ?? $record->feedback,
            'status' => PerformanceReview::STATUS_REVIEWED,
            'reviewed_at' => now(),
            'updated_by' => $user->id,
        ]);

        return ApiResponse::success('Performance review marked as reviewed', $record->fresh([
            'cycle:id,name,period_type,year', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'reviewer:id,name,email', 
            'reviewer.profile:id,user_id,profile_photo_path',
            'kpi:id,title,target,achievement,score'
        ]));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('performance.review.approve')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $record = PerformanceReview::find($id);

        if (!$record) {
            return ApiResponse::error('Performance review not found', null, 404);
        }

        if (!in_array($record->status, [PerformanceReview::STATUS_SUBMITTED, PerformanceReview::STATUS_REVIEWED], true)) {
            return ApiResponse::error('Invalid status', 'Review must be submitted or reviewed first', 400);
        }

        $record->update([
            'status' => PerformanceReview::STATUS_APPROVED,
            'approved_at' => now(),
            'updated_by' => $user->id,
        ]);

        return ApiResponse::success('Performance review approved successfully', $record->fresh([
            'cycle:id,name,period_type,year', 
            'employee:id,user_id,employee_code,department_id,position_id',
            'employee.user:id,name,email',
            'employee.user.profile:id,user_id,profile_photo_path',
            'employee.department:id,name',
            'employee.position:id,name',
            'reviewer:id,name,email', 
            'reviewer.profile:id,user_id,profile_photo_path',
            'kpi:id,title,target,achievement,score'
        ]));
    }

    public function myReviews(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        $reviews = PerformanceReview::with([
                'cycle:id,name,period_type,year', 
                'employee:id,user_id,employee_code,department_id,position_id',
                'employee.user:id,name,email',
                'employee.user.profile:id,user_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,name',
                'reviewer:id,name,email', 
                'reviewer.profile:id,user_id,profile_photo_path',
                'kpi:id,title,target,achievement,score'
            ])
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return ApiResponse::success('My performance reviews retrieved successfully', $reviews);
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'review_cycle_id' => 'sometimes|integer|exists:review_cycles,id',
        ]);

        $query = PerformanceReview::query();

        if (!empty($validated['review_cycle_id'])) {
            $query->where('review_cycle_id', $validated['review_cycle_id']);
        }

        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        return ApiResponse::success('Performance review summary retrieved successfully', [
            'total_reviews' => (clone $query)->count(),
            'average_score' => round((float) ((clone $query)->whereNotNull('score')->avg('score') ?? 0), 2),
            'approved_reviews' => (clone $query)->where('status', PerformanceReview::STATUS_APPROVED)->count(),
            'pending_reviews' => (clone $query)->whereIn('status', [PerformanceReview::STATUS_DRAFT, PerformanceReview::STATUS_SUBMITTED, PerformanceReview::STATUS_REVIEWED])->count(),
            'by_status' => $byStatus,
        ]);
    }

    private function authorizeManage(Request $request, string $permission): void
    {
        $user = $request->user();

        if (!$user->hasPermission($permission)) {
            abort(403, 'No permission');
        }
    }
}
