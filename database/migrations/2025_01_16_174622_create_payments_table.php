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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID primary key for the payment');
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade')->comment('The school associated with this payment');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('The user (student) who made the payment');
            $table->string('payment_method')->comment('Method used for payment (e.g., bank, mobile, cash)');
            $table->enum('payment_status', ['pending', 'success', 'failed'])->default('pending')->comment('Status of the payment');
            $table->decimal('payment_amount', 15, 2)->comment('Amount of the payment');
            $table->string('payment_currency')->comment('Currency of the payment (e.g., NGN, USD)');
            $table->string('payment_reference')->unique()->comment('Unique reference for the payment');
            $table->dateTime('payment_date')->comment('Date and time the payment was made');
            $table->string('payment_description')->comment('Description of the payment');
            $table->foreignId('fee_installment_detail_id')->nullable()->constrained('fee_installment_details')->onDelete('cascade')->comment('The fee installment detail associated with this payment');
            $table->foreignId('fee_id')->nullable()->constrained('fees')->onDelete('cascade')->comment('The fee associated with this payment');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete column for recoverable deletion');
            $table->index(['school_id', 'user_id', 'fee_id'])->comment('Index for efficient querying');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};