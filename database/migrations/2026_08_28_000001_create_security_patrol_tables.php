<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_patrol_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('area')->nullable();
            $table->string('floor')->nullable();
            $table->string('room')->nullable();
            $table->string('qr_code', 128)->unique();
            $table->time('starts_at')->default('20:00:00');
            $table->time('ends_at')->default('06:00:00');
            $table->unsignedSmallInteger('tolerance_minutes')->default(15);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('security_patrol_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkpoint_id')->constrained('security_patrol_checkpoints')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('scanned_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('on_time');
            $table->text('notes')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'scanned_at']);
            $table->index(['user_id', 'scanned_at']);
            $table->index(['checkpoint_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_patrol_scans');
        Schema::dropIfExists('security_patrol_checkpoints');
    }
};
