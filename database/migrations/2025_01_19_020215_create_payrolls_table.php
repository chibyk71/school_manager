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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('salary_id')->constrained('salaries')->cascadeOnDelete();
            $table->decimal('bonus', 15, 2)->nullable();
            $table->decimal('deduction', 15, 2)->nullable();
            $table->decimal('net_salary', 15, 2);
            $table->date('payment_date');
            $table->text('description')->nullable();
            $table->enum('status', ['paid', 'unpaid'])->default('unpaid');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'user_id', 'salary_id', 'payment_date'], 'payrolls_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
