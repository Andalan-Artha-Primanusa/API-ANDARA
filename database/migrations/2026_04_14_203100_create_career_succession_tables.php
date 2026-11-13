<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_development_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('goal_title');
            $table->text('goal_description')->nullable();
            $table->string('status')->default('draft');
            $table->date('target_date')->nullable();
            $table->foreignId('mentor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('idp_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idp_id')->constrained('individual_development_plans')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('succession_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('position_key');
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('readiness')->default('ready_1_2_years');
            $table->decimal('talent_score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['position_key', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('succession_candidates');
        Schema::dropIfExists('idp_action_items');
        Schema::dropIfExists('individual_development_plans');
    }
};
