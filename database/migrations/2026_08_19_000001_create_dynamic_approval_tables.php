<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('approval_flows')) {
            Schema::create('approval_flows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('name');
                $table->string('module');
                $table->string('request_type')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->decimal('min_amount', 15, 2)->nullable();
                $table->decimal('max_amount', 15, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['module', 'is_active']);
                $table->index(['company_id', 'module']);
            });
        } else {
            Schema::table('approval_flows', function (Blueprint $table) {
                if (!Schema::hasColumn('approval_flows', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable();
                }
                if (!Schema::hasColumn('approval_flows', 'request_type')) {
                    $table->string('request_type')->nullable();
                }
                if (!Schema::hasColumn('approval_flows', 'department_id')) {
                    $table->unsignedBigInteger('department_id')->nullable();
                }
                if (!Schema::hasColumn('approval_flows', 'min_amount')) {
                    $table->decimal('min_amount', 15, 2)->nullable();
                }
                if (!Schema::hasColumn('approval_flows', 'max_amount')) {
                    $table->decimal('max_amount', 15, 2)->nullable();
                }
                if (!Schema::hasColumn('approval_flows', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (!Schema::hasColumn('approval_flows', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
            });
        }

        if (!Schema::hasTable('approval_steps')) {
            Schema::create('approval_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('approval_flow_id')->constrained('approval_flows')->cascadeOnDelete();
                $table->unsignedInteger('step_order');
                $table->string('step_name');
                $table->string('approver_type');
                $table->unsignedBigInteger('role_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('position_id')->nullable();
                $table->boolean('is_required')->default(true);
                $table->timestamps();

                $table->index('approval_flow_id');
                $table->unique(['approval_flow_id', 'step_order']);
            });
        } else {
            Schema::table('approval_steps', function (Blueprint $table) {
                if (!Schema::hasColumn('approval_steps', 'step_name')) {
                    $table->string('step_name')->nullable();
                }
                if (!Schema::hasColumn('approval_steps', 'approver_type')) {
                    $table->string('approver_type')->default('role');
                }
                if (!Schema::hasColumn('approval_steps', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable();
                }
                if (!Schema::hasColumn('approval_steps', 'position_id')) {
                    $table->unsignedBigInteger('position_id')->nullable();
                }
                if (!Schema::hasColumn('approval_steps', 'is_required')) {
                    $table->boolean('is_required')->default(true);
                }
            });
        }

        if (!Schema::hasTable('approval_requests')) {
            Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_flow_id')->constrained('approval_flows');
            $table->string('approvable_type'); // polymorphic: App\Modules\Leave\Models\Leave, etc.
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedBigInteger('requester_id');
            $table->unsignedInteger('current_step')->default(1);
            $table->string('status')->default('pending'); // pending, in_progress, approved, rejected, returned, cancelled
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id']);
            $table->index(['requester_id', 'status']);
            $table->index('status');
            });
        }

        if (!Schema::hasTable('approval_histories')) {
            Schema::create('approval_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('approval_step_id')->constrained('approval_steps');
            $table->unsignedBigInteger('approver_id');
            $table->string('action'); // submitted, approved, rejected, returned, cancelled
            $table->text('comment')->nullable();
            $table->timestamp('action_at');
            $table->timestamps();

            $table->index('approval_request_id');
            $table->index('approver_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_histories');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_flows');
    }
};
