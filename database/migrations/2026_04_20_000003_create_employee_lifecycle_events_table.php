<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('event_type'); // hire, promotion, transfer, etc
            $table->date('event_date');
            $table->string('from_value')->nullable(); // previous position, etc
            $table->string('to_value')->nullable();   // new position, etc
            $table->string('reason')->nullable();
            $table->json('supporting_documents')->nullable();
            $table->unsignedBigInteger('initiated_by_id')->nullable();
            $table->unsignedBigInteger('approved_by_id')->nullable();
            $table->date('approval_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->string('status')->default('pending'); // pending, approved, completed, cancelled
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('initiated_by_id')->references('id')->on('employees')->nullOnDelete();
            $table->foreign('approved_by_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['employee_id', 'event_type', 'event_date'], 'ele_idx_emp_type_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_lifecycle_events');
    }
};

