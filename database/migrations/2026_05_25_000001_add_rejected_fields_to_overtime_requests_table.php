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
        Schema::table('overtime_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('overtime_requests', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('overtime_requests', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            if (Schema::hasColumn('overtime_requests', 'rejected_by')) {
                $table->dropForeign(['rejected_by']);
                $table->dropColumn('rejected_by');
            }
            if (Schema::hasColumn('overtime_requests', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
        });
    }
};
