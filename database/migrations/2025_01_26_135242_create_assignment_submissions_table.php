<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the assignment_submissions table with all necessary fields and constraints.
     */
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('school_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('assignment_id')->constrained()->onDelete('cascade');
            $table->text('answer_text')->nullable();
            $table->decimal('mark_obtained', 5, 2)->nullable();
            $table->enum('status', ['draft', 'submitted', 'graded'])->default('submitted');
            $table->timestamp('submitted_at');
            $table->foreignUuid('graded_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the assignment_submissions table.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};