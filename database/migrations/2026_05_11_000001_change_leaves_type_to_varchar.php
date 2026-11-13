<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            // Change ENUM type column to VARCHAR to accept any leave type code (e.g., 'CT-01')
            $table->string('type', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            // Revert back to ENUM if needed
            $table->enum('type', ['annual', 'sick', 'unpaid'])->change();
        });
    }
};
