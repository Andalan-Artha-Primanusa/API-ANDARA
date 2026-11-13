<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        // Only add employee_id if it doesn't already exist
        // (create_payrolls_table may have already included it)
        if (!Schema::hasColumn('payrolls', 'employee_id')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->foreignId('employee_id')
                      ->after('id')
                      ->constrained()
                      ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payrolls', 'employee_id')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropConstrainedForeignId('employee_id');
            });
        }
    }
};