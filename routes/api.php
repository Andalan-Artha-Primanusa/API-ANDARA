
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PromotionController;
use App\Modules\Payroll\Services\ProgressiveTaxService;
use App\Http\Controllers\Api\EmploymentLetterController;
use App\Modules\Payroll\Controllers\SeveranceController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\GoogleAuthController;
use App\Modules\Employee\Controllers\UserProfileController;
use App\Modules\Employee\Controllers\EmployeeController;
use App\Modules\Attendance\Controllers\AttendanceController;
use App\Modules\Attendance\Controllers\QrAttendanceController;
use App\Modules\Attendance\Controllers\SecurityPatrolController;
use App\Modules\Leave\Controllers\LeaveController;
use App\Modules\Performance\Controllers\KpiController;
use App\Modules\Reimbursement\Controllers\ReimbursementController;
use App\Modules\Payroll\Controllers\PayrollController;
use App\Modules\Payroll\Controllers\PayrollDetailController;
use App\Modules\Organization\Controllers\LocationController;
use App\Modules\Administration\Controllers\RoleController;
use App\Modules\Administration\Controllers\PermissionController;
use App\Modules\User\Controllers\UserController;
use App\Modules\Attendance\Controllers\WorkScheduleController;
use App\Modules\Report\Controllers\PeopleInsightController;
use App\Modules\Administration\Controllers\AuditLogController;
use App\Modules\Administration\Controllers\NotificationController;
use App\Modules\Training\Controllers\TrainingController;
use App\Modules\Competency\Controllers\CompetencyController;
use App\Modules\Leave\Controllers\LeavePolicyController;
use App\Modules\Leave\Controllers\LeaveTypeController;
use App\Modules\Asset\Controllers\AssetController;
use App\Modules\Report\Controllers\ReportingController;
use App\Modules\Report\Controllers\DashboardConfigController;
use App\Modules\Administration\Controllers\ApprovalFlowController;
use App\Modules\Administration\Controllers\DataImportController;
use App\Modules\Organization\Controllers\OrgStructureController;
use App\Modules\Administration\Controllers\ComplianceController;
use App\Http\Controllers\Api\BenefitController;
use App\Modules\Performance\Controllers\PerformanceReviewController;
use App\Http\Controllers\Api\CareerDevelopmentController;
use App\Http\Controllers\Api\EmployeeDocumentController;
use App\Http\Controllers\Api\AssignmentLetterController;
use App\Modules\Attendance\Controllers\BiometricIntegrationController;
use App\Modules\Performance\Controllers\EngagementController;
use App\Modules\Administration\Controllers\WorkforcePolicyController;
use App\Modules\Administration\Controllers\EnterpriseOpsController;
use App\Modules\Administration\Controllers\WorkforceComplianceController;
use App\Modules\Organization\Controllers\CompanyController;
use App\Modules\Organization\Controllers\DepartmentController;
use App\Modules\Organization\Controllers\PositionController;
use App\Modules\Administration\Controllers\NotificationSettingController;
use App\Modules\Overtime\Controllers\OvertimeController;
use App\Modules\Performance\Controllers\KpiPeriodController;
use App\Http\Controllers\Api\MineproLegacyController;
use App\Http\Controllers\Api\AndalanMobileController;
use App\Http\Controllers\Api\AndalanKpiController;
use App\Http\Controllers\Api\AndalanWorkforceController;
// PROGRESSIVE TAX (PPh21 Progresif)
Route::post('tax/progressive/calculate', function (\Illuminate\Http\Request $request) {
    $validated = $request->validate([
        'annual_income' => 'required|numeric|min:0',
    ]);
    $service = new ProgressiveTaxService();
    $result = $service->calculate($validated['annual_income']);
    return \App\Helpers\ApiResponse::success('Pajak progresif dihitung', $result);
});

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'API HRIS aktif 🚀'
    ]);
});
// ...existing use statements...
// Surat Pengalaman Kerja & Surat Keterangan Bekerja

// ...existing public routes...

// AUTH
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
// Backward-compatible alias for older frontend bundles
Route::post('/vendor-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

// Temporary legacy employee read endpoints for local integration testing.
// Write operations remain inside the authenticated route group below.
Route::get('/employees', [EmployeeController::class, 'legacyIndex']);
Route::get('/employees/summary', [EmployeeController::class, 'legacySummary']);
Route::get('/employees/{employee}', [EmployeeController::class, 'legacyShow']);

// Read-only MINEPRO bridge for modules whose data already exists in legacy tables.
Route::prefix('presentation/legacy')->group(function () {
    Route::get('/reimbursements', [MineproLegacyController::class, 'reimbursements']);
    Route::get('/reimbursements/statistics', [MineproLegacyController::class, 'reimbursementStatistics']);
    Route::get('/benefit-entitlements', [MineproLegacyController::class, 'entitlements']);
    Route::get('/kpi-ees', [MineproLegacyController::class, 'ees']);
    Route::get('/master-data', [MineproLegacyController::class, 'masterData']);
    Route::get('/profile/{user}', [MineproLegacyController::class, 'profile']);
    Route::get('/attendance/locations', [MineproLegacyController::class, 'attendanceLocations']);
    Route::post('/attendance/locations', [MineproLegacyController::class, 'createAttendanceLocation']);
    Route::put('/attendance/locations/{id}', [MineproLegacyController::class, 'updateAttendanceLocation']);
    Route::delete('/attendance/locations/{id}', [MineproLegacyController::class, 'deleteAttendanceLocation']);
    Route::get('/attendance/monitoring', [MineproLegacyController::class, 'attendanceMonitoring']);
    Route::delete('/attendance/monitoring/{id}', [MineproLegacyController::class, 'deleteAttendanceMonitoring']);
    Route::get('/attendance/{user}/today', [MineproLegacyController::class, 'attendanceToday']);
    Route::get('/attendance/{user}/history', [MineproLegacyController::class, 'attendanceHistory']);
    Route::get('/attendance/{user}/biometric/options', [MineproLegacyController::class, 'biometricOptions']);
    Route::post('/attendance/{user}/biometric/register', [MineproLegacyController::class, 'biometricRegister']);
    Route::post('/attendance/{user}/biometric/verify', [MineproLegacyController::class, 'biometricVerify']);
    Route::post('/attendance/{user}/check-in', [MineproLegacyController::class, 'attendanceCheckIn']);
    Route::post('/attendance/{user}/check-out', [MineproLegacyController::class, 'attendanceCheckOut']);
});

// ANDALAN mobile modules. These tables are intentionally isolated from the
// existing Laravel HRIS tables and the read-only MINEPRO bridge.
Route::prefix('presentation/andalan/mobile')->group(function () {
    Route::get('/profile/{user}', [AndalanMobileController::class, 'mobileProfile']);
    Route::get('/news', [AndalanMobileController::class, 'news']);
    Route::post('/news', [AndalanMobileController::class, 'createNews']);
    Route::put('/news/{id}', [AndalanMobileController::class, 'updateNews']);
    Route::get('/roster', [AndalanMobileController::class, 'roster']);
    Route::post('/roster', [AndalanMobileController::class, 'createRoster']);
    Route::put('/roster/{id}', [AndalanMobileController::class, 'updateRoster']);
    Route::get('/leaves/{user}', [AndalanMobileController::class, 'leaves']);
    Route::post('/leaves', [AndalanMobileController::class, 'createLeave']);
    Route::get('/reimbursements/{user}', [AndalanMobileController::class, 'reimbursements']);
    Route::post('/reimbursements', [AndalanMobileController::class, 'createReimbursement']);
    Route::get('/business-trips/{user}', [AndalanMobileController::class, 'businessTrips']);
    Route::post('/business-trips', [AndalanMobileController::class, 'createBusinessTrip']);
    Route::get('/requests/{user}', [AndalanMobileController::class, 'requests']);
    Route::get('/notifications/{user}', [AndalanMobileController::class, 'notifications']);
    Route::put('/notifications/{id}/read', [AndalanMobileController::class, 'markNotificationRead'])->whereNumber('id');
});

// ANDALAN KPI module. KPI data is isolated in andalan_* tables; employee
// identifiers are copied from MINEPRO only as external references.
Route::prefix('presentation/andalan/kpi')->group(function () {
    Route::get('/cycles', [AndalanKpiController::class, 'index']);
    Route::post('/cycles', [AndalanKpiController::class, 'store']);
    Route::get('/cycles/user/{user}', [AndalanKpiController::class, 'forUser']);
    Route::get('/cycles/{id}', [AndalanKpiController::class, 'show'])->whereNumber('id');
    Route::put('/cycles/{id}', [AndalanKpiController::class, 'update'])->whereNumber('id');
    Route::delete('/cycles/{id}', [AndalanKpiController::class, 'destroy'])->whereNumber('id');
    Route::post('/cycles/{id}/submit', [AndalanKpiController::class, 'submit'])->whereNumber('id');
    Route::put('/cycles/{id}/self-assessment', [AndalanKpiController::class, 'selfAssessment'])->whereNumber('id');
    Route::post('/cycles/{id}/approve', [AndalanKpiController::class, 'approve'])->whereNumber('id');
});

// ANDALAN workforce extensions. These tables store new workflow data only;
// MINEPRO is used as the employee identity/reference source.
Route::prefix('presentation/andalan/workforce')->group(function () {
    Route::get('/payroll/{user}', [AndalanWorkforceController::class, 'payroll']);
    Route::post('/payroll/periods', [AndalanWorkforceController::class, 'createPayrollPeriod']);
    Route::put('/payroll/periods/{id}', [AndalanWorkforceController::class, 'updatePayrollPeriod'])->whereNumber('id');
    Route::post('/payroll/records', [AndalanWorkforceController::class, 'createPayrollRecord']);
    Route::post('/payroll/periods/{id}/approve', [AndalanWorkforceController::class, 'approvePayrollPeriod'])->whereNumber('id');
    Route::get('/training/programs', [AndalanWorkforceController::class, 'trainingPrograms']);
    Route::post('/training/programs', [AndalanWorkforceController::class, 'createTrainingProgram']);
    Route::put('/training/programs/{id}', [AndalanWorkforceController::class, 'updateTrainingProgram'])->whereNumber('id');
    Route::get('/training/enrollments/{user}', [AndalanWorkforceController::class, 'trainingEnrollments']);
    Route::post('/training/programs/{programId}/enroll', [AndalanWorkforceController::class, 'enrollTraining'])->whereNumber('programId');
    Route::post('/training/enrollments/{id}/approve', [AndalanWorkforceController::class, 'approveTraining'])->whereNumber('id');
    Route::get('/assets/{user}', [AndalanWorkforceController::class, 'assets']);
    Route::post('/assets', [AndalanWorkforceController::class, 'createAsset']);
    Route::put('/assets/{id}', [AndalanWorkforceController::class, 'updateAsset'])->whereNumber('id');
    Route::get('/super-admin/assets/{user}', [AndalanWorkforceController::class, 'superAdminAssets']);
    Route::post('/super-admin/assets', [AndalanWorkforceController::class, 'createSuperAdminAsset']);
    Route::get('/super-admin/assets/{user}/scan/{assetCode}', [AndalanWorkforceController::class, 'scanSuperAdminAsset']);
    Route::get('/admin/{user}/payroll', [AndalanWorkforceController::class, 'adminPayroll']);
    Route::get('/admin/{user}/training', [AndalanWorkforceController::class, 'adminTraining']);
    Route::get('/admin/{user}/assets', [AndalanWorkforceController::class, 'adminAssets']);
    Route::get('/admin/{user}/overtime', [AndalanWorkforceController::class, 'adminOvertime']);
    Route::get('/admin/{user}/documents', [AndalanWorkforceController::class, 'adminDocuments']);
    Route::post('/assets/{assetId}/assign', [AndalanWorkforceController::class, 'assignAsset'])->whereNumber('assetId');
    Route::post('/assets/assignments/{id}/approve', [AndalanWorkforceController::class, 'approveAsset'])->whereNumber('id');
    Route::get('/overtime/{user}', [AndalanWorkforceController::class, 'overtime']);
    Route::post('/overtime', [AndalanWorkforceController::class, 'createOvertime']);
    Route::put('/overtime/{id}', [AndalanWorkforceController::class, 'updateOvertime'])->whereNumber('id');
    Route::post('/overtime/{id}/approve', [AndalanWorkforceController::class, 'approveOvertime'])->whereNumber('id');
    Route::get('/documents/{user}', [AndalanWorkforceController::class, 'documents']);
    Route::post('/documents', [AndalanWorkforceController::class, 'createDocument']);
    Route::put('/documents/{id}', [AndalanWorkforceController::class, 'updateDocument'])->whereNumber('id');
    Route::post('/documents/{id}/review', [AndalanWorkforceController::class, 'reviewDocument'])->whereNumber('id');
});

// Temporary presentation menu list: expose all registered application menus
// while the legacy SQL Server authorization mapping is being integrated.
Route::get('/presentation/menus', function () {
    $groupIcons = [
        'Dashboard' => 'LayoutDashboard',
        'Karyawan' => 'Users',
        'Absensi' => 'CalendarDays',
        'Cuti' => 'CalendarDays',
        'Payroll' => 'Banknote',
        'Aset' => 'Briefcase',
        'Reimburse' => 'Wallet',
        'Pelatihan' => 'GraduationCap',
        'Karir' => 'Award',
        'KPI' => 'Target',
        'Laporan' => 'FileBarChart',
        'Kepatuhan' => 'ShieldCheck',
        'Master Data' => 'ListChecks',
        'Admin' => 'Settings',
        'Legal' => 'FileText',
    ];

      // Presentation mode only exposes routes backed by the legacy SQL Server bridge.
      $menus = collect(\App\Constants\MenuRegistry::all())
        ->filter(fn (array $menu) => in_array($menu['path'], [
            '/dashboard',
            '/employees',
            '/reimbursements',
            '/kpis',
            '/organization/master-data',
            '/locations',
            '/attendance/monitoring',
            '/attendance/check-in',
            '/attendance/check-out',
        ], true));

    return \App\Helpers\ApiResponse::success(
        'Presentation menus',
        $menus->groupBy('group')->map(function ($groupMenus, string $group) use ($groupIcons) {
            return [
                'key' => 'presentation.group.' . \Illuminate\Support\Str::slug($group),
                'label' => $group,
                'path' => null,
                'icon' => $groupIcons[$group] ?? 'ListChecks',
                'children' => $groupMenus->map(function (array $menu) use ($groupIcons) {
                    return [
                        'key' => 'presentation.' . trim(str_replace('/', '.', $menu['path']), '.'),
                        'label' => $menu['label'],
                        'path' => $menu['path'],
                        'icon' => $groupIcons[$menu['group']] ?? 'ListChecks',
                        'children' => [],
                    ];
                })->values()->all(),
            ];
        })->values()->all()
    );
});

// GOOGLE SSO
Route::prefix('auth')->group(function () {
    Route::get('/google', [GoogleAuthController::class, 'redirect']);
    Route::get('/google/callback', [GoogleAuthController::class, 'callback']);
});

Route::get('/documents/{filename}', [EmployeeDocumentController::class, 'download']);
Route::get('/overtime/evidences/{id}/file', [OvertimeController::class, 'downloadEvidence'])
    ->middleware('signed')
    ->whereNumber('id')
    ->name('overtime.evidences.file');

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (AUTHENTICATED)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'audit.trail'])->group(function () {
    // Surat Pengalaman Kerja & Surat Keterangan Bekerja
    Route::post('employees/{employee}/experience-letter', [EmploymentLetterController::class, 'generateExperienceLetter']);
    Route::post('employees/{employee}/employment-letter', [EmploymentLetterController::class, 'generateEmploymentLetter']);
    // Assignment Letter (Surat Tugas) dengan approval
    Route::get('assignment-letters', [AssignmentLetterController::class, 'index']);
    Route::post('assignment-letters', [AssignmentLetterController::class, 'store']);
    Route::get('assignment-letters/{id}', [AssignmentLetterController::class, 'show']);
    Route::match(['post', 'put'], 'assignment-letters/{id}/approve', [AssignmentLetterController::class, 'approve']);
    Route::match(['post', 'put'], 'assignment-letters/{id}/reject', [AssignmentLetterController::class, 'reject']);
    Route::get('assignment-letters/{id}/pdf', [AssignmentLetterController::class, 'generatePdf']);

    // Promotions
    Route::get('promotions', [PromotionController::class, 'index']);
    Route::post('promotions', [PromotionController::class, 'store']);
    Route::post('promotions/{id}/approve', [PromotionController::class, 'approve']);
    Route::post('promotions/{id}/reject', [PromotionController::class, 'reject']);
    Route::delete('promotions/{id}', [PromotionController::class, 'destroy']);

    Route::middleware('role:*')->group(function () {
        // Backward-compatible single-endpoint approval (accepts JSON { id })
        Route::match(['get', 'post', 'put'], '/overtime/approval', [OvertimeController::class, 'approveByBody']);
        Route::get('/overtime/requests', [OvertimeController::class, 'index']);
        Route::get('/overtime/requests/pending', [OvertimeController::class, 'pending']);
        Route::put('/overtime/requests/{id}/approve', [OvertimeController::class, 'approve'])->whereNumber('id');
        Route::put('/overtime/requests/{id}/reject', [OvertimeController::class, 'reject'])->whereNumber('id');
        Route::get('/overtime/evidences/request/{id}', [OvertimeController::class, 'overtimeEvidences'])->whereNumber('id');
        Route::put('/overtime/evidences/{id}/approve', [OvertimeController::class, 'approveEvidence'])->whereNumber('id');
        Route::put('/overtime/evidences/{id}/reject', [OvertimeController::class, 'rejectEvidence'])->whereNumber('id');
        Route::post('promotions/{id}/report/approve', [PromotionController::class, 'approveReport']);
        Route::post('promotions/{id}/report/reject', [PromotionController::class, 'rejectReport']);
    });

    // Pesangon PP 35/2021
    Route::get('employees/{employee}/severance/calculate', [SeveranceController::class, 'calculate']);
    Route::get('employees/{employee}/severance/export', [SeveranceController::class, 'exportExcel']);

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | USER PROFILE
    |--------------------------------------------------------------------------
    */
    Route::apiResource('profiles', UserProfileController::class);

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE SELF-SERVICE (ESS)
    |--------------------------------------------------------------------------
    | All routes that apply to the currently authenticated user's employee file
    */
    Route::prefix('my')->group(function () {
        // Profile
        Route::get('/profile', [UserProfileController::class, 'me']);
        // KPI (Legacy)
        Route::get('/kpi', [KpiController::class, 'myKpi']);
        Route::post('/kpi/{id}/accept', [KpiController::class, 'accept']);
        Route::put('/kpi/{id}/progress', [KpiController::class, 'updateProgress']);
        Route::post('/kpi/{id}/submit', [KpiController::class, 'submit']);

        // KPI Periods (ESS)
        Route::get('/kpi-periods', [KpiPeriodController::class, 'myKpiPeriods']);
        Route::put('/kpi-periods/{id}/items', [KpiPeriodController::class, 'myUpdateItems']);
        Route::post('/kpi-periods/{id}/submit', [KpiPeriodController::class, 'mySubmit']);

        // Reimbursements
        Route::get('/reimbursements', [ReimbursementController::class, 'myReimbursements']);
        Route::post('/reimbursements', [ReimbursementController::class, 'createMyReimbursement']);
        Route::put('/reimbursements/{id}', [ReimbursementController::class, 'update']);
        Route::post('/reimbursements/{id}/submit', [ReimbursementController::class, 'submit']);

        // Payroll
        Route::get('/payroll', [PayrollController::class, 'myPayroll']);
        Route::get('/payroll/{id}/slip', [PayrollController::class, 'myPayrollSlip']);
        Route::get('/payroll/{id}/export', [PayrollController::class, 'exportSlipCsv']);
        Route::get('/payroll/{id}/export-pdf', [PayrollController::class, 'exportSlipPdf']);

        // Training and competencies
        Route::get('/trainings', [TrainingController::class, 'myTrainings']);
        Route::get('/trainings/available', [TrainingController::class, 'availableTrainings']);
        Route::post('/trainings/{id}/enroll', [TrainingController::class, 'selfEnroll']);
        Route::get('/competencies', [CompetencyController::class, 'myCompetencies']);
        Route::get('/assets', [AssetController::class, 'myAssets']);
        Route::put('/assets/return/{assignmentId}', [AssetController::class, 'returnAssetByEmployee']);
        Route::get('/benefits', [BenefitController::class, 'myBenefits']);
        Route::get('/performance-reviews', [PerformanceReviewController::class, 'myReviews']);
        Route::get('/documents', [EmployeeDocumentController::class, 'myDocuments']);
        Route::post('/documents', [EmployeeDocumentController::class, 'store']);
        // Overtime requests (ESS)
        Route::get('/overtime', [OvertimeController::class, 'myOvertimeRequests']);
        Route::put('/overtime/{id}/reason', [OvertimeController::class, 'addReason']);
        Route::post('/overtime/{id}/evidence', [OvertimeController::class, 'uploadEvidence']);
        Route::get('/overtime/{id}/evidences', [OvertimeController::class, 'myOvertimeEvidences']);

        Route::post('/promotions/{id}/report/submit', [PromotionController::class, 'submitReport']);

        // Promotions (ESS)
        Route::get('/promotions', [PromotionController::class, 'myPromotions']);
    });

    Route::prefix('leaves')->group(function () {
        // ESS Leave Management
        Route::get('/my', [LeaveController::class, 'myLeaves']);
        Route::get('/balance', [LeaveController::class, 'balance']);
        // Owner resubmit after the leave was returned for revision
        Route::put('/{id}/resubmit', [LeaveController::class, 'resubmit']);
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::get('/{id}', [NotificationController::class, 'show']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    Route::middleware('role:*')->group(function () {
        Route::prefix('organization')->group(function () {
            Route::get('/directory', [OrgStructureController::class, 'directory']);
            Route::get('/summary', [OrgStructureController::class, 'summary']);
            Route::get('/chart', [OrgStructureController::class, 'orgChart']);
            Route::get('/team/{managerUserId}', [OrgStructureController::class, 'teamMembers']);
            Route::get('/master-data', [OrgStructureController::class, 'masterData']);
        });
    });

// Approval Flows - admin/HR/super_admin can manage
Route::middleware('role:*')->prefix('approval-flows')->group(function () {
    Route::get('/', [ApprovalFlowController::class, 'index']);
    Route::post('/', [ApprovalFlowController::class, 'store']);
    Route::get('/{id}', [ApprovalFlowController::class, 'show']);
    Route::put('/{id}', [ApprovalFlowController::class, 'update']);
    Route::delete('/{id}', [ApprovalFlowController::class, 'destroy']);
});

// Approval History - authenticated users can view history
Route::middleware('auth:sanctum')->prefix('approval-history')->group(function () {
    Route::get('/{module}/{moduleId}', [ApprovalFlowController::class, 'history']);
});

    Route::middleware('role:*')->prefix('compliance')->group(function () {
        Route::get('/overview', [ComplianceController::class, 'overview']);
        Route::get('/audit-summary', [ComplianceController::class, 'auditSummary']);
        Route::get('/expiring-documents', [ComplianceController::class, 'expiringDocuments']);
    });

    Route::prefix('attendance')->group(function () {
        // ESS Attendance Management
        Route::get('/locations', [LocationController::class, 'activeLocations']);
        Route::post('/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/check-out', [AttendanceController::class, 'checkOut']);
        Route::post('/qr/generate', [QrAttendanceController::class, 'generate']);
        Route::post('/qr/validate', [QrAttendanceController::class, 'validateToken']);
        Route::post('/qr/check-in', [QrAttendanceController::class, 'checkIn']);
        Route::post('/qr/check-out', [QrAttendanceController::class, 'checkOut']);
        Route::get('/history', [AttendanceController::class, 'history']);
        Route::get('/today', [AttendanceController::class, 'today']);
        Route::get('/intelligence', [AttendanceController::class, 'intelligence']);
        Route::get('/overtime', [AttendanceController::class, 'overtime']);
        // Backward-compatible reporting route: /api/attendance/reports
        Route::get('/reports', [ReportingController::class, 'attendanceAnalytics']);

        Route::middleware('role:*')->group(function () {
            Route::get('/all', [AttendanceController::class, 'all']);
            Route::get('/{id}', [AttendanceController::class, 'show'])->whereNumber('id');
            Route::delete('/{id}', [AttendanceController::class, 'destroy'])->whereNumber('id');
        });
    });

    Route::middleware('role:*')->prefix('patrol')->group(function () {
        Route::get('/checkpoints', [SecurityPatrolController::class, 'checkpoints']);
        Route::post('/checkpoints', [SecurityPatrolController::class, 'storeCheckpoint']);
        Route::put('/checkpoints/{id}', [SecurityPatrolController::class, 'updateCheckpoint'])->whereNumber('id');
        Route::delete('/checkpoints/{id}', [SecurityPatrolController::class, 'destroyCheckpoint'])->whereNumber('id');
        Route::post('/checkpoints/{id}/regenerate-qr', [SecurityPatrolController::class, 'regenerateQr'])->whereNumber('id');
        Route::post('/scan', [SecurityPatrolController::class, 'scan']);
        Route::get('/scans', [SecurityPatrolController::class, 'scans']);
    });

    // MANAGER / HR / ADMIN (Role based grouped endpoints)
    Route::middleware('role:*')->group(function () {

        // APPROVALS FOR MANAGERS / HR (LEAVES)
        Route::prefix('leaves')->group(function () {
            Route::get('/pending', [LeaveController::class, 'pending']);
            Route::put('/{id}/approve', [LeaveController::class, 'approve']);
            Route::put('/{id}/reject', [LeaveController::class, 'reject']);
            Route::put('/{id}/return', [LeaveController::class, 'returnForRevision']);
        });

        // OVERTIME APPROVAL
        Route::prefix('overtime/requests')->group(function () {
            Route::get('/', [OvertimeController::class, 'index']);
            Route::get('/pending', [OvertimeController::class, 'pending']);
            Route::put('/{id}/approve', [OvertimeController::class, 'approve']);
            Route::put('/{id}/reject', [OvertimeController::class, 'reject']);
        });

        // OVERTIME EVIDENCES
        Route::prefix('overtime/evidences')->group(function () {
            Route::get('/request/{id}', [OvertimeController::class, 'overtimeEvidences']);
            Route::put('/{id}/approve', [OvertimeController::class, 'approveEvidence']);
            Route::put('/{id}/reject', [OvertimeController::class, 'rejectEvidence']);
        });

        // KPI MANAGEMENT
        Route::prefix('kpis')->group(function () {
            Route::get('/', [KpiController::class, 'index']);
            Route::post('/', [KpiController::class, 'store']);
            Route::get('/employee/{employee_id}', [KpiController::class, 'byEmployee']);
            Route::get('/{id}', [KpiController::class, 'show']);
            Route::put('/{id}', [KpiController::class, 'update']);
            Route::delete('/{id}', [KpiController::class, 'destroy']);
            Route::put('/{id}/approve', [KpiController::class, 'approve']);
        });

        // KPI PERIOD MANAGEMENT (Period-based KPI)
        Route::prefix('kpi-periods')->group(function () {
            Route::get('/', [KpiPeriodController::class, 'index']);
            Route::post('/', [KpiPeriodController::class, 'store']);
            Route::get('/{id}', [KpiPeriodController::class, 'show']);
            Route::put('/{id}/items', [KpiPeriodController::class, 'updateItems']);
            Route::put('/{id}/approve', [KpiPeriodController::class, 'approve']);
            Route::delete('/{id}', [KpiPeriodController::class, 'destroy']);
        });

        // REIMBURSEMENT MANAGEMENT
        Route::prefix('reimbursements')->group(function () {
                Route::get('/', [ReimbursementController::class, 'index']);
            Route::post('/', [ReimbursementController::class, 'store']);
            Route::get('/pending', [ReimbursementController::class, 'pending']);
            Route::get('/statistics', [ReimbursementController::class, 'statistics']);
            Route::get('/employee/{employee_id}', [ReimbursementController::class, 'byEmployee']);
            Route::get('/{id}', [ReimbursementController::class, 'show']);
            Route::put('/{id}', [ReimbursementController::class, 'update']);
            Route::delete('/{id}', [ReimbursementController::class, 'destroy']);
            Route::put('/{id}/approve', [ReimbursementController::class, 'approve']);
            Route::put('/{id}/reject', [ReimbursementController::class, 'reject']);
            Route::put('/{id}/mark-paid', [ReimbursementController::class, 'markAsPaid']);
        });

        // PEOPLE INSIGHTS DASHBOARD
        Route::get('/insights/people', [PeopleInsightController::class, 'dashboard']);
        Route::get('/insights/people/detailed', [PeopleInsightController::class, 'detailedDashboard']);
        Route::get('/insights/people/trends', [PeopleInsightController::class, 'trends']);
        Route::get('/insights/people/team-health', [PeopleInsightController::class, 'teamHealth']);
        Route::get('/insights/people/employee/{userId}', [PeopleInsightController::class, 'employeeRiskDetail']);

        Route::prefix('attendance')->group(function () {
            Route::get('/employee/{userId}/intelligence', [AttendanceController::class, 'employeeIntelligence']);
        });

        Route::prefix('training')->group(function () {
            Route::get('/programs', [TrainingController::class, 'index']);
            Route::post('/programs', [TrainingController::class, 'store']);
            Route::get('/programs/{id}', [TrainingController::class, 'show']);
            Route::put('/programs/{id}', [TrainingController::class, 'update']);
            Route::delete('/programs/{id}', [TrainingController::class, 'destroy']);
            Route::post('/programs/{id}/enroll', [TrainingController::class, 'enroll']);
            Route::get('/enrollments', [TrainingController::class, 'enrollmentsIndex']);
            Route::put('/enrollments/{id}/complete', [TrainingController::class, 'complete']);
            Route::put('/enrollments/{id}/progress', [TrainingController::class, 'updateProgress']);
            Route::get('/enrollments/{id}/progress-history', [TrainingController::class, 'progressHistory']);
            Route::put('/enrollments/{id}/approve', [TrainingController::class, 'approveEnrollment']);
            Route::put('/enrollments/{id}/reject', [TrainingController::class, 'rejectEnrollment']);
        });

        Route::prefix('competencies')->group(function () {
            Route::get('/', [CompetencyController::class, 'index']);
            Route::post('/', [CompetencyController::class, 'store']);
            Route::get('/{id}', [CompetencyController::class, 'show']);
            Route::put('/{id}', [CompetencyController::class, 'update']);
            Route::delete('/{id}', [CompetencyController::class, 'destroy']);
            Route::post('/{id}/assign', [CompetencyController::class, 'assignToEmployee']);
            Route::post('/assignment/{id}/assess', [CompetencyController::class, 'assessCompetency']);
            Route::get('/employee/{employeeId}', [CompetencyController::class, 'employeeCompetencies']);
        });

        Route::prefix('assets')->group(function () {
            Route::get('/', [AssetController::class, 'index']);
            Route::post('/', [AssetController::class, 'store']);
            Route::get('/assignments', [AssetController::class, 'assignments']);
            Route::get('/{id}', [AssetController::class, 'show']);
            Route::put('/{id}', [AssetController::class, 'update']);
            Route::delete('/{id}', [AssetController::class, 'destroy']);
            Route::post('/{id}/assign', [AssetController::class, 'assign']);
            Route::put('/assignments/{assignmentId}/return', [AssetController::class, 'returnAsset']);
            Route::put('/assignments/{assignmentId}/approve', [AssetController::class, 'approveAssignment']);
            Route::put('/assignments/{assignmentId}/reject', [AssetController::class, 'rejectAssignment']);
        });

        Route::prefix('documents')->group(function () {
            Route::get('/', [EmployeeDocumentController::class, 'index']);
            Route::post('/', [EmployeeDocumentController::class, 'store']);
            Route::get('/expiring', [EmployeeDocumentController::class, 'expiring']);
            Route::get('/contracts', [EmployeeDocumentController::class, 'contracts']);
            Route::get('/{id}', [EmployeeDocumentController::class, 'show']);
            Route::put('/{id}', [EmployeeDocumentController::class, 'update']);
            Route::delete('/{id}', [EmployeeDocumentController::class, 'destroy']);
            Route::put('/{id}/review', [EmployeeDocumentController::class, 'review']);
            Route::put('/{id}/approve', [EmployeeDocumentController::class, 'approveDocument']);
            Route::put('/{id}/reject', [EmployeeDocumentController::class, 'rejectDocument']);
        });

        Route::prefix('benefits')->group(function () {
            Route::get('/', [BenefitController::class, 'index']);
            Route::post('/', [BenefitController::class, 'store']);
            Route::get('/employee/{employeeId}', [BenefitController::class, 'employeeBenefits']);
            Route::get('/{id}', [BenefitController::class, 'show']);
            Route::put('/{id}', [BenefitController::class, 'update']);
            Route::delete('/{id}', [BenefitController::class, 'destroy']);
            Route::post('/{id}/assign', [BenefitController::class, 'assignToEmployee']);
            Route::put('/assignments/{assignmentId}/approve', [BenefitController::class, 'approveBenefitAssignment']);
            Route::put('/assignments/{assignmentId}/reject', [BenefitController::class, 'rejectBenefitAssignment']);
        });

        Route::prefix('performance')->group(function () {
            Route::get('/summary', [PerformanceReviewController::class, 'summary']);

            Route::prefix('reviews')->group(function () {
                Route::get('/', [PerformanceReviewController::class, 'reviewsIndex']);
                Route::post('/', [PerformanceReviewController::class, 'reviewsStore']);
                Route::get('/{id}', [PerformanceReviewController::class, 'reviewsShow']);
                Route::put('/{id}', [PerformanceReviewController::class, 'reviewsUpdate']);
                Route::put('/{id}/submit', [PerformanceReviewController::class, 'submit']);
                Route::put('/{id}/review', [PerformanceReviewController::class, 'review']);
                Route::put('/{id}/approve', [PerformanceReviewController::class, 'approve']);
            });

        });

        Route::prefix('career')->group(function () {
            Route::get('/idps', [CareerDevelopmentController::class, 'idpIndex']);
            Route::post('/idps', [CareerDevelopmentController::class, 'idpStore']);
            Route::put('/idps/{id}', [CareerDevelopmentController::class, 'idpUpdate']);
            Route::get('/succession', [CareerDevelopmentController::class, 'successionMatrix']);
            Route::post('/succession', [CareerDevelopmentController::class, 'successionStore']);
        });

        Route::prefix('engagement')->group(function () {
            Route::get('/surveys', [EngagementController::class, 'surveyIndex']);
            Route::post('/surveys', [EngagementController::class, 'surveyStore']);
            Route::post('/surveys/{id}/responses', [EngagementController::class, 'submitResponse']);
            Route::get('/surveys/{id}/analytics', [EngagementController::class, 'analytics']);
        });


        Route::prefix('workforce')->group(function () {
            Route::get('/compliance/stats', [WorkforceComplianceController::class, 'stats']);
            Route::get('/compliance/documents', [WorkforceComplianceController::class, 'documents']);
            Route::get('/holidays', [WorkforcePolicyController::class, 'holidayCalendarIndex']);
            Route::post('/holidays', [WorkforcePolicyController::class, 'holidayCalendarStore']);
            Route::get('/holidays/{id}', [WorkforcePolicyController::class, 'holidayCalendarShow']);
            Route::put('/holidays/{id}', [WorkforcePolicyController::class, 'holidayCalendarUpdate']);
            Route::delete('/holidays/{id}', [WorkforcePolicyController::class, 'holidayCalendarDestroy']);
            Route::put('/leave-policies/{id}/advanced', [WorkforcePolicyController::class, 'advancedLeavePolicyUpdate']);
            Route::get('/shift-swaps', [WorkforcePolicyController::class, 'shiftSwapIndex']);
            Route::post('/shift-swaps', [WorkforcePolicyController::class, 'shiftSwapStore']);
            Route::put('/shift-swaps/{id}', [WorkforcePolicyController::class, 'shiftSwapApprove']);
            Route::put('/shift-swaps/{id}/approve', [WorkforcePolicyController::class, 'shiftSwapApproveAction']);
            Route::put('/shift-swaps/{id}/reject', [WorkforcePolicyController::class, 'shiftSwapRejectAction']);
            Route::get('/overtime-rules', [WorkforcePolicyController::class, 'overtimeRuleIndex']);
            Route::post('/overtime-rules', [WorkforcePolicyController::class, 'overtimeRuleStore']);
            Route::get('/overtime-rules/{id}', [WorkforcePolicyController::class, 'overtimeRuleShow']);
            Route::put('/overtime-rules/{id}', [WorkforcePolicyController::class, 'overtimeRuleUpdate']);
            Route::delete('/overtime-rules/{id}', [WorkforcePolicyController::class, 'overtimeRuleDestroy']);
        });

        Route::prefix('enterprise')->group(function () {
            Route::put('/compensation/employee/{employeeId}', [EnterpriseOpsController::class, 'upsertCompProfile']);
            Route::post('/compensation/retro-adjustments', [EnterpriseOpsController::class, 'addRetroAdjustment']);
            Route::post('/compensation/bank-export-preview', [EnterpriseOpsController::class, 'bankExportPreview']);
            Route::post('/notifications/templates', [EnterpriseOpsController::class, 'notificationTemplateStore']);
            Route::post('/notifications/rules', [EnterpriseOpsController::class, 'notificationRuleStore']);
            Route::post('/notifications/schedules', [EnterpriseOpsController::class, 'scheduleNotification']);
            Route::get('/compliance/retention-policies', [EnterpriseOpsController::class, 'getRetentionPolicies']);
            Route::delete('/compliance/retention-policies/{module}', [EnterpriseOpsController::class, 'deactivateRetentionPolicy']);
            Route::get('/compliance/privacy-requests', [EnterpriseOpsController::class, 'getPrivacyRequests']);
            Route::post('/compliance/retention-policies', [EnterpriseOpsController::class, 'retentionPolicyStore']);
            Route::post('/compliance/privacy-requests', [EnterpriseOpsController::class, 'privacyRequestStore']);
        });

        // REPORTING & ANALYTICS
        Route::prefix('reports')->group(function () {
            Route::get('/dashboard-summary', [ReportingController::class, 'dashboardSummary']);
            Route::get('/attendance', [ReportingController::class, 'attendanceAnalytics']);
            Route::get('/leave', [ReportingController::class, 'leaveAnalytics']);
            Route::get('/payroll', [ReportingController::class, 'payrollAnalytics']);
            Route::get('/competency', [ReportingController::class, 'competencyAnalytics']);
            Route::get('/employee-lifecycle', [ReportingController::class, 'employeeLifecycleAnalytics']);
            Route::get('/assets', [ReportingController::class, 'assetAnalytics']);
        });

        Route::prefix('dashboard')->group(function () {
            Route::get('/widgets', [DashboardConfigController::class, 'widgets']);
            Route::get('/configs', [DashboardConfigController::class, 'index']);
            Route::post('/configs', [DashboardConfigController::class, 'store']);
            Route::put('/configs/{id}', [DashboardConfigController::class, 'update'])->whereNumber('id');
            Route::delete('/configs/{id}', [DashboardConfigController::class, 'destroy'])->whereNumber('id');
        });

    });

    // We keep these base leaves endpoints for standard access
    Route::prefix('leaves')->group(function () {
        Route::get('/', [LeaveController::class, 'index']);
        Route::post('/', [LeaveController::class, 'store']);
        Route::get('/calendar', [LeaveController::class, 'calendar']);
        Route::get('/{id}', [LeaveController::class, 'show']);
        Route::put('/{id}', [LeaveController::class, 'update']);
        Route::delete('/{id}', [LeaveController::class, 'destroy']);
    });

    // 📖 Leave Reference Data - accessible to all authenticated users for ESS leave creation
    Route::prefix('leave-types')->group(function () {
        Route::get('/', [LeaveTypeController::class, 'index']);
        Route::get('/{id}', [LeaveTypeController::class, 'show']);
        Route::post('/', [LeaveTypeController::class, 'store']);
        Route::put('/{id}', [LeaveTypeController::class, 'update']);
        Route::delete('/{id}', [LeaveTypeController::class, 'destroy']);
    });

    Route::prefix('leave-policies')->group(function () {
        Route::get('/', [LeavePolicyController::class, 'index']);
        Route::get('/{id}', [LeavePolicyController::class, 'show']);
        Route::post('/', [LeavePolicyController::class, 'store']);
        Route::put('/{id}', [LeavePolicyController::class, 'update']);
        Route::delete('/{id}', [LeavePolicyController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | HR / MANAGER / ADMIN ROUTES
    |--------------------------------------------------------------------------
    */

    // ATTENDANCE (Admin/Manager/HR)
    Route::middleware('role:*')->prefix('attendance')->group(function () {
        Route::get('/all', [AttendanceController::class, 'all']);
        Route::get('/{id}', [AttendanceController::class, 'show']);
        Route::delete('/{id}', [AttendanceController::class, 'destroy']);
    });

    // EMPLOYEE MANAGEMENT (Admin/Manager/HR)
    Route::middleware('role:*')->group(function () {
        // GET list/detail are provided by the temporary legacy SQL Server
        // bridge above; keep create/update/delete protected by RBAC.
        Route::apiResource('employees', EmployeeController::class)
            ->except(['index', 'show']);

        Route::prefix('employees')->group(function () {
            Route::put('/{id}/onboarding/start', [EmployeeController::class, 'startOnboarding']);
            Route::put('/{id}/onboarding/complete', [EmployeeController::class, 'completeOnboarding']);
            Route::put('/{id}/offboarding/start', [EmployeeController::class, 'offboard']);
            Route::put('/{id}/offboarding/complete', [EmployeeController::class, 'completeOffboarding']);
        });
    });

    // PAYROLL (HR / Admin / Manager)
    Route::middleware('role:*')->group(function () {
        Route::prefix('payroll')->group(function () {
            Route::get('/', [PayrollController::class, 'index']);
            Route::post('/', [PayrollController::class, 'store']);
            Route::post('/generate/monthly', [PayrollController::class, 'generateMonthly']);
            Route::post('/bulk-pay', [PayrollController::class, 'bulkPay']);          // NEW
            Route::get('/export/bca-klikpay', [PayrollController::class, 'exportBcaKlikPay']);
            Route::get('/export/summary', [PayrollController::class, 'exportPayrollSummaryCsv']);
            Route::get('/{id}', [PayrollController::class, 'show']);
            Route::get('/{id}/slip', [PayrollController::class, 'slip']);
            Route::get('/{id}/export', [PayrollController::class, 'exportSlipCsv']);
            Route::get('/{id}/export-pdf', [PayrollController::class, 'exportSlipPdf']);
            Route::put('/{id}', [PayrollController::class, 'update']);
            Route::delete('/{id}', [PayrollController::class, 'destroy']);
            Route::post('/{id}/approve', [PayrollController::class, 'approve']);         // backward-compat
            Route::post('/{id}/manager-approve', [PayrollController::class, 'managerApprove']); // NEW
            Route::post('/{id}/hr-approve', [PayrollController::class, 'hrApprove']);   // NEW
            Route::post('/{id}/reject', [PayrollController::class, 'reject']);           // NEW
            Route::post('/{id}/pay', [PayrollController::class, 'pay']);
        });


        Route::prefix('payroll-details')->group(function () {
            Route::get('/{payroll_id}', [PayrollDetailController::class, 'index']);
            Route::post('/', [PayrollDetailController::class, 'store']);
            Route::put('/{id}', [PayrollDetailController::class, 'update']);
            Route::delete('/{id}', [PayrollDetailController::class, 'destroy']);
        });
    });

    // MASTER DATA & SYSTEM SETTINGS (Super Admin & Admin/HR/Manager)
    Route::middleware('role:*')->group(function () {
        Route::prefix('admin/notifications')->group(function () {
            Route::get('/summary', [NotificationController::class, 'summary']);
            Route::post('/broadcast', [NotificationController::class, 'broadcast']);
        });

        Route::prefix('admin/email-notifications')->group(function () {
            Route::post('/', [NotificationController::class, 'sendEmailNotification']);
            Route::get('/logs', [NotificationController::class, 'getEmailLogs']);
            Route::post('/{id}/retry', [NotificationController::class, 'retryEmailNotification']);
        });

        Route::prefix('admin/email-templates')->group(function () {
            Route::get('/', [NotificationController::class, 'emailTemplateIndex']);
            Route::post('/', [NotificationController::class, 'emailTemplateStore']);
            Route::put('/{id}', [NotificationController::class, 'emailTemplateUpdate']);
            Route::delete('/{id}', [NotificationController::class, 'emailTemplateDestroy']);
            Route::post('/{id}/preview', [NotificationController::class, 'emailTemplatePreview']);
        });

        // Notification Settings
        Route::prefix('notification-settings')->group(function () {
            Route::get('/', [NotificationSettingController::class, 'index']);
            Route::put('/{category}', [NotificationSettingController::class, 'update']);
        });
    });

    Route::middleware('role:*')->group(function () {
        Route::prefix('admin/notifications')->group(function () {
            Route::post('/', [NotificationController::class, 'store']);
        });
    });

    // MENU PERMISSIONS — accessible by PERMISSION via explicit map (role.assign_permission)
    Route::prefix('admin/menus')->group(function () {
        Route::get('/', [\App\Modules\Administration\Controllers\MenuController::class, 'definitions']);
        Route::post('/assign-role', [\App\Modules\Administration\Controllers\MenuController::class, 'assignRole']);
        Route::delete('/{menuKey}/roles/{roleId}', [\App\Modules\Administration\Controllers\MenuController::class, 'removeRole']);
    });

    // USER MENUS (accessible to all authenticated users)
    Route::get('/user/menus', [\App\Modules\Administration\Controllers\MenuController::class, 'userMenus']);
    Route::get('/user/menu-tree', [\App\Modules\Administration\Controllers\MenuController::class, 'userMenuTree']);

    Route::middleware('role:*')->group(function () {
        Route::apiResource('locations', LocationController::class);

        Route::apiResource('departments', DepartmentController::class);

        Route::apiResource('positions', PositionController::class);

        Route::get('/company/context', [CompanyController::class, 'context']);
        Route::get('/company', [CompanyController::class, 'show']);
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::put('/companies/{id}', [CompanyController::class, 'update'])->whereNumber('id');
        Route::post('/companies/{id}/deactivate', [CompanyController::class, 'deactivate'])->whereNumber('id');
        Route::get('/companies/{id}/users', [CompanyController::class, 'listUsers'])->whereNumber('id');
        Route::post('/companies/{id}/users', [CompanyController::class, 'assignUser'])->whereNumber('id');
        Route::delete('/companies/{id}/users/{userId}', [CompanyController::class, 'removeUser'])->whereNumber('id')->whereNumber('userId');
        Route::post('/company', [CompanyController::class, 'store']);
        Route::put('/company/{id}', [CompanyController::class, 'update']);
        Route::post('/company/{id}/logo', [CompanyController::class, 'uploadLogo']);
        Route::delete('/company/{id}/logo', [CompanyController::class, 'deleteLogo']);

        Route::apiResource('work-schedules', WorkScheduleController::class);


        Route::prefix('biometric')->group(function () {
            Route::get('/devices', [BiometricIntegrationController::class, 'deviceIndex']);
            Route::post('/devices', [BiometricIntegrationController::class, 'deviceStore']);
            Route::post('/sync-attendance', [BiometricIntegrationController::class, 'syncAttendance']);
        });
    });

    // Admin RBAC Routes — tanpa hardcode role name, controller & explicit map handle permission
    Route::prefix('admin')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/{id}', [AuditLogController::class, 'show']);

        // RBAC Management
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/{id}', [RoleController::class, 'show']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
        Route::post('/roles/{id}/assign-permission', [RoleController::class, 'assignPermission']);
        Route::delete('/roles/{id}/remove-permission/{permissionId}', [RoleController::class, 'removePermission']);
        Route::get('/roles/{id}/can-modify', [RoleController::class, 'canModify']);
        Route::get('/roles/{id}/can-assign', [RoleController::class, 'canAssign']);
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/permissions/{id}', [PermissionController::class, 'show']);
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::post('/users/{id}/assign-role', [UserController::class, 'assignRole']);
        Route::delete('/users/{id}/remove-role/{roleId}', [UserController::class, 'removeRole']);

        // Data Import
        Route::prefix('import')->group(function () {
            Route::post('/users', [DataImportController::class, 'importUsers']);
            Route::post('/employees', [DataImportController::class, 'importEmployees']);
            Route::get('/template', [DataImportController::class, 'getImportTemplate']);
        });
    });

});

// Helper for storage link on restricted hosting
Route::get('/setup-storage', function () {
    $link = public_path('storage');
    
    // 1. Hapus jika sudah ada (link rusak atau folder)
    if (file_exists($link) || is_link($link)) {
        if (windows_os()) {
            if (is_dir($link) && !is_link($link)) {
                // Di windows, folder tidak bisa di-unlink
                \Illuminate\Support\Facades\File::deleteDirectory($link);
            } else {
                @unlink($link);
            }
        } else {
            if (is_dir($link) && !is_link($link)) {
                \Illuminate\Support\Facades\File::deleteDirectory($link);
            } else {
                @unlink($link);
            }
        }
    }

    try {
        // 2. Gunakan RELATIVE path (Lebih aman untuk Hostinger)
        // Di Hostinger: public_html/storage -> ../storage/app/public
        if (symlink('../storage/app/public', $link)) {
            return response()->json(['message' => 'Relative Symlink created successfully!']);
        }
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Symlink failed. Fallback route in web.php is active.',
            'error' => $e->getMessage()
        ], 500);
    }
});
