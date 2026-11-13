<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('device_type')->default('fingerprint');
            $table->string('vendor')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('endpoint_url')->nullable();
            $table->string('api_key')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('biometric_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_device_id')->nullable()->constrained('biometric_devices')->nullOnDelete();
            $table->string('external_reference')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date');
            $table->timestamp('check_in')->nullable();
            $table->timestamp('check_out')->nullable();
            $table->string('status')->default('synced');
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['attendance_date', 'status']);
        });

        Schema::create('engagement_surveys', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('survey_type')->default('pulse');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('anonymous')->default(true);
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('engagement_survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('engagement_surveys')->cascadeOnDelete();
            $table->string('question_type')->default('rating');
            $table->text('question_text');
            $table->unsignedSmallInteger('order_no')->default(1);
            $table->boolean('required')->default(true);
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('engagement_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('engagement_surveys')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('engagement_survey_questions')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('rating_value', 5, 2)->nullable();
            $table->text('text_answer')->nullable();
            $table->timestamps();

            $table->index(['survey_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_survey_responses');
        Schema::dropIfExists('engagement_survey_questions');
        Schema::dropIfExists('engagement_surveys');
        Schema::dropIfExists('biometric_sync_logs');
        Schema::dropIfExists('biometric_devices');
    }
};
