<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('andalan_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_key', 255)->index();
            $table->string('title', 255);
            $table->text('message');
            $table->string('type', 50)->default('general');
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
            $table->index(['recipient_key', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('andalan_notifications');
    }
};
