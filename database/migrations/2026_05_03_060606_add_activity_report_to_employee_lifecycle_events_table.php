<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_lifecycle_events', function (Blueprint $table) {
            $table->text('activity_report')->nullable()->after('remarks');
            $table->string('report_status')->nullable()->after('activity_report'); // submitted, approved, rejected
            $table->unsignedBigInteger('report_approved_by_id')->nullable()->after('report_status');
            $table->timestamp('report_approved_at')->nullable()->after('report_approved_by_id');
            $table->string('report_rejection_reason')->nullable()->after('report_approved_at');

            $table->foreign('report_approved_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_lifecycle_events', function (Blueprint $table) {
            $table->dropForeign(['report_approved_by_id']);
            $table->dropColumn(['activity_report', 'report_status', 'report_approved_by_id', 'report_approved_at', 'report_rejection_reason']);
        });
    }
};
