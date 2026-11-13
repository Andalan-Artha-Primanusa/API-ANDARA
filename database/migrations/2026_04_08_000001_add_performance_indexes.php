<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add performance indexes to frequently queried columns.
     */
    public function up(): void
    {
        // Attendance: daily check-in lookup
        if (!$this->indexExists('attendances', 'attendances_user_id_date_index')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->index(['user_id', 'date']);
            });
        }

        // Leaves: filtered queries by user and status
        if (!$this->indexExists('leaves', 'leaves_user_id_status_index')) {
            Schema::table('leaves', function (Blueprint $table) {
                $table->index(['user_id', 'status']);
            });
        }

        // Approval steps: prevent duplicate step orders per flow
        if (!$this->indexExists('approval_steps', 'approval_steps_approval_flow_id_step_order_unique')) {
            Schema::table('approval_steps', function (Blueprint $table) {
                $table->unique(['approval_flow_id', 'step_order']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->indexExists('attendances', 'attendances_user_id_date_index')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'date']);
            });
        }

        if ($this->indexExists('leaves', 'leaves_user_id_status_index')) {
            Schema::table('leaves', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'status']);
            });
        }

        if ($this->indexExists('approval_steps', 'approval_steps_approval_flow_id_step_order_unique')) {
            Schema::table('approval_steps', function (Blueprint $table) {
                $table->dropUnique(['approval_flow_id', 'step_order']);
            });
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return collect(Schema::getIndexes($table))
                ->contains(fn (array $index) => ($index['name'] ?? null) === $indexName);
        }

        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
