<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->restrictOnDelete();

            $table->foreignUuid('student_id')
                ->constrained('students')
                ->restrictOnDelete();

            $table->foreignUuid('academic_session_id')
                ->constrained('academic_sessions')
                ->restrictOnDelete();

            $table->foreignUuid('admission_id')
                ->nullable()
                ->constrained('admissions')
                ->nullOnDelete();

            $table->string('status', 40)->default('draft');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('transferred_out_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('admission_id', 'uq_enrollments_admission_id');

            $table->index(['school_id', 'status'], 'idx_enrollments_school_status');
            $table->index(['school_id', 'academic_session_id'], 'idx_enrollments_school_session');
            $table->index(['student_id', 'academic_session_id'], 'idx_enrollments_student_session');
            $table->index(['school_id', 'deleted_at'], 'idx_enrollments_school_deleted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
