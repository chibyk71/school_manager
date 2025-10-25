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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade')->comment('The school associated with this expense');
            $table->foreignId('recorded_by')->constrained('users')->onDelete('restrict')->comment('The user who recorded the expense');
            $table->decimal('amount', 15, 2)->comment('The expense amount');
            $table->string('category')->comment('The expense category (e.g., utilities, salaries)');
            $table->string('description')->nullable()->comment('Optional description of the expense');
            $table->date('expense_date')->comment('The date the expense was incurred');
            $table->string('status')->default('pending')->comment('Expense status (e.g., pending, approved, rejected)');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete column for recoverable deletion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};