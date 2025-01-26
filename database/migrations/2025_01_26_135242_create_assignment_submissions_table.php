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
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('student_id')->constrained('students');
            $table->foreignId('assignment_id')->constrained()->onDelete('cascade');
            $table->text('answer_text')->nullable();
            $table->decimal('mark_obtained')->nullable();
            $table->enum('status', ['draft','submitted', 'graded'])->default('submitted');
            $table->timestamp('submitted_at');
            $table->foreignUuid('graded_by')->nullable()->constrained('staff');
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
