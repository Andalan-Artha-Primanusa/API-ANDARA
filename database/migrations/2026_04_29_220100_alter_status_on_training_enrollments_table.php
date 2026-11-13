<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE training_enrollments MODIFY COLUMN status ENUM('pending', 'enrolled', 'in_progress', 'completed', 'failed', 'cancelled') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE training_enrollments MODIFY COLUMN status ENUM('enrolled', 'in_progress', 'completed', 'failed', 'cancelled') DEFAULT 'enrolled'");
        }
    }
};
