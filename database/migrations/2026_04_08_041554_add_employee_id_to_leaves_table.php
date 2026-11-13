<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            // Add employee_id alongside user_id (both are needed)
            if (!Schema::hasColumn('leaves', 'employee_id')) {
                $table->foreignId('employee_id')
                      ->nullable()
                      ->after('user_id')
                      ->constrained()
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            if (Schema::hasColumn('leaves', 'employee_id')) {
                $table->dropForeign(['employee_id']);
                $table->dropColumn('employee_id');
            }
        });
    }
};