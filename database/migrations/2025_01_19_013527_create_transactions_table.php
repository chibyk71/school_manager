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
            $table->id()->comment('Primary key for the transaction');
            $table->string('transaction_type')->comment('Type of transaction: income or expense');
            $table->foreignId('payable_id')->nullable()->comment('Polymorphic relation ID (e.g., Fee, Expense)');
            $table->string('payable_type')->nullable()->comment('Polymorphic relation type (e.g., App\Models\Finance\Fee)');
            $table->string('category')->comment('Category of the transaction (e.g., tuition, salary, utilities)');
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade')->comment('The school associated with this transaction');
            $table->foreignId('school_section_id')->nullable()->constrained('school_sections')->onDelete('cascade')->comment('The school section associated with this transaction, if applicable');
            $table->decimal('amount', 15, 2)->comment('Amount of the transaction');
            $table->string('payment_method')->nullable()->comment('Payment method: cash, bank transfer, card, etc.');
            $table->text('description')->nullable()->comment('Details or notes about the transaction');
            $table->date('transaction_date')->comment('Date of the transaction');
            $table->string('reference_number')->nullable()->comment('Reference number for the transaction');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('restrict')->comment('User who recorded the transaction');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete column for recoverable deletion');
            $table->index(['school_id', 'transaction_type', 'category'])->comment('Index for efficient querying');
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