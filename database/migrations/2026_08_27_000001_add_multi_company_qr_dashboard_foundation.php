<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'code')) {
                $table->string('code')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('companies', 'status')) {
                $table->string('status')->default('active')->after('country');
            }
            if (!Schema::hasColumn('companies', 'timezone')) {
                $table->string('timezone')->default('Asia/Jakarta')->after('status');
            }
            if (!Schema::hasColumn('companies', 'currency')) {
                $table->string('currency', 8)->default('IDR')->after('timezone');
            }
            if (!Schema::hasColumn('companies', 'parent_company_id')) {
                $table->foreignId('parent_company_id')->nullable()->after('currency')->constrained('companies')->nullOnDelete();
            }
        });

        Schema::create('user_company_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('scope_role')->default('member');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'company_id']);
            $table->index(['company_id', 'scope_role']);
        });

        $companyTables = [
            'employees',
            'departments',
            'positions',
            'locations',
            'work_schedules',
            'attendances',
            'leaves',
            'payrolls',
            'reimbursements',
            'assets',
            'employee_documents',
            'kpis',
            'training_programs',
            'competencies',
        ];

        foreach ($companyTables as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
                $table->index('company_id', $tableName . '_company_id_index');
            });
        }

        Schema::create('qr_attendance_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('work_schedule_id')->nullable()->constrained('work_schedules')->nullOnDelete();
            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->string('purpose')->default('attendance');
            $table->string('token_hash', 128)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'qr_token_id')) {
                $table->foreignId('qr_token_id')->nullable()->after('user_id')->constrained('qr_attendance_tokens')->nullOnDelete();
            }
            if (!Schema::hasColumn('attendances', 'location_id')) {
                $table->foreignId('location_id')->nullable()->after('qr_token_id')->constrained('locations')->nullOnDelete();
            }
            if (!Schema::hasColumn('attendances', 'validation_status')) {
                $table->string('validation_status')->default('gps')->after('status');
            }
        });

        Schema::create('dashboard_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name')->default('Dashboard');
            $table->string('scope')->default('self');
            $table->json('layout_json')->nullable();
            $table->json('filters_json')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'scope']);
            $table->index(['role_id', 'company_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_configs');

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                foreach (['qr_token_id', 'location_id'] as $column) {
                    if (Schema::hasColumn('attendances', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
                if (Schema::hasColumn('attendances', 'validation_status')) {
                    $table->dropColumn('validation_status');
                }
            });
        }

        Schema::dropIfExists('qr_attendance_tokens');

        $companyTables = [
            'employees',
            'departments',
            'positions',
            'locations',
            'work_schedules',
            'attendances',
            'leaves',
            'payrolls',
            'reimbursements',
            'assets',
            'employee_documents',
            'kpis',
            'training_programs',
            'competencies',
        ];

        foreach ($companyTables as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }

        Schema::dropIfExists('user_company_access');

        Schema::table('companies', function (Blueprint $table) {
            foreach (['parent_company_id'] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach (['code', 'status', 'timezone', 'currency'] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
