<?php

/**
 * Migration: 2026_03_30_000004_create_guardian_student_table.php
 *
 * Creates the guardian_student pivot table — the enriched many-to-many relationship
 * between Guardian records and Student records.
 *
 * ── Core Design Principle ────────────────────────────────────────────────────
 *
 * A single Profile (person) can act as a Guardian for multiple Students.
 * A Student can have multiple Guardians (father, mother, uncle, etc.).
 *
 * Relationship Flow (aligned with Profile model):
 *   Profile → hasMany Guardian (school-scoped guardian records)
 *   Guardian → belongsTo Profile (personal data)
 *   Student ↔ Guardian via this pivot table (guardian_student)
 *
 * This pivot carries important operational metadata specific to the
 * guardian-student link, which is critical for Nigerian schools:
 *   - Relationship type (father, mother, guardian, etc.)
 *   - Primary contact designation
 *   - Pickup authorization (safety feature)
 *   - Portal access rights
 *   - Emergency contact priority
 *
 * Guardian records themselves are school-scoped (via BelongsToSchool trait on Guardian model),
 * while the underlying personal data lives in the shared Profile.
 *
 * ── Why This Pivot Is Rich ───────────────────────────────────────────────────
 *
 * - is_primary_contact: Only one guardian per student should be primary.
 *   Enforced in StudentGuardianService (not DB constraint for flexibility).
 * - can_pickup: Important for primary/early years schools (pickup authorization).
 * - is_emergency_contact + emergency_contact_priority: Allows different people
 *   for daily communication vs emergency situations.
 * - relationship: Stored as string to support HasDynamicEnum per school.
 *
 * ── Multi-Tenancy & Scoping ──────────────────────────────────────────────────
 *
 * No school_id on the pivot — school context comes from both Student and Guardian records.
 * This prevents cross-school guardian-student links within the same tenant.
 *
 * Fits into the Student Management Module:
 * - Used by StudentGuardianController and StudentGuardianService.
 * - Accessed via Student::guardians() and Guardian::students() relationships.
 * - Powers primary contact logic, pickup lists, emergency notifications,
 *   and parent portal access control.
 * - Integrates with HasDynamicEnum for relationship types.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('guardian_student', function (Blueprint $table) {

            // ── Primary Key ───────────────────────────────────────────────────
            $table->id();

            // ── Core Relationships ────────────────────────────────────────────
            $table->foreignUuid('student_id')
                ->constrained('students')
                ->cascadeOnDelete(); // Remove links if student is hard-deleted

            $table->foreignUuid('guardian_id')
                ->constrained('guardians')
                ->cascadeOnDelete(); // Remove links if guardian is hard-deleted

            // ── Relationship Metadata ─────────────────────────────────────────
            // String column to support HasDynamicEnum trait (school-customizable options)
            // Common values: father, mother, guardian, uncle, aunt, sibling,
            //                grandparent, step_father, step_mother, foster_parent, other
            $table->string('relationship', 50)->default('guardian');

            // ── Contact & Authorization Flags ─────────────────────────────────
            // Primary contact receives official communications, invoices, results, etc.
            // Service layer enforces at most one true per student.
            $table->boolean('is_primary_contact')->default(false);

            // Authorization to physically pick up the student from school.
            // Especially important for primary and nursery sections.
            $table->boolean('can_pickup')->default(true);

            // Whether this guardian can log into the parent portal to view
            // attendance, results, fees, etc.
            $table->boolean('can_access_portal')->default(true);

            // Emergency contact designation (separate from primary contact).
            $table->boolean('is_emergency_contact')->default(false);

            // Priority for emergency calls (1 = first to call, 2 = second, etc.)
            $table->unsignedTinyInteger('emergency_contact_priority')
                ->nullable()
                ->comment('Lower number = higher priority for emergency calls');

            // ── Notes ─────────────────────────────────────────────────────────
            // Specific notes about this guardian-student relationship.
            // E.g., "Father works abroad - contact only in emergencies"
            //       "Mother is primary but unavailable on weekends"
            $table->text('notes')->nullable();

            // ── Timestamps ────────────────────────────────────────────────────
            $table->timestamps();

            // ── Constraints & Indexes ─────────────────────────────────────────
            // Prevent duplicate links between the same guardian and student
            $table->unique(['student_id', 'guardian_id'], 'uq_guardian_student');

            // Fast lookup for primary contact
            $table->index(
                ['student_id', 'is_primary_contact'],
                'idx_guardian_student_primary'
            );

            // Fast lookup for emergency contacts (ordered by priority)
            $table->index(
                ['student_id', 'is_emergency_contact', 'emergency_contact_priority'],
                'idx_guardian_student_emergency'
            );

            // Reverse lookup: all students for a particular guardian
            $table->index('guardian_id', 'idx_guardian_student_guardian');

            // Composite for common queries (student + relationship type)
            $table->index(
                ['student_id', 'relationship'],
                'idx_guardian_student_relationship'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
    }
};
