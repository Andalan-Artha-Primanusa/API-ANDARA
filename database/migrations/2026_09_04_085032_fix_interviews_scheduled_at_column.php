<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom yang hilang pada tabel `interviews`.
     *
     * Error: "Unknown column 'scheduled_at' in SELECT ... from `interviews`".
     * Backend produksi masih menjalankan kode recruitment lama yang query
     * tabel `interviews` dengan kolom `application_id` & `scheduled_at`.
     */
    public function up(): void
    {
        if (Schema::hasTable('interviews')) {
            if (!Schema::hasColumn('interviews', 'application_id')) {
                Schema::table('interviews', function (Blueprint $table) {
                    $table->string('application_id', 36)->nullable()->after('id')->index();
                });
            }

            if (!Schema::hasColumn('interviews', 'scheduled_at')) {
                Schema::table('interviews', function (Blueprint $table) {
                    $table->timestamp('scheduled_at')->nullable()->after('application_id');
                });
            }

            if (!Schema::hasColumn('interviews', 'status')) {
                Schema::table('interviews', function (Blueprint $table) {
                    $table->string('status', 50)->nullable()->after('scheduled_at');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('interviews')) {
            Schema::table('interviews', function (Blueprint $table) {
                if (Schema::hasColumn('interviews', 'status')) {
                    $table->dropColumn('status');
                }
                if (Schema::hasColumn('interviews', 'scheduled_at')) {
                    $table->dropColumn('scheduled_at');
                }
                if (Schema::hasColumn('interviews', 'application_id')) {
                    $table->dropColumn('application_id');
                }
            });
        }
    }
};
