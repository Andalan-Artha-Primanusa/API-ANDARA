<?php

namespace App\Constants;

/**
 * Permission Registry - Centralized permission & role management
 * Linked to actual API routes for easy maintenance & auditing
 * 
 * Super Admin can customize role-permission mappings via:
 * POST /admin/roles/{id}/assign-permission
 */
class Permissions
{
    // ==========================================
    // RESOURCE-BASED PERMISSIONS
    // ==========================================
    
    const EMPLOYEE = [
        'employee.view' => 'View employee list & details',
        'employee.create' => 'Create new employee',
        'employee.update' => 'Update employee profile',
        'employee.delete' => 'Delete employee',
        'employee.onboard' => 'Start onboarding process',
        'employee.offboard' => 'Start offboarding process',
    ];

    const LEAVE = [
        'leave.view' => 'View leave requests',
        'leave.create' => 'Create leave request',
        'leave.request' => 'Create leave request',
        'leave.update' => 'Update leave request',
        'leave.delete' => 'Delete leave request',
        'leave.approve' => 'Approve/reject leave (Manager/HR)',
        'leave.policy.manage' => 'Manage leave policies',
    ];

    const ATTENDANCE = [
        'attendance.view_all' => 'View all attendance records',
        'attendance.delete' => 'Delete attendance records',
        'attendance.check_in' => 'Employee check-in',
        'attendance.check_out' => 'Employee check-out',
        'attendance.view_own' => 'View own attendance history',
        'attendance.qr.generate' => 'Generate QR attendance token',
        'attendance.qr.scan' => 'Scan QR for attendance',
        'attendance.manual_adjust' => 'Manual attendance adjustment',
    ];

    const PATROL = [
        'patrol.scan' => 'Scan security patrol checkpoints',
        'patrol.view' => 'View security patrol checkpoints and scans',
        'patrol.manage' => 'Manage security patrol checkpoints',
        'patrol.report' => 'View security patrol reports',
    ];

    const PAYROLL = [
        'payroll.view' => 'View payroll records',
        'payroll.view_own' => 'View own payroll slips',
        'payroll.create' => 'Create payroll run',
        'payroll.generate' => 'Generate payroll batch',
        'payroll.process' => 'Generate payroll batch',
        'payroll.approve' => 'Approve payroll payment',
        'payroll.pay' => 'Process payroll payment',
        'payroll.export' => 'Export payroll data',
        'payroll.publish' => 'Export/publish payroll slips',
        'payroll.reports.view' => 'View payroll reports',
    ];

    const OVERTIME = [
        'overtime.view' => 'View overtime requests',
        'overtime.create' => 'Create overtime request',
        'overtime.request' => 'Create overtime request',
        'overtime.approve' => 'Approve overtime request (Manager/HR)',
        'overtime.manage' => 'Manage overtime records',
    ];

    const KPI = [
        'kpi.view' => 'View KPI records',
        'kpi.create' => 'Create KPI',
        'kpi.update' => 'Update KPI',
        'kpi.delete' => 'Delete KPI',
        'kpi.approve' => 'Approve KPI submission',
    ];

    const REIMBURSEMENT = [
        'reimbursement.view' => 'View reimbursement requests',
        'reimbursement.create' => 'Create reimbursement request',
        'reimbursement.request' => 'Create reimbursement request',
        'reimbursement.approve' => 'Approve reimbursement',
        'reimbursement.pay' => 'Mark reimbursement as paid',
    ];

    const TRAINING = [
        'training.view' => 'View training programs',
        'training.create' => 'Create training program',
        'training.update' => 'Update training program',
        'training.delete' => 'Delete training program',
        'training.manage' => 'Manage training end-to-end',
        'training.enroll' => 'Enroll employee in training',
    ];

    const COMPETENCY = [
        'competency.view' => 'View competencies',
        'competency.create' => 'Create competency',
        'competency.update' => 'Update competency',
        'competency.delete' => 'Delete competency',
        'competency.assign' => 'Assign competency to employee',
    ];

    const ASSET = [
        'asset.view' => 'View assets',
        'asset.create' => 'Create asset',
        'asset.update' => 'Update asset',
        'asset.delete' => 'Delete asset',
        'asset.assign' => 'Assign asset to employee',
    ];

    const DOCUMENT = [
        'document.view' => 'View employee documents',
        'document.create' => 'Create/upload document',
        'document.update' => 'Update document',
        'document.delete' => 'Delete document',
        'document.review' => 'Review/approve document',
    ];

    const ASSIGNMENT_LETTER = [
        'assignment_letter.view' => 'View assignment letters',
        'assignment_letter.create' => 'Create assignment letter',
        'assignment_letter.approve' => 'Approve/reject assignment letter',
        'assignment_letter.export' => 'Generate assignment letter PDF',
    ];

    const BENEFIT = [
        'benefit.view' => 'View benefits',
        'benefit.create' => 'Create benefit plan',
        'benefit.update' => 'Update benefit plan',
        'benefit.delete' => 'Delete benefit plan',
        'benefit.assign' => 'Assign benefit to employee',
    ];

    const PERFORMANCE = [
        'performance.cycle.view' => 'View performance cycles',
        'performance.cycle.create' => 'Create performance cycles',
        'performance.cycle.manage' => 'Manage performance cycles',
        'performance.review.view' => 'View performance reviews',
        'performance.review.create' => 'Create performance review',
        'performance.review.update' => 'Update performance review',
        'performance.review.submit' => 'Submit performance review',
        'performance.review.approve' => 'Approve performance review',
    ];

    const CAREER = [
        'career.idp.view' => 'View Individual Development Plans',
        'career.idp.create' => 'Create IDP',
        'career.idp.update' => 'Update IDP',
        'career.succession.view' => 'View succession planning',
        'career.succession.manage' => 'Manage succession planning',
        'career.promotion.view' => 'View promotion records',
        'career.promotion.create' => 'Create promotion request',
        'career.promotion.update' => 'Update promotion records',
        'career.promotion.delete' => 'Delete promotion records',
        'career.promotion.approve' => 'Approve promotion request',
    ];

    const ENGAGEMENT = [
        'engagement.survey.view' => 'View engagement surveys',
        'engagement.survey.create' => 'Create engagement survey',
        'engagement.survey.respond' => 'Respond to survey',
        'engagement.survey.analytics' => 'View survey analytics',
    ];

    const ORGANIZATION = [
        'organization.view' => 'View organization structure',
        'organization.directory' => 'Access employee directory',
        'organization.chart' => 'View organization chart',
        'organization.team' => 'View team members',
    ];

    const COMPLIANCE = [
        'compliance.view' => 'View compliance overview',
        'compliance.audit' => 'View audit trail',
        'compliance.documents' => 'View document compliance',
    ];

    const REPORTING = [
        'reporting.dashboard' => 'View analytics dashboard',
        'reporting.attendance' => 'View attendance analytics',
        'reporting.leave' => 'View leave analytics',
        'reporting.payroll' => 'View payroll analytics',
        'reporting.competency' => 'View competency analytics',
        'reporting.lifecycle' => 'View employee lifecycle analytics',
        'reporting.assets' => 'View asset analytics',
    ];

    const DASHBOARD = [
        'dashboard.customize_self' => 'Customize own dashboard',
        'dashboard.manage_default' => 'Manage default dashboards',
        'dashboard.view_all_company' => 'View all-company dashboard widgets',
    ];

    // ==========================================
    // SYSTEM ADMIN PERMISSIONS (Super Admin ONLY)
    // ==========================================

    const ADMIN_SYSTEM = [
        'user.view' => 'View users',
        'user.create' => 'Create users',
        'user.update' => 'Update users',
        'user.delete' => 'Delete users',
        'user.assign_role' => 'Assign roles to users',
        'role.view' => 'View roles',
        'role.create' => 'Create roles',
        'role.update' => 'Update roles',
        'role.delete' => 'Delete roles',
        'role.assign_permission' => 'Assign permissions to roles',
        'permission.view' => 'View permissions',
        'admin.user.view' => 'View all users',
        'admin.user.create' => 'Create users',
        'admin.user.update' => 'Update users',
        'admin.user.delete' => 'Delete users',
        'admin.user.assign_role' => 'Assign roles to users',
        'admin.role.view' => 'View roles',
        'admin.role.assign_permission' => 'Assign permissions to roles',
        'admin.permission.view' => 'View permissions',
        'admin.audit.view' => 'View audit logs',
        'admin.audit.delete' => 'Delete audit records',
        'audit.logs.view' => 'View audit logs',
    ];

    const ADMIN_SETTINGS = [
        'location.view' => 'View locations',
        'location.create' => 'Create locations',
        'location.update' => 'Update locations',
        'location.delete' => 'Delete locations',
        'department.view' => 'View departments',
        'department.create' => 'Create departments',
        'department.update' => 'Update departments',
        'department.delete' => 'Delete departments',
        'position.view' => 'View positions',
        'position.create' => 'Create positions',
        'position.update' => 'Update positions',
        'position.delete' => 'Delete positions',
        'profile.view_all' => 'View all profiles',
        'profile.update' => 'Update profiles',
        'profile.delete' => 'Delete profiles',
        'admin.email.manage' => 'Manage email notifications & templates',
        'admin.location.manage' => 'Manage locations',
        'admin.department.manage' => 'Manage departments',
        'admin.position.manage' => 'Manage positions',
        'admin.company.view' => 'View company data',
        'admin.company.update' => 'Update company data',
        'admin.approval_flow.manage' => 'Manage approval workflows',
        'company.view' => 'View companies',
        'company.create' => 'Create companies',
        'company.update' => 'Update companies',
        'company.deactivate' => 'Deactivate companies',
        'company.view_all' => 'View all companies',
        'company.assign_user' => 'Assign users to companies',
        'admin.schedule.manage' => 'Manage work schedules',
        'approval.manage' => 'Manage approval workflows',
        'admin.biometric.manage' => 'Manage biometric devices',
        'biometric.devices.view' => 'View biometric devices',
        'biometric.attendance.sync' => 'Sync biometric attendance',
    ];

    const ADMIN_DATA = [
        'admin.import.users' => 'Bulk import users',
        'admin.import.employees' => 'Bulk import employees',
        'admin.import.execute' => 'Execute admin imports',
    ];

    // ==========================================
    // PERMISSION GROUPS
    // ==========================================

    public static function all(): array
    {
        return array_merge(
            self::EMPLOYEE,
            self::LEAVE,
            self::ATTENDANCE,
            self::PATROL,
            self::PAYROLL,
            self::KPI,
            self::REIMBURSEMENT,
            self::TRAINING,
            self::COMPETENCY,
            self::ASSET,
            self::DOCUMENT,
            self::ASSIGNMENT_LETTER,
            self::BENEFIT,
            self::PERFORMANCE,
            self::CAREER,
            self::ENGAGEMENT,
            self::ORGANIZATION,
            self::COMPLIANCE,
            self::OVERTIME,
            self::REPORTING,
            self::DASHBOARD,
            self::ADMIN_SYSTEM,
            self::ADMIN_SETTINGS,
            self::ADMIN_DATA,
        );
    }

    // ==========================================
    // ROLE PERMISSION MAPPINGS
    // Default permissions per role — now reads from config/rbac.php
    // Super Admin can customize via API later (POST /admin/roles/{id}/assign-permission)
    // super_admin always gets ALL permissions (hardcoded)
    // ==========================================

    /**
     * Get default permissions for all roles.
     * Reads from config/rbac.php. super_admin always gets ALL.
     */
    public static function roleDefaultPermissions(): array
    {
        $defaults = [];
        $roleConfigs = config('rbac.roles', []);

        foreach ($roleConfigs as $roleName => $perms) {
            if ($roleName === 'super_admin') {
                $defaults[$roleName] = array_keys(self::all());
            } elseif (is_array($perms)) {
                // Ensure permissions actually exist in the registry
                $allPerms = self::all();
                $defaults[$roleName] = array_values(array_intersect($perms, array_keys($allPerms)));
            }
        }

        return $defaults;
    }

    /**
     * Get permission description
     */
    public static function description(string $permission): string
    {
        return self::all()[$permission] ?? "Permission: $permission";
    }

    /**
     * Get default permissions for a single role.
     * Falls back to empty array if role not found.
     */
    public static function forRole(string $role): array
    {
        $defaults = self::roleDefaultPermissions();
        return $defaults[$role] ?? [];
    }
}
