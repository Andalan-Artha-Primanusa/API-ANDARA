<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('shift_swap_requests', 'approval_flow_id')) {
                $table->foreignId('approval_flow_id')->nullable()->after('reason')->constrained('approval_flows')->nullOnDelete();
            }
            if (!Schema::hasColumn('shift_swap_requests', 'current_step')) {
                $table->unsignedTinyInteger('current_step')->nullable()->after('approval_flow_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->dropForeign(['approval_flow_id']);
            $table->dropColumn(['approval_flow_id', 'current_step']);
        });
    }
};
