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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();

            // Polymorphic relationship: Fee, Expense, Payment, etc.
            $table->uuidMorphs('payable'); // payable_id + payable_type

            $table->string('transaction_type')->index(); // income, expense
            $table->string('category')->index(); // Tuition, Salary, Utilities, etc.
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2)->nullable(); // Optional: running balance

            $table->date('transaction_date');
            $table->text('description')->nullable();

            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique()->nullable(); // e.g. PAYSTACK-REF, EXP-001
            $table->json('meta')->nullable(); // Extra data: payment method, bank, etc.

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['school_id', 'transaction_date']);
            $table->index(['transaction_type', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
