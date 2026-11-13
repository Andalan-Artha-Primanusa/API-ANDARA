<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('andalan_news', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('summary')->nullable();
            $table->text('content')->nullable();
            $table->string('category')->default('general');
            $table->string('cover_image_url', 1000)->nullable();
            $table->boolean('is_published')->default(true);
            $table->dateTime('published_at')->nullable();
            $table->string('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'published_at']);
        });

        Schema::create('andalan_rosters', function (Blueprint $table) {
            $table->id();
            $table->date('roster_date');
            $table->string('user_id')->nullable();
            $table->string('salary_number')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('employee_name');
            $table->string('roster_type')->default('leave');
            $table->string('leave_type')->nullable();
            $table->string('status')->default('planned');
            $table->text('notes')->nullable();
            $table->string('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['roster_date', 'status']);
            $table->index(['salary_number', 'roster_date']);
        });

        Schema::create('andalan_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('salary_number')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('employee_name');
            $table->string('leave_type');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('total_days');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->text('approval_note')->nullable();
            $table->string('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['start_date', 'end_date', 'status']);
        });

        Schema::create('andalan_reimbursements', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('salary_number')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('employee_name');
            $table->string('title');
            $table->string('category')->default('other');
            $table->decimal('amount', 18, 2);
            $table->date('expense_date');
            $table->text('description')->nullable();
            $table->string('receipt_url', 1000)->nullable();
            $table->string('status')->default('draft');
            $table->text('approval_note')->nullable();
            $table->string('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['expense_date', 'status']);
        });

        Schema::create('andalan_business_trips', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('salary_number')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('employee_name');
            $table->string('purpose');
            $table->string('destination');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('total_days');
            $table->decimal('estimated_amount', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->text('approval_note')->nullable();
            $table->string('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['start_date', 'end_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('andalan_business_trips');
        Schema::dropIfExists('andalan_reimbursements');
        Schema::dropIfExists('andalan_leave_requests');
        Schema::dropIfExists('andalan_rosters');
        Schema::dropIfExists('andalan_news');
    }
};
