<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('andalan_kpi_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('period_type')->default('annual');
            $table->string('period_label');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->string('created_by_user_id')->nullable();
            $table->string('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date']);
        });

        Schema::create('andalan_kpi_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('andalan_kpi_cycles')->cascadeOnDelete();
            // These are MINEPRO identifiers copied as references. No FK is
            // created because MINEPRO is an external/legacy data source.
            $table->string('user_id')->nullable();
            $table->string('salary_number')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('employee_name');
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('indicator');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('measurement_method')->default('manual');
            $table->string('unit')->nullable();
            $table->decimal('target', 14, 2)->default(0);
            $table->decimal('achievement', 14, 2)->default(0);
            $table->decimal('score', 8, 2)->default(0);
            $table->decimal('weight', 6, 2)->default(0);
            $table->string('status')->default('draft');
            $table->string('evidence_url', 1000)->nullable();
            $table->text('employee_comment')->nullable();
            $table->text('supervisor_comment')->nullable();
            $table->timestamps();

            $table->index(['cycle_id', 'employee_id']);
            $table->index(['salary_number', 'cycle_id']);
        });

        Schema::create('andalan_kpi_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('andalan_kpi_cycles')->cascadeOnDelete();
            $table->string('approval_role');
            $table->string('action');
            $table->string('actor_user_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['cycle_id', 'approval_role', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('andalan_kpi_approvals');
        Schema::dropIfExists('andalan_kpi_items');
        Schema::dropIfExists('andalan_kpi_cycles');
    }
};
