<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('recipient_email', 255);
            $table->string('subject', 500);
            $table->enum('type', ['approval', 'reminder', 'notification', 'alert', 'report', 'document', 'workflow'])->default('notification');
            $table->enum('status', ['pending', 'sent', 'failed', 'bounced'])->default('pending');
            $table->text('body')->nullable();
            $table->string('reference_type')->nullable()->comment('Model type: leave, reimbursement, etc');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('Model ID');
            $table->integer('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['status']);
            $table->index(['recipient_email']);
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique()->comment('Template identifier like approval_request');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('subject', 500);
            $table->text('html_body');
            $table->text('text_body')->nullable();
            $table->json('placeholders')->nullable()->comment('Available variables: {user_name}, {approver_name}, etc');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_logs');
    }
};
