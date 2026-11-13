<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_flow_histories', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->unsignedBigInteger('module_id');
            $table->foreignId('approval_flow_id')->constrained()->cascadeOnDelete();
            $table->integer('step_order');
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // approved, rejected, pending
            $table->text('note')->nullable();
            $table->timestamp('acted_at')->useCurrent();
            $table->timestamps();

            $table->index(['module', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_flow_histories');
    }
};
