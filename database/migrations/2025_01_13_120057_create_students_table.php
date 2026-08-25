<?php

/**
 * Migration: 2026_03_30_000002_create_students_table.php
 *
 * Creates the 'students' table — the canonical school-scoped enrollment record for a person within a specific school.
 *
 * ── Core Design Principle ────────────────────────────────────────────────────
 *
 * This table stores ONLY student-specific role and enrollment data.
 * ALL personal information (name, date_of_birth, gender, photo, phone, etc.)
 * lives in the central 'profiles' table.
 *
 * Relationship:
 *   Student belongsTo Profile
 *   Profile hasMany Student  (one person can have student records in multiple schools)
 *
 * This supports your tenant rules:
 *   - One Profile → Multiple Student records (different schools or historical roles)
 *   - Same person can be a Student in School A and a Guardian in School B
 *   - Transfer = old Student record status → 'transferred' + new Student record in target school
 *   - No data duplication across roles/schools.
 *
 * ── What This Table Tracks ───────────────────────────────────────────────────
 *
 * - School membership (school_id)
 * - Link to central Profile
 * - Admission metadata (admission_number, admission_date)
 * - Lifecycle status (made dynamic via HasDynamicEnum trait)
 * - Origin from StudentApplication (if any)
 * - Transfer / previous school notes
 * - Admin notes
 *
 * ── What This Table Does NOT Track ───────────────────────────────────────────
 *
 * - Personal data → delegated to Profile model
 * - Current class placement → student_session_placements table
 * - Guardian links → guardian_student pivot table
 * - Custom fields → custom_field_responses (polymorphic via HasCustomFields trait)
 * - Addresses → via HasAddress trait on Profile (or directly on Student if needed)
 *
 * ── Status Handling ──────────────────────────────────────────────────────────
 *
 * Status is a simple string column (NOT a database ENUM).
 * School-specific options are managed via the HasDynamicEnum trait on the Student model.
 * This allows each school to customize statuses (e.g. add "probation", "boarding", "alumni") without migration changes.
 *
 * Default global options will be provided in the Student model or via DynamicEnum seeder.
 *
 * ── Multi-Tenancy ────────────────────────────────────────────────────────────
 *
 * - Uses BelongsToSchool trait → automatic school_id assignment + global scope.
 * - Student::all() never leaks data across schools.
 *
 * Fits into the Student Management Module:
 * - Created during admission (from StudentApplication or direct enrollment wizard).
 * - Used heavily in DataTables (via HasTableQuery), modals, and services (StudentEnrollmentService, StudentStatusService, StudentTransferService).
 * - Integrates with HasCustomFields, HasAddress (via Profile), and HasDynamicEnum.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {

            // ── Primary Key ───────────────────────────────────────────────────
            $table->uuid('id')->primary();

            // ── School Scope ──────────────────────────────────────────────────
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->restrictOnDelete(); // Prevent deleting school with active students

            // ── Link to Central Profile (Personal Data) ───────────────────────
            // This is the correct relationship based on your Profile model (hasMany students)
            $table->foreignUuid('profile_id')
                ->constrained('profiles')
                ->restrictOnDelete(); // A profile with student records cannot be easily deleted

            // ── Admission Record ──────────────────────────────────────────────
            $table->string('admission_number', 50)->nullable();
            $table->date('admission_date')->nullable();

            // Admission type as string (can be made dynamic later if schools need custom types)
            $table->string('admission_type', 30)->default('fresh');

            // ── Lifecycle Status (Dynamic via HasDynamicEnum) ─────────────────
            // NOT an ENUM → allows per-school customization through DynamicEnum system
            $table->string('status', 50)->default('admitted');

            // ── Status Change Tracking ────────────────────────────────────────
            $table->text('status_reason')->nullable();
            $table->date('status_date')->nullable();
            $table->date('status_until')->nullable();        // For temporary statuses like suspension
            $table->foreignUuid('status_changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ── Transfer / Previous School Info ───────────────────────────────
            $table->string('previous_school', 255)->nullable();
            $table->string('previous_class', 100)->nullable();
            $table->string('previous_school_address', 500)->nullable();

            // For outgoing transfers
            $table->string('transfer_destination', 255)->nullable();
            $table->string('transfer_certificate_number', 100)->nullable();

            // ── Application Origin ────────────────────────────────────────────
            $table->foreignUuid('application_id')
                ->nullable()
                ->constrained('student_applications')
                ->nullOnDelete();

            // ── Internal Notes ────────────────────────────────────────────────
            $table->text('notes')->nullable();

            // ── Timestamps & Soft Deletes ─────────────────────────────────────
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ───────────────────────────────────────────────────────
            // Unique admission number per school
            $table->unique(['school_id', 'admission_number'], 'uq_admission_number_per_school');

            // Common queries: school + status, school + profile
            $table->index(['school_id', 'status'], 'idx_students_school_status');
            $table->index(['school_id', 'profile_id'], 'idx_students_school_profile');

            // Fast lookup from application
            $table->index('application_id', 'idx_students_application');

            // Soft delete + school filtering
            $table->index(['school_id', 'deleted_at'], 'idx_students_school_deleted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
