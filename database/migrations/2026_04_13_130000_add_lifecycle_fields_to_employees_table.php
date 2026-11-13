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
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'status')) {
                $table->string('status')->default('active')->after('department');
            }

            if (!Schema::hasColumn('employees', 'probation_end_date')) {
                $table->date('probation_end_date')->nullable()->after('hire_date');
            }

            if (!Schema::hasColumn('employees', 'termination_date')) {
                $table->date('termination_date')->nullable()->after('probation_end_date');
            }

            if (!Schema::hasColumn('employees', 'termination_reason')) {
                $table->text('termination_reason')->nullable()->after('termination_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'termination_reason')) {
                $table->dropColumn('termination_reason');
            }

            if (Schema::hasColumn('employees', 'termination_date')) {
                $table->dropColumn('termination_date');
            }

            if (Schema::hasColumn('employees', 'probation_end_date')) {
                $table->dropColumn('probation_end_date');
            }

            if (Schema::hasColumn('employees', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};