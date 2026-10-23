<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: Enrollment may exist before Student identity.
 * - Make enrollments.student_id nullable
 * - Add enrollment_requirement_definitions (school-scoped)
 * - Add enrollment_requirement_instances (per-enrollment)
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->makeStudentIdNullable();
        $this->createRequirementDefinitionsTable();
        $this->createRequirementInstancesTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_requirement_instances');
        Schema::dropIfExists('enrollment_requirement_definitions');
    }

    protected function makeStudentIdNullable(): void
    {
        if (! Schema::hasTable('enrollments')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildEnrollmentsForSqlite();

            return;
        }

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->uuid('student_id')->nullable()->change();
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreign('student_id')
                ->references('id')
                ->on('students')
                ->nullOnDelete();
        });
    }

    protected function rebuildEnrollmentsForSqlite(): void
    {
        Schema::rename('enrollments', 'enrollments_old_phase4');

        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->restrictOnDelete();

            $table->uuid('student_id')->nullable();
            $table->foreign('student_id')
                ->references('id')
                ->on('students')
                ->nullOnDelete();

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

        $columns = [
            'id', 'school_id', 'student_id', 'academic_session_id', 'admission_id',
            'status', 'started_at', 'activated_at', 'withdrawn_at', 'transferred_out_at',
            'completed_at', 'notes', 'meta', 'created_at', 'updated_at', 'deleted_at',
        ];

        $colList = implode(', ', $columns);
        DB::statement("INSERT INTO enrollments ({$colList}) SELECT {$colList} FROM enrollments_old_phase4");

        Schema::drop('enrollments_old_phase4');
    }

    protected function createRequirementDefinitionsTable(): void
    {
        if (Schema::hasTable('enrollment_requirement_definitions')) {
            return;
        }

        Schema::create('enrollment_requirement_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->restrictOnDelete();

            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 40);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('config')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'code'], 'uq_enroll_req_def_school_code');
            $table->index(['school_id', 'is_active', 'sort_order'], 'idx_enroll_req_def_school_active');
        });
    }

    protected function createRequirementInstancesTable(): void
    {
        if (Schema::hasTable('enrollment_requirement_instances')) {
            return;
        }

        Schema::create('enrollment_requirement_instances', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('enrollment_id')
                ->constrained('enrollments')
                ->cascadeOnDelete();

            $table->foreignUuid('definition_id')
                ->constrained('enrollment_requirement_definitions')
                ->restrictOnDelete();

            $table->string('status', 20)->default('pending');

            $table->timestamp('satisfied_at')->nullable();
            $table->uuid('satisfied_by')->nullable();
            $table->timestamp('waived_at')->nullable();
            $table->uuid('waived_by')->nullable();
            $table->text('waiver_reason')->nullable();

            $table->uuid('document_id')->nullable();
            $table->string('external_reference')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['enrollment_id', 'definition_id'], 'uq_enroll_req_inst_enrollment_def');
            $table->index(['enrollment_id', 'status'], 'idx_enroll_req_inst_enrollment_status');
        });
    }
};
