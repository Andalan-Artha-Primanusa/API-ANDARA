<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {

            if (!Schema::hasColumn('leaves', 'total_days')) {
                $table->integer('total_days')->default(1)->after('end_date');
            }

            if (!Schema::hasColumn('leaves', 'type')) {
                $table->string('type', 50)
                      ->default('annual')
                      ->after('total_days');
            }

            if (!Schema::hasColumn('leaves', 'approved_by')) {
                $table->foreignId('approved_by')
                      ->nullable()
                      ->after('status')
                      ->constrained('users')
                      ->nullOnDelete();
            }

            if (!Schema::hasColumn('leaves', 'approved_at')) {
                $table->timestamp('approved_at')
                      ->nullable()
                      ->after('approved_by');
            }

            if (!Schema::hasColumn('leaves', 'approval_note')) {
                $table->text('approval_note')
                      ->nullable()
                      ->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $columns = ['total_days', 'type', 'approval_note', 'approved_at'];
            $dropColumns = [];
            foreach ($columns as $col) {
                if (Schema::hasColumn('leaves', $col)) {
                    $dropColumns[] = $col;
                }
            }
            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }

            if (Schema::hasColumn('leaves', 'approved_by')) {
                $table->dropForeign(['approved_by']);
                $table->dropColumn('approved_by');
            }
        });
    }
};