<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update status enum — backward-compatible (keeps existing values)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payrolls MODIFY COLUMN status ENUM('draft', 'pending_hr', 'approved', 'paid', 'rejected') DEFAULT 'draft'");
        }

        Schema::table('payrolls', function (Blueprint $table) {
            // 2. Late deduction columns
            if (!Schema::hasColumn('payrolls', 'late_days')) {
                $table->integer('late_days')->default(0)->after('paid_leave_amount');
            }
            if (!Schema::hasColumn('payrolls', 'late_deduction')) {
                $table->decimal('late_deduction', 14, 2)->default(0)->after('late_days');
            }

            // 3. Reimbursement column
            if (!Schema::hasColumn('payrolls', 'reimbursement_amount')) {
                $table->decimal('reimbursement_amount', 14, 2)->default(0)->after('late_deduction');
            }

            // 4. Audit fields
            if (!Schema::hasColumn('payrolls', 'manager_approved_by')) {
                $table->unsignedBigInteger('manager_approved_by')->nullable()->after('status');
            }
            if (!Schema::hasColumn('payrolls', 'manager_approved_at')) {
                $table->timestamp('manager_approved_at')->nullable()->after('manager_approved_by');
            }
            if (!Schema::hasColumn('payrolls', 'hr_approved_by')) {
                $table->unsignedBigInteger('hr_approved_by')->nullable()->after('manager_approved_at');
            }
            if (!Schema::hasColumn('payrolls', 'hr_approved_at')) {
                $table->timestamp('hr_approved_at')->nullable()->after('hr_approved_by');
            }
            if (!Schema::hasColumn('payrolls', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('hr_approved_at');
            }
            if (!Schema::hasColumn('payrolls', 'rejected_reason')) {
                $table->text('rejected_reason')->nullable()->after('rejected_by');
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payrolls MODIFY COLUMN status ENUM('draft', 'approved', 'paid') DEFAULT 'draft'");
        }

        Schema::table('payrolls', function (Blueprint $table) {
            $cols = [
                'late_days', 'late_deduction', 'reimbursement_amount',
                'manager_approved_by', 'manager_approved_at',
                'hr_approved_by', 'hr_approved_at',
                'rejected_by', 'rejected_reason',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('payrolls', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
