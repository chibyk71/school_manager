<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the book_orders table with school scoping and soft delete support.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('book_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('book_list_id')->constrained('book_lists')->onDelete('cascade');
            $table->foreignUuid('student_id')->constrained('students')->onDelete('cascade');
            $table->date('order_date');
            $table->date('return_date')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'returned'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the book_orders table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('book_orders');
    }
};
