<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('route_prefix')->index();
            $table->string('permission_name')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['route_prefix', 'permission_name']);
        });

        $now = now();
        $map = [
            '/api/admin/roles' => ['role.view', 'role.create', 'role.update', 'role.delete', 'role.assign_permission'],
            '/admin/roles' => ['role.view', 'role.create', 'role.update', 'role.delete', 'role.assign_permission'],
            '/api/admin/permissions' => ['permission.view'],
            '/admin/permissions' => ['permission.view'],
            '/api/admin/users' => ['user.view', 'user.create', 'user.update', 'user.delete', 'user.assign_role', 'admin.user.view', 'admin.user.create', 'admin.user.update', 'admin.user.delete', 'admin.user.assign_role'],
            '/admin/users' => ['user.view', 'user.create', 'user.update', 'user.delete', 'user.assign_role', 'admin.user.view', 'admin.user.create', 'admin.user.update', 'admin.user.delete', 'admin.user.assign_role'],
            '/api/admin/menus' => ['role.assign_permission', 'role.view'],
            '/admin/menus' => ['role.assign_permission', 'role.view'],
            '/api/admin/audit-logs' => ['audit.logs.view', 'admin.audit.view'],
            '/admin/audit-logs' => ['audit.logs.view', 'admin.audit.view'],
            '/api/admin/import' => ['admin.import.execute'],
            '/admin/import' => ['admin.import.execute'],
            '/api/admin/notifications' => ['admin.email.manage'],
            '/admin/notifications' => ['admin.email.manage'],
            '/api/admin/email-notifications' => ['admin.email.manage'],
            '/admin/email-notifications' => ['admin.email.manage'],
            '/api/admin/email-templates' => ['admin.email.manage'],
            '/admin/email-templates' => ['admin.email.manage'],
            '/api/locations' => ['location.view', 'location.create', 'location.update', 'location.delete'],
            '/locations' => ['location.view', 'location.create', 'location.update', 'location.delete'],
            '/api/departments' => ['department.view', 'department.create', 'department.update', 'department.delete'],
            '/departments' => ['department.view', 'department.create', 'department.update', 'department.delete'],
            '/api/positions' => ['position.view', 'position.create', 'position.update', 'position.delete'],
            '/positions' => ['position.view', 'position.create', 'position.update', 'position.delete'],
            '/api/company' => ['admin.company.view', 'admin.company.update'],
            '/company' => ['admin.company.view', 'admin.company.update'],
            '/api/companies' => ['company.view', 'company.view_all', 'company.create', 'company.update', 'company.deactivate', 'company.assign_user'],
            '/companies' => ['company.view', 'company.view_all', 'company.create', 'company.update', 'company.deactivate', 'company.assign_user'],
            '/api/work-schedules' => ['admin.schedule.manage'],
            '/work-schedules' => ['admin.schedule.manage'],
            '/api/employees' => ['employee.view', 'employee.create', 'employee.update', 'employee.delete', 'employee.onboard', 'employee.offboard'],
            '/employees' => ['employee.view', 'employee.create', 'employee.update', 'employee.delete', 'employee.onboard', 'employee.offboard'],
            '/api/biometric/devices' => ['biometric.devices.view'],
            '/biometric/devices' => ['biometric.devices.view'],
            '/api/biometric/sync-attendance' => ['biometric.attendance.sync'],
            '/biometric/sync-attendance' => ['biometric.attendance.sync'],
            '/api/payroll/reports' => ['payroll.reports.view'],
            '/payroll/reports' => ['payroll.reports.view'],
            '/api/payroll' => ['payroll.view', 'payroll.create', 'payroll.generate', 'payroll.approve', 'payroll.pay', 'payroll.export', 'payroll.reports.view'],
            '/payroll' => ['payroll.view', 'payroll.create', 'payroll.generate', 'payroll.approve', 'payroll.pay', 'payroll.export', 'payroll.reports.view'],
            '/api/reports' => ['reporting.dashboard', 'reporting.attendance', 'reporting.leave', 'reporting.payroll', 'reporting.competency', 'reporting.lifecycle', 'reporting.assets'],
            '/reports' => ['reporting.dashboard', 'reporting.attendance', 'reporting.leave', 'reporting.payroll', 'reporting.competency', 'reporting.lifecycle', 'reporting.assets'],
            '/api/dashboard' => ['dashboard.customize_self', 'dashboard.manage_default', 'dashboard.view_all_company'],
            '/dashboard' => ['dashboard.customize_self', 'dashboard.manage_default', 'dashboard.view_all_company'],
            '/api/leaves' => ['leave.view', 'leave.create', 'leave.update', 'leave.delete', 'leave.approve'],
            '/leaves' => ['leave.view', 'leave.create', 'leave.update', 'leave.delete', 'leave.approve'],
            '/api/leave-types' => ['leave.policy.manage'],
            '/leave-types' => ['leave.policy.manage'],
            '/api/leave-policies' => ['leave.policy.manage'],
            '/leave-policies' => ['leave.policy.manage'],
            '/api/approval-flows' => ['admin.approval_flow.manage'],
            '/approval-flows' => ['admin.approval_flow.manage'],
            '/api/kpis' => ['kpi.view', 'kpi.create', 'kpi.update', 'kpi.delete', 'kpi.approve'],
            '/kpis' => ['kpi.view', 'kpi.create', 'kpi.update', 'kpi.delete', 'kpi.approve'],
            '/api/kpi-periods' => ['kpi.view', 'kpi.create', 'kpi.update', 'kpi.delete', 'kpi.approve'],
            '/kpi-periods' => ['kpi.view', 'kpi.create', 'kpi.update', 'kpi.delete', 'kpi.approve'],
            '/api/overtime' => ['overtime.view', 'overtime.create', 'overtime.approve', 'overtime.manage'],
            '/overtime' => ['overtime.view', 'overtime.create', 'overtime.approve', 'overtime.manage'],
            '/api/documents' => ['document.view', 'document.create', 'document.update', 'document.delete', 'document.review'],
            '/documents' => ['document.view', 'document.create', 'document.update', 'document.delete', 'document.review'],
            '/api/assets' => ['asset.view', 'asset.create', 'asset.update', 'asset.delete', 'asset.assign'],
            '/assets' => ['asset.view', 'asset.create', 'asset.update', 'asset.delete', 'asset.assign'],
            '/api/competencies' => ['competency.view', 'competency.create', 'competency.update', 'competency.delete', 'competency.assign'],
            '/competencies' => ['competency.view', 'competency.create', 'competency.update', 'competency.delete', 'competency.assign'],
            '/api/organization' => ['organization.view', 'organization.directory', 'organization.chart', 'organization.team'],
            '/organization' => ['organization.view', 'organization.directory', 'organization.chart', 'organization.team'],
            '/api/benefits' => ['benefit.view', 'benefit.create', 'benefit.update', 'benefit.delete', 'benefit.assign'],
            '/benefits' => ['benefit.view', 'benefit.create', 'benefit.update', 'benefit.delete', 'benefit.assign'],
            '/api/trainings' => ['training.view', 'training.create', 'training.update', 'training.delete', 'training.enroll'],
            '/trainings' => ['training.view', 'training.create', 'training.update', 'training.delete', 'training.enroll'],
            '/api/training' => ['training.view', 'training.create', 'training.update', 'training.delete', 'training.enroll'],
            '/training' => ['training.view', 'training.create', 'training.update', 'training.delete', 'training.enroll'],
            '/api/performance' => ['performance.cycle.view', 'performance.cycle.create', 'performance.cycle.manage', 'performance.review.view', 'performance.review.create', 'performance.review.update', 'performance.review.submit', 'performance.review.approve'],
            '/performance' => ['performance.cycle.view', 'performance.cycle.create', 'performance.cycle.manage', 'performance.review.view', 'performance.review.create', 'performance.review.update', 'performance.review.submit', 'performance.review.approve'],
            '/api/reimbursements' => ['reimbursement.view', 'reimbursement.create', 'reimbursement.approve', 'reimbursement.pay'],
            '/reimbursements' => ['reimbursement.view', 'reimbursement.create', 'reimbursement.approve', 'reimbursement.pay'],
            '/api/promotions' => ['career.promotion.view', 'career.promotion.create', 'career.promotion.approve', 'career.promotion.delete'],
            '/promotions' => ['career.promotion.view', 'career.promotion.create', 'career.promotion.approve', 'career.promotion.delete'],
            '/api/career' => ['career.idp.view', 'career.idp.create', 'career.idp.update', 'career.succession.view', 'career.succession.manage', 'career.promotion.view', 'career.promotion.create', 'career.promotion.update', 'career.promotion.delete', 'career.promotion.approve'],
            '/career' => ['career.idp.view', 'career.idp.create', 'career.idp.update', 'career.succession.view', 'career.succession.manage', 'career.promotion.view', 'career.promotion.create', 'career.promotion.update', 'career.promotion.delete', 'career.promotion.approve'],
            '/api/engagement' => ['engagement.survey.view', 'engagement.survey.create', 'engagement.survey.respond', 'engagement.survey.analytics'],
            '/engagement' => ['engagement.survey.view', 'engagement.survey.create', 'engagement.survey.respond', 'engagement.survey.analytics'],
            '/api/attendance' => ['attendance.view_all', 'attendance.delete', 'attendance.check_in', 'attendance.check_out', 'attendance.qr.generate', 'attendance.qr.scan'],
            '/attendance' => ['attendance.view_all', 'attendance.delete', 'attendance.check_in', 'attendance.check_out', 'attendance.qr.generate', 'attendance.qr.scan'],
            '/api/patrol' => ['patrol.scan', 'patrol.view', 'patrol.manage', 'patrol.report'],
            '/patrol' => ['patrol.scan', 'patrol.view', 'patrol.manage', 'patrol.report'],
            '/api/compliance' => ['compliance.view', 'compliance.audit', 'compliance.documents'],
            '/compliance' => ['compliance.view', 'compliance.audit', 'compliance.documents'],
        ];

        $rows = [];
        foreach ($map as $prefix => $permissions) {
            foreach ($permissions as $permission) {
                $rows[] = [
                    'route_prefix' => $prefix,
                    'permission_name' => $permission,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('route_permissions')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('route_permissions');
    }
};
