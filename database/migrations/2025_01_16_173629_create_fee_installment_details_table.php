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
        Schema::create('fee_installment_details', function (Blueprint $table) {
            $table->id()->comment('Primary key for the fee installment detail');
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade')->comment('The school associated with this fee installment detail');
            $table->foreignId('fee_installment_id')->constrained('fee_installments')->onDelete('cascade')->comment('The fee installment associated with this detail');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('The student associated with this fee installment detail');
            $table->decimal('amount', 15, 2)->comment('Amount of the installment');
            $table->date('due_date')->comment('Due date for the installment payment');
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending')->comment('Status of the installment: pending, paid, or overdue');
            $table->date('paid_date')->nullable()->comment('Date when the installment was paid');
            $table->decimal('punishment', 15, 2)->nullable()->comment('Penalty amount for late payment');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete column for recoverable deletion');
            $table->index(['school_id', 'fee_installment_id', 'user_id'])->comment('Index for efficient querying');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_installment_details');
    }
};