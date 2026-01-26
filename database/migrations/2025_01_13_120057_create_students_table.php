<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: 2026_01_24_create_students_table.php
 *
 * This migration creates the 'students' table, which represents time-bound enrollment records for students.
 * Each record captures a specific enrollment period (e.g., in a school section), allowing for re-enrollments when students move sections or schools, even within the same tenant.
 *
 * Features / Problems Solved:
 * - Treats "student" as a role/enrollment rather than a lifelong identity: New record on re-enrollment (e.g., primary to secondary transition), with potentially different data (e.g., new admission number, updated status).
 * - Supports multi-school and multi-section scoping: Foreign keys to school and section ensure data isolation (e.g., a student in School A's primary section is separate from School B's secondary).
 * - Includes essential enrollment data: admission_number (unique per enrollment), dates (enrollment/graduation), status (via HasDynamicEnum for active/graduated/suspended).
 * - Soft deletes for archiving: Retain historical enrollments without hard deletion (e.g., for alumni tracking or audits).
 * - Foreign key constraints with cascade on delete: If a profile, school, or section is deleted (rare, via soft delete), related students are handled safely (set null or restrict as appropriate to prevent orphans).
 * - Indexes on key fields (e.g., admission_number, profile_id) for efficient queries (e.g., searching students by name via profile join or by school/section).
 * - Prepares for traits: BelongsToSchool (auto-scopes queries to current school), HasDynamicEnum (for status), HasCustomFields (school-specific extensions like allergies, special needs), SoftDeletes.
 * - UUID primary key: For globally unique identifiers, useful in distributed systems or exports.
 * - Timestamps for auditing creation/updates.
 * - Nullable fields for flexibility (e.g., graduation_date only set post-graduation; current_class_id for future integration with Class model).
 * - Data integrity: Unique constraint on admission_number per school to prevent duplicates within a school.
 * - Performance: Composite indexes (e.g., school_id + section_id) for filtered listings in data tables.
 * - Error handling: Constraints prevent invalid inserts (e.g., non-existent profile/school/section).
 *
 * Fits into the User Management Module:
 * - Links to Profile (belongsTo) for shared personal data, enabling one person (profile) to have multiple enrollments (students) over time/schools.
 * - Scoped via BelongsToSchool and belongsTo Section for multi-tenant safety: Queries auto-filter to current school (via global scope).
 * - Created bundled with profiles: StudentController handles "enroll student" (create Profile if not exists + Student); re-enrollment creates new Student linked to existing Profile.
 * - Integrates with Guardian: BelongsToMany Guardian via pivot (student_guardian) for assigning guardians with relationship types.
 * - Frontend integration: Used in StudentsTable.vue (data table with HasTableQuery backend for listings/filters), StudentEnrollmentModal.vue (modal for creation/re-enrollment using useCustomFields composable).
 * - Backend integration: StudentController for CRUD; EnrollmentService.php for complex logic (e.g., graduate: set status/graduation_date, create new Student for re-enrollment).
 * - Extensibility: Custom fields for school-specific student data (e.g., uniform size); dynamic enums for status.
 * - Alignment with stack: Laravel best practices (constraints, indexes); no direct UI, but supports Inertia responses for modals/tables.
 */

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('profiles')->onDelete('delete'); // Link to user profile; set null if user deleted (rare)
            $table->foreignUuid('school_id')->constrained('schools')->onDelete('restrict'); // Prevent deleting school with active students
            $table->foreignUuid('school_section_id')->constrained('school_sections')->onDelete('restrict'); // Scoped to school section (e.g., primary/secondary)
            $table->string('admission_number')->unique(); // Unique per enrollment; consider school-scoped unique if needed
            $table->date('enrollment_date');
            $table->date('graduation_date')->nullable();
            $table->string('status')->default('active'); // e.g., active, graduated, suspended (via HasDynamicEnum)
            $table->text('notes')->nullable(); // Enrollment-specific notes
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('profile_id'); // For joining with profiles
            $table->index('admission_number'); // For quick lookups
            $table->index(['school_id', 'school_section_id']); // For scoped listings (e.g., students in current school/section)
            $table->unique(['school_id', 'admission_number']); // Prevent duplicate admission numbers within a school
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
