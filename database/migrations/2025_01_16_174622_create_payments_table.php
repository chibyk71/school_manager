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
            $table->uuid('id');
            $table->string('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('payment_method');
            $table->enum('payment_status', ['pending', 'success', 'failed']);
            $table->decimal('payment_amount');
            $table->string('payment_currency');
            $table->string('payment_reference');
            $table->dateTime('payment_date');
            $table->string('payment_description');
            $table->foreignId('fee_installment_detail_id')->nullable()->constrained('fee_installment_details')->onDelete('cascade');
            $table->foreignId('fee_id')->nullable()->constrained('fees')->onDelete('cascade');
            $table->timestamps();
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
