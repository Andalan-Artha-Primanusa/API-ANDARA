<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_documents', 'approval_flow_id')) {
                $table->foreignId('approval_flow_id')->nullable()->after('review_notes')->constrained('approval_flows')->nullOnDelete();
            }
            if (!Schema::hasColumn('employee_documents', 'current_step')) {
                $table->unsignedTinyInteger('current_step')->nullable()->after('approval_flow_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropForeign(['approval_flow_id']);
            $table->dropColumn(['approval_flow_id', 'current_step']);
        });
    }
};
