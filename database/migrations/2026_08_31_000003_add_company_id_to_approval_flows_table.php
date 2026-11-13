<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Additive: nullable company_id to support future per-company approval flows.
     * NULL = global flow (backward compatible with existing seeded flows).
     */
    public function up(): void
    {
        if (Schema::hasColumn('approval_flows', 'company_id')) {
            return;
        }

        Schema::table('approval_flows', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('module');
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('approval_flows', 'company_id')) {
            return;
        }

        Schema::table('approval_flows', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
