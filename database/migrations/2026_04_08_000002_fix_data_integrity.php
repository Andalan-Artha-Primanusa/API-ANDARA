<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix data integrity issues across multiple tables.
     */
    public function up(): void
    {
        // 1. Drop deprecated 'role' column from users table
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }

        // 2. Add unique constraint on user_profiles.user_id
        if (!$this->indexExists('user_profiles', 'user_profiles_user_id_unique')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                $table->unique('user_id');
            });
        }

        // 3. Add unique constraint on approval_flows.module
        if (!$this->indexExists('approval_flows', 'approval_flows_module_unique')) {
            Schema::table('approval_flows', function (Blueprint $table) {
                $table->unique('module');
            });
        }

        // 4. Fix attendances check_in/check_out column types
        Schema::table('attendances', function (Blueprint $table) {
            $table->dateTime('check_in')->nullable()->change();
            $table->dateTime('check_out')->nullable()->change();
        });

        // 5. Upgrade attendances [user_id, date] from index to unique
        if (!$this->indexExists('attendances', 'attendances_user_id_date_unique')) {
            // Drop the regular index if it exists
            if ($this->indexExists('attendances', 'attendances_user_id_date_index')) {
                Schema::table('attendances', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });

                Schema::table('attendances', function (Blueprint $table) {
                    $table->dropIndex(['user_id', 'date']);
                    $table->unique(['user_id', 'date'], 'attendances_user_id_date_unique');
                });

                Schema::table('attendances', function (Blueprint $table) {
                    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                });
            } else {
                // Just add the unique constraint directly
                Schema::table('attendances', function (Blueprint $table) {
                    $table->unique(['user_id', 'date'], 'attendances_user_id_date_unique');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('employee')->after('password');
            });
        }

        if ($this->indexExists('user_profiles', 'user_profiles_user_id_unique')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                $table->dropUnique(['user_id']);
            });
        }

        if ($this->indexExists('approval_flows', 'approval_flows_module_unique')) {
            Schema::table('approval_flows', function (Blueprint $table) {
                $table->dropUnique(['module']);
            });
        }

        Schema::table('attendances', function (Blueprint $table) {
            $table->time('check_in')->nullable()->change();
            $table->time('check_out')->nullable()->change();
        });

        if ($this->indexExists('attendances', 'attendances_user_id_date_unique')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::table('attendances', function (Blueprint $table) {
                $table->dropUnique('attendances_user_id_date_unique');
                $table->index(['user_id', 'date']);
            });

            Schema::table('attendances', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
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
