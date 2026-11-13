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
        Schema::create('kpis', function (Blueprint $table) {
            $table->id();

            // 🔗 Relasi ke employee
            $table->foreignId('employee_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // 📌 Data KPI
            $table->string('title');
            $table->text('description')->nullable();

            // 🎯 Target & hasil
            $table->double('target');
            $table->double('achievement')->default(0);
            $table->double('score')->default(0);

            // 📊 Status workflow
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected'
            ])->default('draft');

            // 📅 Periode KPI (contoh: 2026-04)
            $table->string('period');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpis');
    }
};