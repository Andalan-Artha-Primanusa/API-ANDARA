<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_role_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('menu_path');
            $table->timestamps();
            $table->unique(['role_id', 'menu_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_role_access');
    }
};
