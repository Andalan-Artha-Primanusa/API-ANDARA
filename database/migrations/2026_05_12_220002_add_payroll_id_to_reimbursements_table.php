<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            if (!Schema::hasColumn('reimbursements', 'payroll_id')) {
                $table->unsignedBigInteger('payroll_id')->nullable()->after('employee_id');
                $table->foreign('payroll_id')->references('id')->on('payrolls')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            if (Schema::hasColumn('reimbursements', 'payroll_id')) {
                $table->dropForeign(['payroll_id']);
                $table->dropColumn('payroll_id');
            }
        });
    }
};
