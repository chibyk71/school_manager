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
            $table->uuid('id')->primary()->comment('Primary key for the transaction');
            $table->foreignUuid('payable_id')->nullable()->comment('Polymorphic relation ID (e.g., Fee, Expense)');
            $table->string('payable_type')->nullable()->comment('Polymorphic relation type (e.g., App\Models\Finance\Fee)');
            $table->foreignUuid('school_id')->constrained('schools')->onDelete('cascade')->comment('The school associated with this transaction');
            $table->foreignUuid('school_section_id')->nullable()->constrained('school_sections')->onDelete('cascade')->comment('The school section associated with this transaction, if applicable');
            $table->decimal('amount', 15, 2)->comment('Amount of the transaction');
            $table->text('description')->nullable()->comment('Details or notes about the transaction');
            $table->date('transaction_date')->comment('Date of the transaction');
            $table->string('reference_number')->nullable()->comment('Reference number for the transaction');
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->onDelete('restrict')->comment('User who recorded the transaction');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete column for recoverable deletion');
            $table->index(['school_id', 'reference_number'])->comment('Index for efficient querying');
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
