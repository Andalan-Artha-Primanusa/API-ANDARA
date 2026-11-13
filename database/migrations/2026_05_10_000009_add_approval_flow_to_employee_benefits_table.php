<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_benefits', function (Blueprint $table) {
            $table->foreignId('approval_flow_id')->nullable()->constrained()->nullOnDelete()->after('assigned_by');
            $table->integer('current_step')->default(1)->after('approval_flow_id');
        });
    }

    public function down(): void
    {
        Schema::table('employee_benefits', function (Blueprint $table) {
            $table->dropForeign(['approval_flow_id']);
            $table->dropColumn(['approval_flow_id', 'current_step']);
        });
    }
};
