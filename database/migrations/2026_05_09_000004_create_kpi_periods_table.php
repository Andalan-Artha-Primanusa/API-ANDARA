<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kpi_periods')) {
            Schema::create('kpi_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->string('period_type'); // quarterly, semi_annual, annual
                $table->string('period_label'); // Q1 2026, H1 2026, 2026
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('overall_score', 8, 2)->default(0);
                $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('kpi_items')) {
            Schema::create('kpi_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
                $table->string('indicator');
                $table->text('description')->nullable();
                $table->string('category')->nullable(); // financial, customer, operational, employee, sales, project
                $table->string('measurement_method')->default('direct'); // direct, formula, survey, manual
                $table->string('formula_type')->default('standard'); // standard, growth, efficiency, quality, timeliness, weighted
                $table->integer('weight')->default(0); // sum to 100 across items
                $table->decimal('target', 14, 2)->default(0);
                $table->decimal('achievement', 14, 2)->default(0);
                $table->decimal('score', 8, 2)->default(0);
                $table->string('source')->nullable();
                $table->enum('status', ['draft', 'submitted', 'approved'])->default('draft');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_items');
        Schema::dropIfExists('kpi_periods');
    }
};
