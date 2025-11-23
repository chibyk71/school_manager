<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fee_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('fee_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete(); // Student
            $table->foreignUuid('term_id')->constrained()->cascadeOnDelete();

            $table->decimal('original_amount', 15, 2);
            $table->decimal('concession_amount', 15, 2)->default(0); // Total concession applied
            $table->decimal('amount_due', 15, 2); // original - concession
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->storedAs('amount_due - amount_paid');

            $table->date('due_date');
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue'])->default('pending');

            $table->timestamps();
            $table->softDeletes();

            // Critical indexes
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'due_date']);
            $table->unique(['fee_id', 'user_id', 'term_id']); // One assignment per fee per student per term
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_assignments');
    }
};
