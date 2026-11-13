<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'paid_leave_days')) {
                $table->decimal('paid_leave_days', 5, 1)->default(0)->after('overtime_pay');
            }
            if (!Schema::hasColumn('payrolls', 'paid_leave_amount')) {
                $table->decimal('paid_leave_amount', 12, 2)->default(0)->after('paid_leave_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['paid_leave_days', 'paid_leave_amount']);
        });
    }
};
