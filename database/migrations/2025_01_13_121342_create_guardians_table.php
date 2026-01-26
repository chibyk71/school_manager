<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: 2026_01_24_create_guardians_table.php
 *
 * This migration creates the 'guardians' table to store guardian-specific information and relationships.
 * It allows independent creation of guardians (people who are not necessarily staff or students themselves)
 * while linking them to a central Profile for shared personal data (name, contact, address, etc.).
 *
 * Features / Problems Solved:
 * - Separates guardian role from other roles (student/staff) to support guardian-specific data and custom fields.
 * - Enables independent guardian creation: Guardians can be added before being linked to any student (e.g., pre-register parents during admission season).
 * - Supports school-specific or tenant-wide guardians: 'school_id' is nullable → a guardian can be linked to students across multiple schools or be school-specific.
 * - Minimal core fields: Most guardian-specific data should come from HasCustomFields (e.g., occupation, relationship preferences, emergency priority, income bracket).
 * - Soft deletes: Allows archiving guardians without losing historical links to students (important for legal/audit purposes).
 * - Foreign key to profiles: Ensures every guardian has personal data centralized (no duplication of name/phone/email).
 * - On-delete behavior: 'set null' on profile deletion (rare) to avoid orphan records; restrict on school deletion if school_id is set.
 * - Indexes: On profile_id and school_id for fast joins and scoped queries (e.g., "all guardians for current school").
 * - Prepares for traits:
 *   - BelongsToSchool (optional scoping via global scope when school_id is present).
 *   - HasCustomFields (primary extension point for school-defined guardian attributes).
 *   - HasDynamicEnum (potential future use for guardian_type: parent, grandparent, sponsor, etc.).
 *   - SoftDeletes.
 * - UUID primary key: Consistent with other role tables (students, staff) for uniformity and future-proofing.
 * - Timestamps for creation/update auditing.
 * - Notes field: For quick admin notes (e.g., "prefers WhatsApp communication", "custody arrangement").
 *
 * Fits into the User Management Module:
 * - Complements the pivot table 'student_guardian' (created separately) which defines the actual many-to-many relationship between students and guardians.
 * - GuardianController handles standalone creation (Profile + Guardian bundle) and linking via pivot.
 * - Allows flexible workflows:
 *   - Create guardian independently → later assign to students (via AssignGuardianModal.vue).
 *   - Create guardian during student enrollment (inline in StudentEnrollmentModal.vue).
 * - Scoped queries: When school_id is set, BelongsToSchool trait can filter guardians relevant to the current school.
 * - Frontend integration:
 *   - GuardiansTable.vue → lists guardians (with HasTableQuery backend).
 *   - GuardianFormModal.vue → creation/editing (uses useCustomFields for dynamic fields).
 *   - AssignGuardianModal.vue → search existing guardians or create new → attach via pivot.
 * - Backend integration: GuardianController for CRUD; pivot operations in StudentController or dedicated service.
 * - Extensibility: Heavy reliance on custom fields makes it adaptable to different school policies (e.g., required fields for Nigerian vs. international guardians).
 * - Security: No direct sensitive data here; personal info lives in Profile; access controlled via permissions on User.
 */

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('profile_id')
                ->constrained('profiles')
                ->onDelete('restrict'); // Rare profile deletion shouldn't orphan guardian record

            $table->foreignUuid('school_id')
                ->nullable()
                ->constrained('schools')
                ->onDelete('set null'); // If school-specific guardian; null = tenant-wide

            $table->text('notes')->nullable(); // Admin notes (e.g., communication preferences, special instructions)

            $table->timestamps();
            $table->softDeletes();

            // Performance indexes
            $table->index('profile_id');           // Fast join to personal data
            $table->index('school_id');            // Scoped queries (guardians relevant to current school)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
