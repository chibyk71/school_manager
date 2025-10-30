<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->cascadeOnDelete()
                ->comment('References the school the assignment belongs to');
            $table->foreignUuid('class_level_id')
                ->constrained('class_levels')
                ->cascadeOnDelete()
                ->comment('References the class level for the assignment');
            $table->foreignUuid('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete()
                ->comment('References the subject of the assignment');
            $table->string('title')
                ->comment('The title of the assignment');
            $table->text('description')
                ->nullable()
                ->comment('Optional description of the assignment');
            $table->foreignUuid('term_id')
                ->constrained('terms')
                ->cascadeOnDelete()
                ->comment('References the academic term');
            $table->unsignedInteger('total_mark')
                ->comment('The total mark for the assignment');
            $table->dateTime('due_date')
                ->comment('The due date and time for the assignment');
            $table->foreignUuid('teacher_id')
                ->constrained('staff')
                ->cascadeOnDelete()
                ->comment('References the teacher who created the assignment');
            $table->timestamps();
            $table->softDeletes()
                ->comment('Soft delete timestamp for the assignment');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
