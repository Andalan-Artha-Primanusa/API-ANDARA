<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            if (!Schema::hasColumn('leaves', 'leave_type_id')) {
                $table->foreignId('leave_type_id')
                    ->nullable()
                    ->after('employee_id')
                    ->constrained('leave_types')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            if (Schema::hasColumn('leaves', 'leave_type_id')) {
                $table->dropForeign(['leave_type_id']);
                $table->dropColumn('leave_type_id');
            }
        });
    }
};
