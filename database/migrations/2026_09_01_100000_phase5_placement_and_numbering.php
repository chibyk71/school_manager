<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Placement & Registration Numbers
 *
 * - id_sequences: DB-backed counters (authoritative)
 * - student_session_placements: history-friendly fields; drop unique(student,session)
 * - registration_number_histories: immutable assignment history
 * - registration_number_assignments: CURRENT assignments — unique(school, scope_key, number) + unique(school, student)
 * - students: admission_number immutability trigger (uq already exists as uq_admission_number_per_school)
 *
 * placement_id remains unsignedBigInteger matching student_session_placements.id (integer PK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->uuid('school_id')->nullable();
            $table->string('scope_key', 191)->default('');
            $table->unsignedInteger('year')->default(0);
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
            $table->unique(['type', 'school_id', 'scope_key', 'year'], 'uq_id_sequences_scope');
            $table->index(['school_id', 'type'], 'idx_id_sequences_school_type');
        });

        Schema::table('student_session_placements', function (Blueprint $table) {
            $table->foreignUuid('enrollment_id')
                ->nullable()
                ->after('student_id')
                ->constrained('enrollments')
                ->nullOnDelete();
            $table->string('registration_number', 64)->nullable()->after('class_section_id');
            $table->boolean('capacity_override_used')->default(false)->after('notes');
            $table->uuid('placed_by')->nullable()->after('capacity_override_used');
            $table->json('meta')->nullable()->after('placed_by');
        });

        $this->dropIndexIfExists('student_session_placements', 'student_session_placements_student_id_academic_session_id_unique');
        $this->dropIndexIfExists('student_session_placements', 'uq_placement_student_session');

        Schema::table('student_session_placements', function (Blueprint $table) {
            $table->index(['student_id', 'academic_session_id', 'is_current'], 'idx_placement_student_session_current');
            $table->index(['class_section_id', 'is_current', 'left_at'], 'idx_placement_section_active');
            $table->index(['enrollment_id'], 'idx_placement_enrollment');
            $table->index(['registration_number'], 'idx_placement_registration_number');
        });

        Schema::create('registration_number_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->uuid('school_id');
            $table->foreignUuid('enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->unsignedBigInteger('placement_id')->nullable();
            $table->string('registration_number', 64);
            $table->string('scope_key', 191)->nullable();
            $table->foreignUuid('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->foreignUuid('class_level_id')->nullable()->constrained('class_levels')->nullOnDelete();
            $table->foreignUuid('class_section_id')->nullable()->constrained('class_sections')->nullOnDelete();
            $table->string('reason', 64)->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->uuid('assigned_by')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'effective_to'], 'idx_regnum_hist_student_active');
            $table->index(['school_id', 'registration_number'], 'idx_regnum_hist_school_number');
            $table->index(['school_id', 'scope_key', 'registration_number'], 'idx_regnum_hist_scope');
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('placement_id')->references('id')->on('student_session_placements')->nullOnDelete();
        });

        Schema::create('registration_number_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('school_id');
            $table->string('scope_key', 191);
            $table->string('registration_number', 64);
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedBigInteger('history_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['school_id', 'scope_key', 'registration_number'],
                'uq_regnum_assignment_active'
            );
            $table->unique(
                ['school_id', 'student_id'],
                'uq_regnum_assignment_student'
            );
            $table->index(['student_id'], 'idx_regnum_assignment_student');
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('history_id')->references('id')->on('registration_number_histories')->nullOnDelete();
        });

        // Admission number uniqueness already exists as uq_admission_number_per_school
        // (create_students_table). Do not create a duplicate unique index here.

        $this->installAdmissionNumberImmutabilityTrigger();
    }

    public function down(): void
    {
        // Do not drop uq_admission_number_per_school — it predates Phase 5.
        $this->dropAdmissionNumberImmutabilityTrigger();

        Schema::dropIfExists('registration_number_assignments');
        Schema::dropIfExists('registration_number_histories');

        Schema::table('student_session_placements', function (Blueprint $table) {
            $table->dropIndex('idx_placement_student_session_current');
            $table->dropIndex('idx_placement_section_active');
            $table->dropIndex('idx_placement_enrollment');
            $table->dropIndex('idx_placement_registration_number');
            $table->dropConstrainedForeignId('enrollment_id');
            $table->dropColumn([
                'registration_number',
                'capacity_override_used',
                'placed_by',
                'meta',
            ]);
        });

        Schema::table('student_session_placements', function (Blueprint $table) {
            $table->unique(['student_id', 'academic_session_id'], 'uq_placement_student_session');
        });

        Schema::dropIfExists('id_sequences');
    }

    /**
     * Prevent UPDATE of students.admission_number once a non-null value is set.
     * Application-level Eloquent guard remains; this is the DB integrity layer.
     */
    protected function installAdmissionNumberImmutabilityTrigger(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_student_admission_number_change()
RETURNS trigger AS $$
BEGIN
    IF OLD.admission_number IS NOT NULL
       AND NEW.admission_number IS DISTINCT FROM OLD.admission_number THEN
        RAISE EXCEPTION 'admission_number is immutable once assigned';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
            DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_students_admission_number_immutable ON students;
CREATE TRIGGER trg_students_admission_number_immutable
BEFORE UPDATE ON students
FOR EACH ROW
EXECUTE PROCEDURE prevent_student_admission_number_change();
SQL);
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_students_admission_number_immutable');
            DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_students_admission_number_immutable
BEFORE UPDATE ON students
FOR EACH ROW
BEGIN
    IF OLD.admission_number IS NOT NULL
       AND NEW.admission_number <> OLD.admission_number THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'admission_number is immutable once assigned';
    END IF;
END
SQL);
            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_students_admission_number_immutable');
            DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_students_admission_number_immutable
BEFORE UPDATE ON students
FOR EACH ROW
WHEN OLD.admission_number IS NOT NULL
 AND NEW.admission_number IS NOT OLD.admission_number
BEGIN
    SELECT RAISE(ABORT, 'admission_number is immutable once assigned');
END;
SQL);
        }
    }

    protected function dropAdmissionNumberImmutabilityTrigger(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_students_admission_number_immutable ON students');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_student_admission_number_change()');
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_students_admission_number_immutable');
    }

    protected function dropIndexIfExists(string $table, string $index): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            try {
                Schema::table($table, fn (Blueprint $b) => $b->dropUnique($index));
            } catch (\Throwable) {
                try {
                    Schema::table($table, fn (Blueprint $b) => $b->dropIndex($index));
                } catch (\Throwable) {
                }
            }

            return;
        }

        try {
            Schema::table($table, fn (Blueprint $b) => $b->dropUnique($index));
        } catch (\Throwable) {
            try {
                Schema::table($table, fn (Blueprint $b) => $b->dropIndex($index));
            } catch (\Throwable) {
            }
        }
    }
};
