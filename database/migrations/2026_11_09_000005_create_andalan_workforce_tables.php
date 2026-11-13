<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('andalan_payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_code', 50)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 30)->default('draft');
            $table->string('created_by_user_id', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('andalan_payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('andalan_payroll_periods')->cascadeOnDelete();
            $table->string('employee_id', 255)->index();
            $table->string('salary_number', 255)->nullable()->index();
            $table->string('employee_name', 255);
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('deduction_amount', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->string('source_ref', 255)->nullable();
            $table->timestamps();
            $table->unique(['period_id', 'employee_id']);
        });

        Schema::create('andalan_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_record_id')->constrained('andalan_payroll_records')->cascadeOnDelete();
            $table->string('item_type', 30);
            $table->string('item_name', 255);
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('andalan_training_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('provider', 255)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('created_by_user_id', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('andalan_training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('andalan_training_programs')->cascadeOnDelete();
            $table->string('employee_id', 255)->index();
            $table->string('salary_number', 255)->nullable()->index();
            $table->string('employee_name', 255);
            $table->string('status', 30)->default('requested');
            $table->decimal('score', 8, 2)->nullable();
            $table->string('certificate_url', 1000)->nullable();
            $table->timestamps();
            $table->unique(['program_id', 'employee_id']);
        });

        Schema::create('andalan_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 100)->unique();
            $table->string('name', 255);
            $table->string('category', 100)->nullable();
            $table->string('serial_number', 255)->nullable();
            $table->string('status', 30)->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('andalan_asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('andalan_assets')->cascadeOnDelete();
            $table->string('employee_id', 255)->index();
            $table->string('salary_number', 255)->nullable()->index();
            $table->string('employee_name', 255);
            $table->string('status', 30)->default('requested');
            $table->date('assigned_at')->nullable();
            $table->date('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('andalan_overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 255)->index();
            $table->string('salary_number', 255)->nullable()->index();
            $table->string('employee_name', 255);
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('total_hours', 8, 2)->default(0);
            $table->text('reason');
            $table->string('status', 30)->default('draft');
            $table->timestamps();
        });

        Schema::create('andalan_overtime_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_request_id')->constrained('andalan_overtime_requests')->cascadeOnDelete();
            $table->string('approval_role', 50);
            $table->string('actor_user_id', 255)->nullable();
            $table->string('action', 30);
            $table->text('note')->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('andalan_documents', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 255)->index();
            $table->string('salary_number', 255)->nullable()->index();
            $table->string('employee_name', 255);
            $table->string('document_type', 100);
            $table->string('title', 255);
            $table->string('file_url', 1000)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->string('uploaded_by_user_id', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('andalan_documents');
        Schema::dropIfExists('andalan_overtime_approvals');
        Schema::dropIfExists('andalan_overtime_requests');
        Schema::dropIfExists('andalan_asset_assignments');
        Schema::dropIfExists('andalan_assets');
        Schema::dropIfExists('andalan_training_enrollments');
        Schema::dropIfExists('andalan_training_programs');
        Schema::dropIfExists('andalan_payroll_items');
        Schema::dropIfExists('andalan_payroll_records');
        Schema::dropIfExists('andalan_payroll_periods');
    }
};
