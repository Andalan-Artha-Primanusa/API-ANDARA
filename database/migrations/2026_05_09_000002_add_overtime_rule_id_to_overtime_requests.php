<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('overtime_requests', 'overtime_rule_id')) {
                $table->foreignId('overtime_rule_id')
                    ->nullable()
                    ->after('attendance_id')
                    ->constrained('overtime_rules')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            if (Schema::hasColumn('overtime_requests', 'overtime_rule_id')) {
                $table->dropForeign(['overtime_rule_id']);
                $table->dropColumn('overtime_rule_id');
            }
        });
    }
};
