<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reimbursements', function (Blueprint $table) {
            $table->id();

            // 🔗 Relasi ke employee
            $table->foreignId('employee_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // 📋 Data reimbursement
            $table->string('title');
            $table->text('description')->nullable();

            // 💰 Amount & kategori
            $table->decimal('amount', 15, 2);
            $table->enum('category', [
                'travel',
                'medical',
                'office_supplies',
                'training',
                'meal',
                'accommodation',
                'transportation',
                'other'
            ])->default('other');

            // 📊 Status workflow
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'paid'
            ])->default('draft');

            // 📅 Tanggal
            $table->date('expense_date');
            $table->date('submitted_at')->nullable();
            $table->date('approved_at')->nullable();
            $table->date('paid_at')->nullable();

            // 👤 Approval
            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->text('approval_note')->nullable();

            // 📎 File attachment (receipt)
            $table->string('receipt_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reimbursements');
    }
};