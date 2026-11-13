<?php

namespace App\Modules\Administration\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\SendEmailNotificationJob;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Modules\User\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.email.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
        ]);

        $days = (int) ($validated['days'] ?? 30);
        $fromDate = now()->subDays($days - 1)->startOfDay();
        $toDate = now()->endOfDay();

        $baseQuery = UserNotification::query()->whereBetween('created_at', [$fromDate, $toDate]);

        $total = (clone $baseQuery)->count();
        $unread = (clone $baseQuery)->whereNull('read_at')->count();
        $read = $total - $unread;

        $byCategory = (clone $baseQuery)->selectRaw('COALESCE(category, ?) as category, COUNT(*) as total', ['uncategorized'])->groupBy('category')->orderByDesc('total')->get();

        $byType = (clone $baseQuery)->selectRaw('type, COUNT(*) as total')->groupBy('type')->orderByDesc('total')->limit(10)->get();

        return ApiResponse::success('Notification summary retrieved successfully', [
            'window' => [
                'days' => $days,
                'from' => $fromDate->toDateTimeString(),
                'to' => $toDate->toDateTimeString(),
            ],
            'summary' => [
                'total' => $total,
                'read' => $read,
                'unread' => $unread,
                'unread_rate_percent' => $total > 0 ? round(($unread / $total) * 100, 2) : 0,
            ],
            'by_category' => $byCategory,
            'top_types' => $byType,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'category' => 'sometimes|string|max:100',
            'type' => 'sometimes|string|max:100',
            'unread_only' => 'sometimes|boolean',
        ]);

        $query = UserNotification::with('sender:id,name,email')->where('user_id', $request->user()->id)->latest();

        if (!empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (!empty($validated['unread_only'])) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate($validated['per_page'] ?? 10)->withQueryString();

        return ApiResponse::success('Notifications retrieved successfully', $notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count();

        return ApiResponse::success('Unread notification count', [
            'count' => $count,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $notification = UserNotification::with('sender:id,name,email')->find($id);

        if (!$notification) {
            return ApiResponse::error('Notification not found', null, 404);
        }

        if ($notification->user_id !== $request->user()->id && !$request->user()->isAdmin() && !$request->user()->hasPermission('admin.email.manage')) {
            return ApiResponse::error('Forbidden', 'You cannot access this notification', 403);
        }

        return ApiResponse::success('Notification detail', $notification);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = UserNotification::find($id);

        if (!$notification) {
            return ApiResponse::error('Notification not found', null, 404);
        }

        if ($notification->user_id !== $request->user()->id && !$request->user()->isAdmin() && !$request->user()->hasPermission('admin.email.manage')) {
            return ApiResponse::error('Forbidden', 'You cannot modify this notification', 403);
        }

        $notification->markAsRead();

        return ApiResponse::success('Notification marked as read', $notification->fresh('sender:id,name,email'));
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return ApiResponse::success('All notifications marked as read');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $notification = UserNotification::find($id);

        if (!$notification) {
            return ApiResponse::error('Notification not found', null, 404);
        }

        if ($notification->user_id !== $request->user()->id && !$request->user()->isAdmin() && !$request->user()->hasPermission('admin.email.manage')) {
            return ApiResponse::error('Forbidden', 'You cannot delete this notification', 403);
        }

        $deleted = $notification->toArray();
        $notification->delete();

        return ApiResponse::success('Notification deleted', $deleted);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.email.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'type' => 'required|string|max:100',
            'category' => 'nullable|string|max:100',
            'data' => 'nullable|array',
        ]);

        $notifications = [];

        foreach ($validated['user_ids'] as $userId) {
            $notifications[] = UserNotification::create([
                'user_id' => $userId,
                'sender_user_id' => $user->id,
                'title' => $validated['title'],
                'message' => $validated['message'],
                'type' => $validated['type'],
                'category' => $validated['category'] ?? null,
                'data' => $validated['data'] ?? null,
            ]);
        }

        return ApiResponse::success('Notification(s) created successfully', $notifications, 201);
    }

    public function broadcast(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.email.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'type' => 'required|string|max:100',
            'category' => 'nullable|string|max:100',
            'data' => 'nullable|array',
        ]);

        $recipientIds = User::query()->where('id', '!=', $user->id)->pluck('id')->all();

        $notifications = [];

        foreach ($recipientIds as $recipientId) {
            $notifications[] = UserNotification::create([
                'user_id' => $recipientId,
                'sender_user_id' => $user->id,
                'title' => $validated['title'],
                'message' => $validated['message'],
                'type' => $validated['type'],
                'category' => $validated['category'] ?? 'broadcast',
                'data' => $validated['data'] ?? null,
            ]);
        }

        return ApiResponse::success('Broadcast notification created successfully', [
            'count' => count($notifications),
        ], 201);
    }

    // ================= EMAIL TEMPLATE =================

    public function emailTemplateIndex(Request $request): JsonResponse
    {
        $templates = EmailTemplate::query()->orderBy('name')->paginate($request->integer('per_page', 10))->withQueryString();

        return ApiResponse::success('Email templates retrieved', $templates);
    }

    public function emailTemplateStore(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('admin.email.manage')) {
                return ApiResponse::error('Forbidden', 'No permission', 403);
            }

            $validated = $request->validate([
                'key' => 'required|string|max:100|unique:email_templates,key',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'subject' => 'required|string|max:500',
                'html_body' => 'required|string',
                'text_body' => 'nullable|string',
                'placeholders' => 'nullable|array',
            ]);

            DB::beginTransaction();

            $validated['created_by'] = $user->id;
            $validated['is_active'] = true;

            if (isset($validated['placeholders'])) {
                $validated['placeholders'] = array_values($validated['placeholders']);
            }

            $template = EmailTemplate::create($validated);

            DB::commit();

            return ApiResponse::success('Email template created', $template, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('EmailTemplateStore Error: ' . $e->getMessage());

            return ApiResponse::error('Internal server error', $e->getMessage(), 500);
        }
    }

    public function emailTemplateUpdate(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('admin.email.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $template = EmailTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'subject' => 'sometimes|string|max:500',
            'html_body' => 'sometimes|string',
            'text_body' => 'nullable|string',
            'placeholders' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['placeholders'])) {
            $validated['placeholders'] = array_values($validated['placeholders']);
        }

        $template->update($validated);

        return ApiResponse::success('Email template updated', $template);
    }

    public function emailTemplatePreview(Request $request, $id): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);

        $validated = $request->validate([
            'data' => 'sometimes|array',
        ]);

        $data = $validated['data'] ?? [];

        return ApiResponse::success('Email template preview', [
            'subject' => $template->renderSubject($data),
            'html_body' => $template->renderHtmlBody($data),
            'text_body' => $template->renderTextBody($data),
        ]);
    }

    public function emailTemplateDestroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.email.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $template = EmailTemplate::findOrFail($id);
        $template->delete();

        return ApiResponse::success('Email template deleted successfully');
    }

    public function sendEmailNotification(Request $request): JsonResponse
    {
        $user = $request->user();

        // Hanya admin & HR yang boleh kirim email
        if (!$user->hasPermission('admin.email.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        // Validasi request
        $validated = $request->validate([
            'recipient_email' => 'required|email',
            'user_id' => 'nullable|integer|exists:users,id',
            'subject' => 'sometimes|string|max:500',
            'body' => 'sometimes|string',
            'template_key' => 'sometimes|string|exists:email_templates,key',
            'template_data' => 'sometimes|array',
            'type' => 'sometimes|string|in:approval,reminder,notification,alert,report,document,workflow',
            'reference_type' => 'nullable|string|max:100',
            'reference_id' => 'nullable|integer',
        ]);

        // Default type
        $validated['type'] = $validated['type'] ?? 'notification';

        // Default user_id ke user login jika tidak dikirim
        if (empty($validated['user_id'])) {
            $validated['user_id'] = $user->id;
        }

        // Jika pakai template, render subject & body dari template HANYA jika tidak dikirim manual
        if (!empty($validated['template_key'])) {
            $template = EmailTemplate::where('key', $validated['template_key'])->firstOrFail();
            $templateData = $validated['template_data'] ?? [];

            // Jika subject kosong, ambil dari template
            if (empty($validated['subject'])) {
                // Validasi placeholder hanya jika subject/body diambil dari template
                $placeholders = $template->placeholders ?? [];
                $missingPlaceholders = [];
                foreach ($placeholders as $ph) {
                    if (!array_key_exists($ph, $templateData)) {
                        $missingPlaceholders[] = $ph;
                    }
                }
                if (count($missingPlaceholders) > 0) {
                    return ApiResponse::error('Missing template_data for placeholders: ' . implode(', ', $missingPlaceholders), null, 422);
                }
                $validated['subject'] = $template->renderSubject($templateData);
            }

            // Jika body kosong, ambil dari template
            if (empty($validated['body'])) {
                $validated['body'] = $template->renderHtmlBody($templateData);
            }
        }

        // Jika tidak pakai template, subject & body wajib ada
        if (empty($validated['subject']) || empty($validated['body'])) {
            return ApiResponse::error('Subject and body are required (either directly or via template)', null, 422);
        }

        // Set default reference_type dan reference_id jika null
        $referenceType = $validated['reference_type'] ?? 'user';
        $referenceId = $validated['reference_id'] ?? $validated['user_id'];

        // Simpan log email dengan field konsisten
        $emailLog = EmailLog::create([
            'recipient_email' => $validated['recipient_email'],
            'user_id' => $validated['user_id'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'type' => $validated['type'],
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);

        // Dispatch job pengiriman email
        SendEmailNotificationJob::dispatch($emailLog);

        return ApiResponse::success('Email notification queued for sending', $emailLog, 201);
    }

    public function getEmailLogs(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.email.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $query = EmailLog::query();

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return ApiResponse::success('Email logs retrieved', $logs);
    }

    public function retryEmailNotification(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.email.manage')) {
            return ApiResponse::error('Forbidden', 'No permission', 403);
        }

        $emailLog = \App\Models\EmailLog::findOrFail($id);

        if (!$emailLog->canRetry()) {
            return ApiResponse::error('Email cannot be retried', null, 422);
        }

        $emailLog->update(['status' => 'pending']);

        \App\Jobs\SendEmailNotificationJob::dispatch($emailLog);

        return ApiResponse::success('Email notification sent for retry', $emailLog);
    }
}

