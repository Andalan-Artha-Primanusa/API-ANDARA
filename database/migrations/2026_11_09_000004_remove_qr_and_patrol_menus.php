<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        if (Schema::hasTable('menu_required_permissions')) {
            DB::table('menu_required_permissions')->whereIn('menu_key', [
            'workforce.attendance.qr-generator',
            'workforce.patrol.scan',
            'workforce.patrol.monitor',
            'ess.attendance.check-in',
            'ess.attendance.check-out',
            'ess.attendance.patrol',
            ])->delete();
        }

        DB::table('menus')->whereIn('key', [
            'workforce.attendance.qr-generator',
            'workforce.patrol.scan',
            'workforce.patrol.monitor',
            'ess.attendance.check-in',
            'ess.attendance.check-out',
            'ess.attendance.patrol',
        ])->delete();
    }

    public function down(): void
    {
        // Removed menu entries are intentionally not recreated automatically.
    }
};
