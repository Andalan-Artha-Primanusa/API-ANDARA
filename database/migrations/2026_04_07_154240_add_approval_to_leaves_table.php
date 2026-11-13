<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            if (!Schema::hasColumn('leaves', 'approval_flow_id')) {
                $table->foreignId('approval_flow_id')->nullable()->constrained();
            }
            if (!Schema::hasColumn('leaves', 'current_step')) {
                $table->integer('current_step')->default(1);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            if (Schema::hasColumn('leaves', 'approval_flow_id')) {
                $table->dropForeign(['approval_flow_id']);
                $table->dropColumn('approval_flow_id');
            }
            if (Schema::hasColumn('leaves', 'current_step')) {
                $table->dropColumn('current_step');
            }
        });
    }
};
