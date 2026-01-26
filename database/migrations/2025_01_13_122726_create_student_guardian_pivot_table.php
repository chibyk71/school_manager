<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: 2026_01_24_create_student_guardian_table.php
 *
 * This migration creates the pivot table 'student_guardian' that defines the many-to-many relationship
 * between students (enrollments) and guardians (responsible persons).
 *
 * Features / Problems Solved:
 * - Enables flexible assignment: One student can have multiple guardians (e.g., father, mother, aunt);
 *   one guardian can be responsible for multiple students (siblings, cousins, wards).
 * - Stores relationship context per link: 'relationship_type' (e.g., father, mother, legal_guardian)
 *   allows precise tracking and display (via HasDynamicEnum or config array).
 * - Supports additional metadata: 'is_primary' flag (for contact priority), 'notes' (e.g., custody notes,
 *   preferred contact time), and 'created_at'/'updated_at' for auditing when the relationship was added/updated.
 * - Cascade on delete: When a student or guardian record is deleted (soft or hard), the pivot entries are
 *   automatically removed to maintain referential integrity without leaving dangling relationships.
 * - Unique constraint on (guardian_id, student_id): Prevents duplicate assignments of the same guardian
 *   to the same student (avoids data errors from accidental double-linking).
 * - Indexes on foreign keys: Ensures fast joins when fetching guardians for a student or students for a guardian.
 * - No direct 'school_id' here: Relationship scoping is inherited from the student record (which is already
 *   school- and section-scoped via BelongsToSchool).
 * - Prepares for future extensions: Nullable fields like 'authorization_level' or 'document_verified_at'
 *   can be added later without breaking existing data.
 * - UUID foreign keys: Consistent with students and guardians tables (using foreignUuid).
 *
 * Fits into the User Management Module:
 * - Completes the guardian-student linkage architecture:
 *     Profile → Guardian → (pivot) student_guardian ← Student ← Profile
 * - Used by:
 *   - StudentController / GuardianController when assigning guardians during enrollment or via dedicated modal.
 *   - AssignGuardianModal.vue (frontend) for searching existing guardians or creating new ones inline.
 *   - Data tables: StudentsTable.vue can show guardian count or primary guardian name; GuardiansTable.vue
 *     can list assigned students.
 * - Business logic:
 *   - Primary guardian concept (is_primary = true) can drive contact priority in notifications or emergency protocols.
 *   - Relationship_type powers dropdowns/radios in forms (via HasDynamicEnum or config).
 * - Security & Integrity:
 *   - Cascade delete prevents orphaned pivot records.
 *   - Unique constraint avoids logical duplicates.
 *   - No user-modifiable timestamps on pivot (only system-managed) reduces attack surface.
 * - Frontend integration:
 *   - In StudentEnrollmentModal.vue and AssignGuardianModal.vue: use pivot data to display current guardians
 *     and allow add/remove operations.
 *   - Inertia responses return pivot data nested under student/guardian for easy display.
 * - Extensibility:
 *   - Can later add soft deletes to pivot if history of past guardian relationships is needed.
 *   - Ready for custom pivot attributes via accessor/mutator or additional columns.
 */

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_guardian', function (Blueprint $table) {
            // No auto-incrementing id — composite key is sufficient for most pivot tables
            $table->foreignUuid('student_id')
                  ->constrained('students')
                  ->cascadeOnDelete();

            $table->foreignUuid('guardian_id')
                  ->constrained('guardians')
                  ->cascadeOnDelete();

            // Relationship context
            $table->string('relationship_type')->nullable(); // e.g., father, mother, aunt, legal_guardian (via HasDynamicEnum)
            $table->boolean('is_primary')->default(false);   // Primary contact / decision maker
            $table->text('notes')->nullable();               // e.g., "Prefers email", "Custody shared", "Emergency only"

            // Auditing timestamps
            $table->timestamps();

            // Composite primary key + unique constraint
            $table->primary(['student_id', 'guardian_id']);
            $table->unique(['guardian_id', 'student_id'], 'guardian_student_unique'); // Symmetric uniqueness

            // Performance indexes (though primary key covers most cases)
            $table->index('student_id');
            $table->index('guardian_id');
            $table->index('is_primary'); // For queries like "get primary guardians"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_guardian');
    }
};
