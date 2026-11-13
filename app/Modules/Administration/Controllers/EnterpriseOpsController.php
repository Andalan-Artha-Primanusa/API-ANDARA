<?php

namespace App\Modules\Administration\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnterpriseOpsController extends Controller
{
    public function upsertCompProfile(Request $request, int $employeeId): JsonResponse
    {
        $validated = $request->validate([
            'tax_number' => 'nullable|string|max:100',
            'tax_status' => 'nullable|string|max:100',
            'bpjs_kesehatan_pct' => 'nullable|numeric|min:0|max:100',
            'bpjs_ketenagakerjaan_pct' => 'nullable|numeric|min:0|max:100',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_no' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
        ]);

        DB::table('employee_compensation_profiles')->updateOrInsert(
            ['employee_id' => $employeeId],
            [
                ...$validated,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return ApiResponse::success('Compensation profile saved successfully', DB::table('employee_compensation_profiles')->where('employee_id', $employeeId)->first());
    }

    public function addRetroAdjustment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payroll_id' => 'nullable|integer|exists:payrolls,id',
            'employee_id' => 'required|integer|exists:employees,id',
            'type' => 'sometimes|string|max:100',
            'amount' => 'required|numeric',
            'reason' => 'nullable|string|max:5000',
            'status' => 'sometimes|string|in:pending,approved,rejected,applied',
        ]);

        $id = DB::table('payroll_retro_adjustments')->insertGetId([
            ...$validated,
            'type' => $validated['type'] ?? 'correction',
            'status' => $validated['status'] ?? 'pending',
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Retro adjustment created successfully', DB::table('payroll_retro_adjustments')->where('id', $id)->first(), 201);
    }

    public function bankExportPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'required|string|max:50',
        ]);

        $rows = DB::table('payrolls as p')
            ->join('employees as e', 'e.id', '=', 'p.employee_id')
            ->leftJoin('employee_compensation_profiles as cp', 'cp.employee_id', '=', 'e.id')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->where('p.period', $validated['period'])
            ->select('p.id as payroll_id', 'p.period', 'u.name as employee_name', 'cp.bank_name', 'cp.bank_account_no', 'cp.bank_account_name', 'p.take_home_pay')
            ->get();

        return ApiResponse::success('Bank export preview generated successfully', [
            'period' => $validated['period'],
            'count' => $rows->count(),
            'rows' => $rows,
        ]);
    }

    public function notificationTemplateStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:100|unique:notification_templates,code',
            'channel' => 'sometimes|string|in:in_app,email,chat',
            'title_template' => 'required|string|max:255',
            'body_template' => 'required|string|max:10000',
            'active' => 'sometimes|boolean',
        ]);

        $id = DB::table('notification_templates')->insertGetId([
            'code' => $validated['code'],
            'channel' => $validated['channel'] ?? 'in_app',
            'title_template' => $validated['title_template'],
            'body_template' => $validated['body_template'],
            'active' => $validated['active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Notification template created successfully', DB::table('notification_templates')->where('id', $id)->first(), 201);
    }

    public function notificationRuleStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'event_key' => 'required|string|max:255',
            'conditions' => 'nullable|array',
            'channels' => 'nullable|array',
            'template_id' => 'nullable|integer|exists:notification_templates,id',
            'active' => 'sometimes|boolean',
        ]);

        $id = DB::table('notification_rule_sets')->insertGetId([
            'name' => $validated['name'],
            'event_key' => $validated['event_key'],
            'conditions' => isset($validated['conditions']) ? json_encode($validated['conditions']) : null,
            'channels' => isset($validated['channels']) ? json_encode($validated['channels']) : null,
            'template_id' => $validated['template_id'] ?? null,
            'active' => $validated['active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Notification rule created successfully', DB::table('notification_rule_sets')->where('id', $id)->first(), 201);
    }

    public function scheduleNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'nullable|integer|exists:notification_templates,id',
            'channel' => 'sometimes|string|in:in_app,email,chat',
            'target_user_id' => 'nullable|integer|exists:users,id',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:10000',
            'scheduled_at' => 'required|date',
        ]);

        $id = DB::table('scheduled_notifications')->insertGetId([
            'template_id' => $validated['template_id'] ?? null,
            'channel' => $validated['channel'] ?? 'in_app',
            'target_user_id' => $validated['target_user_id'] ?? null,
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Notification scheduled successfully', DB::table('scheduled_notifications')->where('id', $id)->first(), 201);
    }

    public function getRetentionPolicies(): JsonResponse
    {
        $policies = DB::table('data_retention_policies')
            ->where('active', true)
            ->orderBy('module')
            ->get();
        return ApiResponse::success('Retention policies retrieved successfully', $policies);
    }

    public function getPrivacyRequests(): JsonResponse
    {
        $requests = DB::table('privacy_requests')
            ->leftJoin('users', 'privacy_requests.requester_user_id', '=', 'users.id')
            ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
            ->select(
                'privacy_requests.id',
                'privacy_requests.request_type',
                'privacy_requests.status',
                'privacy_requests.description',
                'privacy_requests.created_at',
                'privacy_requests.updated_at',
                'users.name as requester_name',
                'employees.employee_code',
                'employees.department'
            )
            ->orderByDesc('privacy_requests.created_at')
            ->get();
        return ApiResponse::success('Privacy requests retrieved successfully', $requests);
    }

    public function deactivateRetentionPolicy(string $module): JsonResponse
    {
        $deleted = DB::table('data_retention_policies')->where('module', $module)->delete();
        if ($deleted === 0) {
            return ApiResponse::error('Retention policy not found', null, 404);
        }
        return ApiResponse::success('Retention policy deleted successfully');
    }

    public function retentionPolicyStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module' => 'required|string|max:100',
            'retain_days' => 'required|integer|min:1|max:36500',
            'anonymize_after_expiry' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
        ]);

        DB::table('data_retention_policies')->updateOrInsert(
            ['module' => $validated['module']],
            [
                'retain_days' => $validated['retain_days'],
                'anonymize_after_expiry' => $validated['anonymize_after_expiry'] ?? false,
                'active' => $validated['active'] ?? true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return ApiResponse::success('Data retention policy saved successfully', DB::table('data_retention_policies')->where('module', $validated['module'])->first());
    }

    public function complianceTaskStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'module' => 'nullable|string|max:100',
            'status' => 'sometimes|string|in:open,in_progress,done,cancelled',
            'due_date' => 'nullable|date',
            'owner_user_id' => 'nullable|integer|exists:users,id',
            'notes' => 'nullable|string|max:5000',
        ]);

        $id = DB::table('compliance_tasks')->insertGetId([
            ...$validated,
            'status' => $validated['status'] ?? 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Compliance task created successfully', DB::table('compliance_tasks')->where('id', $id)->first(), 201);
    }

    public function privacyRequestStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_type' => 'required|string|in:access,update,delete,anonymize,export',
            'description' => 'nullable|string|max:5000',
        ]);

        $id = DB::table('privacy_requests')->insertGetId([
            'requester_user_id' => $request->user()->id,
            'request_type' => $validated['request_type'],
            'status' => 'submitted',
            'description' => $validated['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Privacy request submitted successfully', DB::table('privacy_requests')->where('id', $id)->first(), 201);
    }
}
