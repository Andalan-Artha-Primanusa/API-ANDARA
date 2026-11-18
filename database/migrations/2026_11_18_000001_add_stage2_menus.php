<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus') || !Schema::hasTable('menu_required_permissions')) return;

        $now = now();
        $menus = [
            ['key' => 'benefits', 'label' => 'Benefits', 'path' => '/benefits', 'icon' => 'Gift', 'parent_key' => 'compensation', 'sort_order' => 42],
            ['key' => 'ess.benefits', 'label' => 'My Benefits', 'path' => '/my/benefits', 'icon' => 'Gift', 'parent_key' => 'ess', 'sort_order' => 216],
            ['key' => 'reporting.people-insight', 'label' => 'People Insight', 'path' => '/insights/people/detailed', 'icon' => 'BarChart3', 'parent_key' => 'reports', 'sort_order' => 81],
            ['key' => 'compliance.overview', 'label' => 'Compliance Overview', 'path' => '/compliance/overview', 'icon' => 'ShieldCheck', 'parent_key' => 'reports', 'sort_order' => 82],
            ['key' => 'compliance.audit-summary', 'label' => 'Compliance Audit', 'path' => '/compliance/audit-summary', 'icon' => 'FileText', 'parent_key' => 'reports', 'sort_order' => 83],
            ['key' => 'compliance.expiring-documents', 'label' => 'Expiring Documents', 'path' => '/compliance/expiring-documents', 'icon' => 'FileText', 'parent_key' => 'reports', 'sort_order' => 84],
            ['key' => 'performance.review', 'label' => 'Performance Review', 'path' => '/performance/reviews', 'icon' => 'Target', 'parent_key' => 'performance-dev', 'sort_order' => 54],
            ['key' => 'engagement.survey', 'label' => 'Engagement Survey', 'path' => '/engagement/surveys', 'icon' => 'ClipboardCheck', 'parent_key' => 'performance-dev', 'sort_order' => 55],
            ['key' => 'admin.biometric', 'label' => 'Biometric Devices', 'path' => '/admin/biometric-devices', 'icon' => 'Fingerprint', 'parent_key' => 'admin.system', 'sort_order' => 118],
            ['key' => 'career.promotions', 'label' => 'Promotion & Career', 'path' => '/promotions', 'icon' => 'TrendingUp', 'parent_key' => 'workforce', 'sort_order' => 61],
            ['key' => 'assignment-letters', 'label' => 'Assignment Letter', 'path' => '/assignment-letters', 'icon' => 'FileText', 'parent_key' => 'workforce', 'sort_order' => 62],
        ];

        foreach ($menus as $menu) {
            DB::table('menus')->updateOrInsert(['key' => $menu['key']], array_merge($menu, [
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }

        $requirements = [
            'benefits' => 'benefit.view',
            'ess.benefits' => 'benefit.view',
            'reporting.people-insight' => 'reporting.dashboard',
            'compliance.overview' => 'compliance.view',
            'compliance.audit-summary' => 'compliance.audit',
            'compliance.expiring-documents' => 'compliance.documents',
            'performance.review' => 'performance.review.view',
            'engagement.survey' => 'engagement.survey.view',
            'admin.biometric' => 'admin.biometric.manage',
            'career.promotions' => 'career.promotion.view',
            'assignment-letters' => 'assignment_letter.view',
        ];

        foreach ($requirements as $menuKey => $permission) {
            DB::table('menu_required_permissions')->updateOrInsert(
                ['menu_key' => $menuKey, 'permission_name' => $permission],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) return;
        $keys = [
            'reporting.people-insight', 'compliance.overview', 'compliance.audit-summary',
            'compliance.expiring-documents', 'performance.review', 'engagement.survey', 'admin.biometric',
            'career.promotions', 'assignment-letters',
        ];
        DB::table('menu_required_permissions')->whereIn('menu_key', $keys)->delete();
        DB::table('menus')->whereIn('key', $keys)->delete();
    }
};
