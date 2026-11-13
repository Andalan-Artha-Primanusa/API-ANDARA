<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_calendars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'year']);
        });

        Schema::create('holiday_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holiday_calendar_id')->constrained('holiday_calendars')->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name');
            $table->boolean('is_national')->default(true);
            $table->timestamps();
            $table->unique(['holiday_calendar_id', 'holiday_date']);
        });

        Schema::table('leave_policies', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_policies', 'carry_over_enabled')) {
                $table->boolean('carry_over_enabled')->default(false)->after('carry_over_allowance');
            }
            if (!Schema::hasColumn('leave_policies', 'encashment_enabled')) {
                $table->boolean('encashment_enabled')->default(false)->after('carry_over_enabled');
            }
            if (!Schema::hasColumn('leave_policies', 'blackout_ranges')) {
                $table->json('blackout_ranges')->nullable()->after('encashment_enabled');
            }
            if (!Schema::hasColumn('leave_policies', 'holiday_calendar_id')) {
                $table->foreignId('holiday_calendar_id')->nullable()->after('blackout_ranges')->constrained('holiday_calendars')->nullOnDelete();
            }
        });

        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('target_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('swap_date');
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('overtime_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('department')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('min_minutes')->default(0);
            $table->decimal('multiplier', 5, 2)->default(1.00);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_compensation_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('tax_number')->nullable();
            $table->string('tax_status')->nullable();
            $table->decimal('bpjs_kesehatan_pct', 5, 2)->nullable();
            $table->decimal('bpjs_ketenagakerjaan_pct', 5, 2)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->timestamps();
            $table->unique('employee_id');
        });

        Schema::create('payroll_retro_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('correction');
            $table->decimal('amount', 12, 2);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('channel')->default('in_app');
            $table->string('title_template');
            $table->text('body_template');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('event_key');
            $table->json('conditions')->nullable();
            $table->json('channels')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('scheduled_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->string('channel')->default('in_app');
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('queued');
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('data_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->unsignedInteger('retain_days');
            $table->boolean('anonymize_after_expiry')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique('module');
        });

        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_type');
            $table->string('status')->default('submitted');
            $table->text('description')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->index(['request_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
        Schema::dropIfExists('data_retention_policies');
        Schema::dropIfExists('scheduled_notifications');
        Schema::dropIfExists('notification_rule_sets');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('payroll_retro_adjustments');
        Schema::dropIfExists('employee_compensation_profiles');
        Schema::dropIfExists('overtime_rules');
        Schema::dropIfExists('shift_swap_requests');

        if (Schema::hasTable('leave_policies')) {
            Schema::table('leave_policies', function (Blueprint $table) {
                if (Schema::hasColumn('leave_policies', 'holiday_calendar_id')) {
                    $table->dropForeign(['holiday_calendar_id']);
                    $table->dropColumn('holiday_calendar_id');
                }
                if (Schema::hasColumn('leave_policies', 'blackout_ranges')) {
                    $table->dropColumn('blackout_ranges');
                }
                if (Schema::hasColumn('leave_policies', 'encashment_enabled')) {
                    $table->dropColumn('encashment_enabled');
                }
                if (Schema::hasColumn('leave_policies', 'carry_over_enabled')) {
                    $table->dropColumn('carry_over_enabled');
                }
            });
        }

        Schema::dropIfExists('holiday_dates');
        Schema::dropIfExists('holiday_calendars');
    }
};
