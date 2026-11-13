<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Role Definitions
    |--------------------------------------------------------------------------
    |
    | Daftar role default beserta permission masing-masing.
    | super_admin selalu mendapat semua permission (dihardcode di Permissions.php).
    | Role lain bisa ditambah/dikurangi di sini tanpa mengubah kode.
    |
    | Format:
    | 'nama_role' => ['permission.1', 'permission.2', ...]
    | Khusus 'super_admin' gunakan string '*' artinya semua permission.
    |
    */

    'roles' => [
        'super_admin' => '*',

        'admin' => [
            'employee.view', 'employee.create', 'employee.update', 'employee.delete',
            'employee.onboard', 'employee.offboard',
            'leave.view', 'leave.create', 'leave.update', 'leave.delete', 'leave.approve',
            'leave.policy.manage',
            'attendance.view_all', 'attendance.delete', 'attendance.check_in', 'attendance.check_out',
            'attendance.view_own', 'attendance.qr.generate', 'attendance.qr.scan', 'attendance.manual_adjust',
            'patrol.scan', 'patrol.view', 'patrol.manage', 'patrol.report',
            'payroll.view', 'payroll.view_own', 'payroll.create', 'payroll.generate', 'payroll.approve', 'payroll.pay',
            'payroll.export', 'payroll.reports.view',
            'kpi.view', 'kpi.create', 'kpi.update', 'kpi.delete', 'kpi.approve',
            'reimbursement.view', 'reimbursement.create', 'reimbursement.approve', 'reimbursement.pay',
            'overtime.view', 'overtime.create', 'overtime.approve', 'overtime.manage',
            'training.view', 'training.create', 'training.update', 'training.delete', 'training.enroll',
            'competency.view', 'competency.create', 'competency.update', 'competency.delete', 'competency.assign',
            'asset.view', 'asset.create', 'asset.update', 'asset.delete', 'asset.assign',
            'document.view', 'document.create', 'document.update', 'document.delete', 'document.review',
            'assignment_letter.view', 'assignment_letter.create', 'assignment_letter.approve', 'assignment_letter.export',
            'career.idp.view', 'career.idp.create', 'career.idp.update',
            'career.succession.view', 'career.succession.manage',
            'career.promotion.view', 'career.promotion.create', 'career.promotion.update',
            'career.promotion.delete', 'career.promotion.approve',
            'engagement.survey.view', 'engagement.survey.create', 'engagement.survey.respond',
            'engagement.survey.analytics',
            'organization.view', 'organization.directory', 'organization.chart', 'organization.team',
            'compliance.view', 'compliance.audit', 'compliance.documents',
            'reporting.dashboard', 'reporting.attendance', 'reporting.leave', 'reporting.payroll',
            'reporting.competency', 'reporting.lifecycle', 'reporting.assets',
            'dashboard.customize_self', 'dashboard.manage_default', 'dashboard.view_all_company',
            'user.view', 'user.create', 'user.update', 'user.delete', 'user.assign_role',
            'role.view', 'role.create', 'role.update', 'role.delete', 'role.assign_permission',
            'permission.view',
            'admin.user.view', 'admin.user.create', 'admin.user.update', 'admin.user.delete', 'admin.user.assign_role',
            'admin.role.view', 'admin.role.assign_permission',
            'admin.permission.view',
            'admin.audit.view', 'admin.audit.delete',
            'audit.logs.view',
            'location.view', 'location.create', 'location.update', 'location.delete',
            'department.view', 'department.create', 'department.update', 'department.delete',
            'position.view', 'position.create', 'position.update', 'position.delete',
            'profile.view_all', 'profile.update', 'profile.delete',
            'admin.email.manage',
            'admin.location.manage', 'admin.department.manage', 'admin.position.manage',
            'admin.company.view', 'admin.company.update',
            'company.view', 'company.create', 'company.update', 'company.deactivate', 'company.view_all', 'company.assign_user',
            'admin.schedule.manage', 'admin.approval_flow.manage', 'admin.biometric.manage',
            'biometric.devices.view', 'biometric.attendance.sync',
            'admin.import.users', 'admin.import.employees', 'admin.import.execute',
        ],

        'hr' => [
            'employee.view', 'employee.create', 'employee.update', 'employee.delete',
            'employee.onboard', 'employee.offboard',
            'leave.view', 'leave.create', 'leave.update', 'leave.delete', 'leave.approve',
            'leave.policy.manage',
            'attendance.view_all', 'attendance.delete', 'attendance.check_in', 'attendance.check_out',
            'attendance.view_own', 'attendance.qr.generate', 'attendance.qr.scan', 'attendance.manual_adjust',
            'patrol.scan', 'patrol.view', 'patrol.manage', 'patrol.report',
            'payroll.view', 'payroll.view_own', 'payroll.create', 'payroll.generate', 'payroll.approve', 'payroll.pay',
            'payroll.export', 'payroll.reports.view',
            'kpi.view', 'kpi.create', 'kpi.update', 'kpi.delete', 'kpi.approve',
            'reimbursement.view', 'reimbursement.create', 'reimbursement.approve', 'reimbursement.pay',
            'overtime.view', 'overtime.create', 'overtime.approve', 'overtime.manage',
            'training.view', 'training.create', 'training.update', 'training.delete', 'training.enroll',
            'competency.view', 'competency.create', 'competency.update', 'competency.delete', 'competency.assign',
            'asset.view', 'asset.create', 'asset.update', 'asset.delete', 'asset.assign',
            'document.view', 'document.create', 'document.update', 'document.delete', 'document.review',
            'assignment_letter.view', 'assignment_letter.create', 'assignment_letter.approve', 'assignment_letter.export',
            'benefit.view', 'benefit.create', 'benefit.update', 'benefit.delete', 'benefit.assign',
            'performance.cycle.view', 'performance.cycle.create', 'performance.cycle.manage',
            'performance.review.view', 'performance.review.create', 'performance.review.update',
            'performance.review.submit', 'performance.review.approve',
            'career.idp.view', 'career.idp.create', 'career.idp.update',
            'career.succession.view', 'career.succession.manage',
            'career.promotion.view', 'career.promotion.create', 'career.promotion.update',
            'career.promotion.delete', 'career.promotion.approve',
            'engagement.survey.view', 'engagement.survey.create', 'engagement.survey.respond',
            'engagement.survey.analytics',
            'organization.view', 'organization.directory', 'organization.chart', 'organization.team',
            'compliance.view', 'compliance.audit', 'compliance.documents',
            'reporting.dashboard', 'reporting.attendance', 'reporting.leave', 'reporting.payroll',
            'reporting.competency', 'reporting.lifecycle', 'reporting.assets',
            'dashboard.customize_self', 'dashboard.manage_default',
            'admin.email.manage', 'admin.email.manage',
            'profile.view_all', 'profile.update',
            'admin.import.users', 'admin.import.employees', 'admin.import.execute',
            'location.view', 'department.view', 'position.view',
            'company.view', 'company.create', 'company.update', 'company.assign_user',
        ],

        'ho' => [
            'employee.view',
            'leave.view', 'leave.approve',
            'attendance.view_all', 'attendance.view_own', 'attendance.check_in', 'attendance.check_out',
            'attendance.qr.generate', 'attendance.qr.scan',
            'patrol.scan', 'patrol.view', 'patrol.manage', 'patrol.report',
            'payroll.view', 'payroll.view_own', 'payroll.reports.view', 'payroll.export',
            'kpi.view',
            'reimbursement.view', 'reimbursement.approve',
            'overtime.view', 'overtime.approve',
            'training.view',
            'competency.view',
            'asset.view',
            'document.view', 'document.review',
            'assignment_letter.view', 'assignment_letter.approve', 'assignment_letter.export',
            'organization.view', 'organization.directory', 'organization.chart', 'organization.team',
            'compliance.view', 'compliance.audit', 'compliance.documents',
            'reporting.dashboard', 'reporting.attendance', 'reporting.leave', 'reporting.payroll',
            'reporting.competency', 'reporting.lifecycle', 'reporting.assets',
            'dashboard.customize_self', 'dashboard.manage_default', 'dashboard.view_all_company',
            'company.view', 'company.view_all', 'company.create', 'company.update', 'company.assign_user',
            'location.view', 'department.view', 'position.view',
        ],

        'manager' => [
            'employee.view',
            'leave.view', 'leave.create', 'leave.approve',
            'attendance.check_in', 'attendance.check_out', 'attendance.view_own', 'attendance.qr.scan',
            'patrol.scan', 'patrol.view',
            'kpi.view', 'kpi.create', 'kpi.update', 'kpi.approve',
            'reimbursement.view', 'reimbursement.create', 'reimbursement.approve',
            'overtime.view', 'overtime.create', 'overtime.approve',
            'payroll.view_own',
            'training.view', 'training.enroll',
            'competency.view',
            'asset.view',
            'document.view',
            'assignment_letter.view',
            'performance.review.view', 'performance.review.approve',
            'organization.view', 'organization.directory', 'organization.chart', 'organization.team',
            'reporting.dashboard',
            'dashboard.customize_self',
        ],

        'employee' => [
            'leave.view', 'leave.create',
            'attendance.check_in', 'attendance.check_out', 'attendance.view_own', 'attendance.qr.scan',
            'patrol.scan',
            'overtime.create', 'overtime.view',
            'payroll.view_own',
            'kpi.view',
            'reimbursement.view', 'reimbursement.create',
            'training.view',
            'competency.view',
            'asset.view',
            'document.view',
            'assignment_letter.view', 'assignment_letter.create', 'assignment_letter.export',
            'performance.review.view',
            'career.idp.view',
            'engagement.survey.respond', 'engagement.survey.view',
            'dashboard.customize_self',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Level Indicators
    |--------------------------------------------------------------------------
    |
    | Digunakan oleh User.php isAdmin()/isHR()/isManager()/isEmployee()
    | untuk menentukan level user berdasarkan PERMISSION, bukan role name.
    | Super_admin selalu dianggap all-level.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Role for New Registrations
    |--------------------------------------------------------------------------
    |
    | Role name yang akan diberikan ke user baru saat register.
    | Bisa diubah sesuai kebutuhan tanpa mengubah kode.
    |
    */

    'default_role' => env('RBAC_DEFAULT_ROLE', 'employee'),

    'level_indicators' => [
        'admin' => [
            'role.view', 'role.create', 'role.update', 'role.delete', 'role.assign_permission',
            'user.view', 'user.create', 'user.update', 'user.delete', 'user.assign_role',
            'permission.view',
            'admin.audit.view',
            'admin.audit.delete',
        ],
        'hr' => [
            'employee.create', 'employee.delete', 'leave.policy.manage',
        ],
        'manager' => [
            'employee.view', 'employee.update', 'employee.onboard', 'employee.offboard',
            'leave.approve',
            'kpi.approve',
            'overtime.approve',
            'performance.review.approve',
        ],
        'employee' => [
            'attendance.check_in', 'attendance.check_out', 'attendance.view_own',
            'leave.create',
            'overtime.create',
            'reimbursement.create',
        ],
    ],
];
