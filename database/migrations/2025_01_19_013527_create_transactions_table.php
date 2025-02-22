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
            $table->id();
            $table->string('transaction_type'); // 'income' or 'expense'
            $table->foreignId('payable_id')->nullable(); // Polymorphic relation (e.g., FeePayment, SalaryPayment)
            $table->string('payable_type')->nullable(); // Model type for the payable
            $table->string('category'); // E.g., 'tuition', 'salary', 'transport', 'miscellaneous'
            $table->string('school_id')->index();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete(); // Tenant scoping
            $table->foreignId('school_section_id')->nullable()->constrained()->cascadeOnDelete(); // Tenant scoping
            $table->decimal('amount', 15, 2); // Amount of the transaction
            $table->string('payment_method')->nullable(); // 'cash', 'bank transfer', 'card', etc.
            $table->text('description')->nullable(); // Details or notes about the transaction
            $table->date('transaction_date'); // Date of the transaction
            $table->string('reference_number')->nullable(); // Reference number for the transaction
            $table->foreignUuid('recorded_by')->nullable()->constrained('users'); // User who recorded the transaction
            $table->timestamps();
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
