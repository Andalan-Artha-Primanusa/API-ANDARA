<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_policy_id')->nullable()->constrained('leave_policies')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('leave_type')->default('annual');
            $table->unsignedSmallInteger('allocated_days')->default(0);
            $table->unsignedSmallInteger('carry_over_days')->default(0);
            $table->unsignedSmallInteger('used_days')->default(0);
            $table->unsignedSmallInteger('pending_days')->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'leave_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_balances');
    }
};