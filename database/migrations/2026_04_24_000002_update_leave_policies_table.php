<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_policies', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_policies', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('leave_policies', 'policy_code')) {
                $table->string('policy_code')->nullable()->after('name');
            }
            if (!Schema::hasColumn('leave_policies', 'entitlement_type')) {
                $table->string('entitlement_type')->default('fixed')->after('policy_code');
            }
            if (!Schema::hasColumn('leave_policies', 'entitlement_value')) {
                $table->integer('entitlement_value')->nullable()->after('entitlement_type');
            }
            if (!Schema::hasColumn('leave_policies', 'max_carryover_days')) {
                $table->integer('max_carryover_days')->nullable()->after('entitlement_value');
            }
            if (!Schema::hasColumn('leave_policies', 'is_paid')) {
                $table->boolean('is_paid')->default(true)->after('max_carryover_days');
            }
            // Make year nullable since we are moving towards named policies
            $table->unsignedSmallInteger('year')->nullable()->change();
            // Remove unique constraint on year if it exists
            try {
                $table->dropUnique(['year']);
            } catch (\Exception $e) {}
        });
    }

    public function down(): void
    {
        Schema::table('leave_policies', function (Blueprint $table) {
            $table->dropColumn(['name', 'policy_code', 'entitlement_type', 'entitlement_value', 'max_carryover_days', 'is_paid']);
        });
    }
};
