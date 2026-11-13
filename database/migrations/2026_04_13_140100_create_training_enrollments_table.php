<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['enrolled', 'in_progress', 'completed', 'failed', 'cancelled'])->default('enrolled');
            $table->decimal('score', 5, 2)->nullable();
            $table->string('certificate_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['training_program_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_enrollments');
    }
};