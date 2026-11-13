<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('interview_evaluations');
        Schema::dropIfExists('interview_schedules');
        Schema::dropIfExists('offer_letters');
        Schema::dropIfExists('background_checks');
        Schema::dropIfExists('talent_pool_entries');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('job_openings');
        Schema::dropIfExists('okrs');
        Schema::dropIfExists('hr_service_request_comments');
        Schema::dropIfExists('hr_service_requests');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('review_360');
        Schema::dropIfExists('review360');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot be reversed
    }
};
