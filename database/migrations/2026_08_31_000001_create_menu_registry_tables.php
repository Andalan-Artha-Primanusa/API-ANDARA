<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            Schema::create('menus', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('label');
                $table->string('path')->nullable();
                $table->string('icon')->nullable();
                $table->string('parent_key')->nullable()->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('menu_required_permissions')) {
            Schema::create('menu_required_permissions', function (Blueprint $table) {
                $table->id();
                $table->string('menu_key')->index();
                $table->string('permission_name')->index();
                $table->timestamps();
                $table->unique(['menu_key', 'permission_name']);
            });
        }

        $now = now();
        $menus = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'LayoutDashboard', 'sort_order' => 10],
            ['key' => 'dashboard.overview', 'label' => 'Overview', 'path' => '/dashboard', 'icon' => 'Home', 'parent_key' => 'dashboard', 'sort_order' => 11],
            ['key' => 'dashboard.custom', 'label' => 'Custom Dashboard', 'path' => '/dashboard/custom', 'icon' => 'SlidersHorizontal', 'parent_key' => 'dashboard', 'sort_order' => 12],
            ['key' => 'workforce', 'label' => 'Workforce', 'icon' => 'Users', 'sort_order' => 20],
            ['key' => 'workforce.employees', 'label' => 'Employees', 'path' => '/employees', 'icon' => 'Users', 'parent_key' => 'workforce', 'sort_order' => 21],
            ['key' => 'workforce.attendance', 'label' => 'Attendance', 'path' => '/attendance', 'icon' => 'Clock', 'parent_key' => 'workforce', 'sort_order' => 22],
            ['key' => 'workforce.leave', 'label' => 'Leave', 'path' => '/leave/requests', 'icon' => 'CalendarDays', 'parent_key' => 'workforce', 'sort_order' => 26],
            ['key' => 'workforce.overtime', 'label' => 'Overtime', 'path' => '/attendance/overtime', 'icon' => 'Clock', 'parent_key' => 'workforce', 'sort_order' => 27],
            ['key' => 'compensation', 'label' => 'Compensation', 'icon' => 'Banknote', 'sort_order' => 30],
            ['key' => 'compensation.payroll', 'label' => 'Payroll', 'path' => '/payroll', 'icon' => 'Wallet', 'parent_key' => 'compensation', 'sort_order' => 31],
            ['key' => 'compensation.payroll.process', 'label' => 'Proses Payroll', 'icon' => 'ListChecks', 'parent_key' => 'compensation.payroll', 'sort_order' => 32],
            ['key' => 'compensation.payroll.process.generate', 'label' => 'Generate Payroll', 'path' => '/payroll/process/generate', 'icon' => 'Zap', 'parent_key' => 'compensation.payroll.process', 'sort_order' => 33],
            ['key' => 'compensation.payroll.process.approval', 'label' => 'Persetujuan', 'path' => '/payroll/process/approval', 'icon' => 'ShieldCheck', 'parent_key' => 'compensation.payroll.process', 'sort_order' => 34],
            ['key' => 'compensation.payroll.process.payment', 'label' => 'Pembayaran', 'path' => '/payroll/process/payment', 'icon' => 'CreditCard', 'parent_key' => 'compensation.payroll.process', 'sort_order' => 35],
            ['key' => 'compensation.payroll.list', 'label' => 'Daftar Payroll', 'path' => '/payroll/list', 'icon' => 'FileSpreadsheet', 'parent_key' => 'compensation.payroll', 'sort_order' => 36],
            ['key' => 'compensation.payroll.component', 'label' => 'Komponen Gaji', 'icon' => 'SlidersHorizontal', 'parent_key' => 'compensation.payroll', 'sort_order' => 37],
            ['key' => 'compensation.payroll.component.allowance', 'label' => 'Tunjangan', 'path' => '/payroll/component/allowance', 'icon' => 'Gift', 'parent_key' => 'compensation.payroll.component', 'sort_order' => 38],
            ['key' => 'compensation.payroll.component.deduction', 'label' => 'Potongan', 'path' => '/payroll/component/deduction', 'icon' => 'MinusCircle', 'parent_key' => 'compensation.payroll.component', 'sort_order' => 39],
            ['key' => 'compensation.payroll.reports', 'label' => 'Laporan', 'path' => '/payroll/reports', 'icon' => 'FileBarChart', 'parent_key' => 'compensation.payroll', 'sort_order' => 40],
            ['key' => 'compensation.reimbursement', 'label' => 'Reimbursement', 'path' => '/reimbursements', 'icon' => 'Receipt', 'parent_key' => 'compensation', 'sort_order' => 41],
            ['key' => 'performance-dev', 'label' => 'Performance & Development', 'icon' => 'Target', 'sort_order' => 50],
            ['key' => 'performance-dev.kpi', 'label' => 'KPI', 'path' => '/kpis', 'icon' => 'Target', 'parent_key' => 'performance-dev', 'sort_order' => 51],
            ['key' => 'performance-dev.training', 'label' => 'Training', 'path' => '/training/programs', 'icon' => 'GraduationCap', 'parent_key' => 'performance-dev', 'sort_order' => 52],
            ['key' => 'performance-dev.competency', 'label' => 'Competency', 'path' => '/competencies', 'icon' => 'Award', 'parent_key' => 'performance-dev', 'sort_order' => 53],
            ['key' => 'assets', 'label' => 'Assets', 'path' => '/assets', 'icon' => 'Briefcase', 'sort_order' => 60],
            ['key' => 'approval-center', 'label' => 'Approval Center', 'path' => '/approval-flows', 'icon' => 'ClipboardCheck', 'sort_order' => 70],
            ['key' => 'reports', 'label' => 'Reports', 'path' => '/reports/dashboard-summary', 'icon' => 'FileBarChart', 'sort_order' => 80],
            ['key' => 'admin', 'label' => 'Administration', 'icon' => 'Settings', 'sort_order' => 90],
            ['key' => 'admin.organization', 'label' => 'Organization', 'parent_key' => 'admin', 'sort_order' => 91],
            ['key' => 'admin.companies', 'label' => 'Companies', 'path' => '/companies', 'icon' => 'Briefcase', 'parent_key' => 'admin.organization', 'sort_order' => 92],
            ['key' => 'admin.company', 'label' => 'Company Setting', 'path' => '/settings/company', 'icon' => 'Settings', 'parent_key' => 'admin.organization', 'sort_order' => 93],
            ['key' => 'admin.departments', 'label' => 'Departments', 'path' => '/organization/master-data/departments', 'icon' => 'Users', 'parent_key' => 'admin.organization', 'sort_order' => 94],
            ['key' => 'admin.positions', 'label' => 'Positions', 'path' => '/organization/master-data/positions', 'icon' => 'Award', 'parent_key' => 'admin.organization', 'sort_order' => 95],
            ['key' => 'admin.locations', 'label' => 'Locations', 'path' => '/locations', 'icon' => 'MapPin', 'parent_key' => 'admin.organization', 'sort_order' => 96],
            ['key' => 'admin.access-management', 'label' => 'Access Management', 'parent_key' => 'admin', 'sort_order' => 100],
            ['key' => 'admin.users', 'label' => 'Users', 'path' => '/admin/users', 'icon' => 'Users', 'parent_key' => 'admin.access-management', 'sort_order' => 101],
            ['key' => 'admin.roles', 'label' => 'Roles & Permissions', 'path' => '/admin/roles', 'icon' => 'ShieldCheck', 'parent_key' => 'admin.access-management', 'sort_order' => 102],
            ['key' => 'admin.permissions', 'label' => 'Permissions', 'path' => '/admin/permissions', 'icon' => 'ClipboardCheck', 'parent_key' => 'admin.access-management', 'sort_order' => 103],
            ['key' => 'admin.menu-permissions', 'label' => 'Menu Access', 'path' => '/admin/menu-permissions', 'icon' => 'ListChecks', 'parent_key' => 'admin.access-management', 'sort_order' => 104],
            ['key' => 'admin.system', 'label' => 'System', 'parent_key' => 'admin', 'sort_order' => 110],
            ['key' => 'admin.audit-logs', 'label' => 'Audit Logs', 'path' => '/admin/audit-logs', 'icon' => 'FileText', 'parent_key' => 'admin.system', 'sort_order' => 111],
            ['key' => 'admin.import', 'label' => 'Import Center', 'path' => '/admin/import', 'icon' => 'FileSpreadsheet', 'parent_key' => 'admin.system', 'sort_order' => 112],
            ['key' => 'admin.notifications', 'label' => 'Admin Notifications', 'path' => '/admin/notifications', 'icon' => 'Bell', 'parent_key' => 'admin.system', 'sort_order' => 113],
            ['key' => 'admin.email-send', 'label' => 'Send Email', 'path' => '/admin/notifications/email-send', 'icon' => 'Bell', 'parent_key' => 'admin.system', 'sort_order' => 114],
            ['key' => 'admin.email-logs', 'label' => 'Email Logs', 'path' => '/admin/notifications/email-logs', 'icon' => 'FileText', 'parent_key' => 'admin.system', 'sort_order' => 115],
            ['key' => 'admin.notification-settings', 'label' => 'Notification Settings', 'path' => '/settings/notifications', 'icon' => 'Settings', 'parent_key' => 'admin.system', 'sort_order' => 116],
            ['key' => 'admin.work-schedules', 'label' => 'Work Schedules', 'path' => '/work-schedules', 'icon' => 'Clock', 'parent_key' => 'admin.system', 'sort_order' => 117],
            ['key' => 'ess', 'label' => 'Employee Self Service', 'icon' => 'UserCircle', 'sort_order' => 200],
            ['key' => 'employee-dashboard', 'label' => 'Dashboard', 'path' => '/employee-dashboard', 'icon' => 'LayoutDashboard', 'parent_key' => 'ess', 'sort_order' => 201],
            ['key' => 'ess.profile', 'label' => 'My Profile', 'path' => '/my/profile', 'icon' => 'Users', 'parent_key' => 'ess', 'sort_order' => 202],
            ['key' => 'ess.attendance', 'label' => 'Attendance', 'icon' => 'Clock', 'parent_key' => 'ess', 'sort_order' => 203],
            ['key' => 'ess.attendance.history', 'label' => 'History', 'path' => '/attendance/history', 'icon' => 'ListChecks', 'parent_key' => 'ess.attendance', 'sort_order' => 207],
            ['key' => 'ess.leave', 'label' => 'Leave', 'path' => '/leave/my-leave', 'icon' => 'CalendarDays', 'parent_key' => 'ess', 'sort_order' => 208],
            ['key' => 'ess.overtime', 'label' => 'Overtime', 'path' => '/my/overtime', 'icon' => 'Clock', 'parent_key' => 'ess', 'sort_order' => 209],
            ['key' => 'ess.reimbursement', 'label' => 'Reimbursement', 'path' => '/my/reimbursements', 'icon' => 'Receipt', 'parent_key' => 'ess', 'sort_order' => 210],
            ['key' => 'ess.payslip', 'label' => 'Payslip', 'path' => '/my/payroll', 'icon' => 'Banknote', 'parent_key' => 'ess', 'sort_order' => 211],
            ['key' => 'ess.kpi', 'label' => 'My KPI', 'path' => '/my/kpi', 'icon' => 'Target', 'parent_key' => 'ess', 'sort_order' => 212],
            ['key' => 'ess.training', 'label' => 'Training', 'path' => '/my/trainings', 'icon' => 'GraduationCap', 'parent_key' => 'ess', 'sort_order' => 213],
            ['key' => 'ess.competency', 'label' => 'Competency', 'path' => '/my/competencies', 'icon' => 'Award', 'parent_key' => 'ess', 'sort_order' => 214],
            ['key' => 'ess.assets', 'label' => 'My Assets', 'path' => '/my/assets', 'icon' => 'Briefcase', 'parent_key' => 'ess', 'sort_order' => 215],
            ['key' => 'ess.documents', 'label' => 'My Documents', 'path' => '/my/documents', 'icon' => 'FileText', 'parent_key' => 'ess', 'sort_order' => 216],
            ['key' => 'ess.dashboard.custom', 'label' => 'Custom Dashboard', 'path' => '/dashboard/custom', 'icon' => 'ShieldCheck', 'parent_key' => 'ess', 'sort_order' => 217],
            ['key' => 'ess.notifications', 'label' => 'Notifications', 'path' => '/notifications', 'icon' => 'Bell', 'parent_key' => 'ess', 'sort_order' => 218],
        ];

        DB::table('menus')->insertOrIgnore(array_map(fn ($menu) => [
            'key' => $menu['key'],
            'label' => $menu['label'],
            'path' => $menu['path'] ?? null,
            'icon' => $menu['icon'] ?? null,
            'parent_key' => $menu['parent_key'] ?? null,
            'sort_order' => $menu['sort_order'] ?? 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $menus));

        $requirements = [
            'dashboard' => ['reporting.dashboard', 'dashboard.customize_self'],
            'dashboard.overview' => ['reporting.dashboard'],
            'dashboard.custom' => ['dashboard.customize_self'],
            'workforce' => ['employee.view', 'attendance.view_all', 'attendance.view_own', 'leave.view', 'overtime.view', 'patrol.scan'],
            'workforce.employees' => ['employee.view'],
            'workforce.attendance' => ['attendance.view_all', 'attendance.view_own'],
            'workforce.leave' => ['leave.view'],
            'workforce.overtime' => ['overtime.view'],
            'compensation' => ['payroll.view', 'reimbursement.view'],
            'compensation.payroll' => ['payroll.view'],
            'compensation.payroll.process' => ['payroll.generate', 'payroll.approve', 'payroll.pay'],
            'compensation.payroll.process.generate' => ['payroll.generate'],
            'compensation.payroll.process.approval' => ['payroll.approve'],
            'compensation.payroll.process.payment' => ['payroll.pay'],
            'compensation.payroll.list' => ['payroll.view'],
            'compensation.payroll.component' => ['payroll.view'],
            'compensation.payroll.component.allowance' => ['payroll.view'],
            'compensation.payroll.component.deduction' => ['payroll.view'],
            'compensation.payroll.reports' => ['payroll.reports.view'],
            'compensation.reimbursement' => ['reimbursement.view'],
            'performance-dev' => ['kpi.view', 'training.view', 'competency.view'],
            'performance-dev.kpi' => ['kpi.view'],
            'performance-dev.training' => ['training.view'],
            'performance-dev.competency' => ['competency.view'],
            'assets' => ['asset.view'],
            'approval-center' => ['admin.approval_flow.manage'],
            'reports' => ['reporting.dashboard'],
            'admin' => ['company.view', 'admin.company.view', 'user.view', 'role.view', 'permission.view', 'admin.audit.view'],
            'admin.companies' => ['company.view', 'company.view_all'],
            'admin.company' => ['admin.company.view', 'company.view'],
            'admin.departments' => ['department.view'],
            'admin.positions' => ['position.view'],
            'admin.locations' => ['location.view'],
            'admin.users' => ['user.view', 'admin.user.view'],
            'admin.roles' => ['role.view', 'admin.role.view'],
            'admin.permissions' => ['permission.view', 'admin.permission.view'],
            'admin.menu-permissions' => ['role.assign_permission', 'role.view'],
            'admin.audit-logs' => ['audit.logs.view', 'admin.audit.view'],
            'admin.import' => ['admin.import.execute'],
            'admin.notifications' => ['admin.email.manage'],
            'admin.email-send' => ['admin.email.manage'],
            'admin.email-logs' => ['admin.email.manage'],
            'admin.notification-settings' => ['admin.email.manage'],
            'admin.work-schedules' => ['admin.schedule.manage'],
            'ess.profile' => ['profile.update', 'attendance.view_own'],
            'employee-dashboard' => ['dashboard.customize_self', 'attendance.view_own'],
            'ess.attendance' => ['attendance.check_in', 'attendance.check_out', 'attendance.view_own', 'patrol.scan'],
            'ess.attendance.check-in' => ['attendance.check_in'],
            'ess.attendance.check-out' => ['attendance.check_out'],
            'ess.attendance.patrol' => ['patrol.scan'],
            'ess.attendance.history' => ['attendance.view_own'],
            'ess.leave' => ['leave.view', 'leave.create'],
            'ess.overtime' => ['overtime.view', 'overtime.create'],
            'ess.reimbursement' => ['reimbursement.view', 'reimbursement.create'],
            'ess.payslip' => ['payroll.view', 'payroll.view_own'],
            'ess.kpi' => ['kpi.view'],
            'ess.training' => ['training.view'],
            'ess.competency' => ['competency.view'],
            'ess.assets' => ['asset.view'],
            'ess.documents' => ['document.view'],
            'ess.dashboard.custom' => ['dashboard.customize_self'],
            'ess.notifications' => ['attendance.view_own'],
        ];

        $rows = [];
        foreach ($requirements as $menuKey => $permissions) {
            foreach ($permissions as $permission) {
                $rows[] = [
                    'menu_key' => $menuKey,
                    'permission_name' => $permission,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('menu_required_permissions')->insertOrIgnore($rows);

        if (Schema::hasTable('menu_permissions') && Schema::hasTable('roles')) {
            $assignmentRows = [];
            $roleIds = DB::table('roles')->pluck('id');
            $menuKeys = collect($menus)->pluck('key');

            foreach ($roleIds as $roleId) {
                foreach ($menuKeys as $menuKey) {
                    $assignmentRows[] = [
                        'menu_key' => $menuKey,
                        'role_id' => $roleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($assignmentRows, 500) as $chunk) {
                DB::table('menu_permissions')->insertOrIgnore($chunk);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_required_permissions');
        Schema::dropIfExists('menus');
    }
};
