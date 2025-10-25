<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the book_lists table with school scoping and soft delete support.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('book_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('class_level_id')->constrained('class_levels')->onDelete('cascade');
            $table->foreignUuid('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->string('title')->index();
            $table->string('author');
            $table->string('isbn')->nullable();
            $table->string('edition')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the book_lists table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('book_lists');
    }
};