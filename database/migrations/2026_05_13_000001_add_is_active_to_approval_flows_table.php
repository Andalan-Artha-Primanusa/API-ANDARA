<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_flows', function (Blueprint $table) {
            if (!Schema::hasColumn('approval_flows', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('module');
            }
        });
    }

    public function down(): void
    {
        Schema::table('approval_flows', function (Blueprint $table) {
            if (Schema::hasColumn('approval_flows', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
