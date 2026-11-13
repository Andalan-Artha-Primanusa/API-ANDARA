<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('andalan_payroll_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('andalan_payroll_periods')->cascadeOnDelete();
            $table->string('approval_role', 50);
            $table->string('actor_user_id', 255)->nullable();
            $table->string('action', 30);
            $table->text('note')->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('andalan_training_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('andalan_training_enrollments')->cascadeOnDelete();
            $table->string('approval_role', 50);
            $table->string('actor_user_id', 255)->nullable();
            $table->string('action', 30);
            $table->text('note')->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('andalan_asset_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('andalan_asset_assignments')->cascadeOnDelete();
            $table->string('approval_role', 50);
            $table->string('actor_user_id', 255)->nullable();
            $table->string('action', 30);
            $table->text('note')->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('andalan_document_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('andalan_documents')->cascadeOnDelete();
            $table->string('review_role', 50);
            $table->string('actor_user_id', 255)->nullable();
            $table->string('action', 30);
            $table->text('note')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('andalan_document_reviews');
        Schema::dropIfExists('andalan_asset_approvals');
        Schema::dropIfExists('andalan_training_approvals');
        Schema::dropIfExists('andalan_payroll_approvals');
    }
};
