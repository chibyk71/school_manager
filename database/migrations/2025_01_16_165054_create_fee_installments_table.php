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
        Schema::create('fee_installments', function (Blueprint $table) {
            $table->id()->comment('Primary key for the fee installment');
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade')->comment('The school associated with this fee installment');
            $table->foreignId('fee_id')->constrained('fees')->onDelete('cascade')->comment('The fee associated with this installment');
            $table->integer('no_of_installment')->comment('Number of the installment (e.g., 1 for first installment)');
            $table->decimal('initial_amount_payable', 15, 2)->nullable()->comment('Initial amount payable before installments start');
            $table->date('due_date')->comment('Due date for the installment payment');
            $table->json('options')->nullable()->comment('Additional JSON options for the installment');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete column for recoverable deletion');
            $table->index(['school_id', 'fee_id'])->comment('Index for efficient querying by school and fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_installments');
    }
};