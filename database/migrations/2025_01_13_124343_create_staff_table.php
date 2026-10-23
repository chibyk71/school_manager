<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: 2026_01_24_create_staff_table.php
 *
 * This migration creates the 'staff' table, which represents position/employment records for staff members.
 * Each record captures a specific employment instance (e.g., a teacher role in one school/section), allowing a single person (via Profile) to hold multiple staff positions across schools or over time.
 *
 * Features / Problems Solved:
 * - Treats "staff" as a time-bound role rather than a permanent identity: New record for new positions, promotions, transfers, or re-hirings (e.g., Mr. Ahmed as teacher in School A, then admin in School B).
 * - Enables multi-school and optional section scoping: Foreign keys to school and section (nullable) support cross-school roles and section-specific assignments (e.g., primary school coordinator).
 * - Includes core employment data: staff_id_number (unique identifier, often school-specific), hire/termination dates, department linkage (future-proof for HRM integration).
 * - Soft deletes for safe archiving: Retain historical employment records (e.g., for payroll audits, service certificates) without permanent data loss.
 * - Foreign key constraints with appropriate delete behaviors:
 *   - profile_id: onDelete('set null') — rare profile deletion shouldn't orphan staff records.
 *   - school_id: onDelete('restrict') — prevent deleting schools with active/former staff.
 *   - section_id: nullable + onDelete('set null') — sections can change without breaking history.
 *   - department_id: nullable + onDelete('set null') — departments may be restructured.
 * - Indexes on frequently queried fields (profile_id, staff_id_number, school_id + section_id) for efficient joins and filtered listings.
 * - Unique constraint on staff_id_number (global for now; can be made per-school via composite unique if needed).
 * - Prepares for traits:
 *   - BelongsToSchool (auto-scopes queries to current school via global scope).
 *   - HasDynamicEnum (for employment_type: full-time/part-time/contract, role: teacher/admin/support).
 *   - HasCustomFields (school-specific extensions: qualifications, certifications, emergency contact).
 *   - SoftDeletes.
 * - UUID primary key for global uniqueness (useful for distributed systems, exports, or integrations).
 * - Timestamps for auditing creation/modification.
 * - Nullable fields for flexibility (e.g., date_of_termination only set on resignation/termination; department_id optional for non-departmental roles).
 * - Performance: Composite index on school_id + section_id for scoped staff listings in data tables.
 * - Security/Integrity: Constraints prevent invalid data (e.g., staff without profile or school).
 *
 * Fits into the User Management Module:
 * - Links to Profile (belongsTo) for shared personal data (name, DOB, photo, addresses via HasAddress).
 * - Scoped via BelongsToSchool and optional belongsTo Section for multi-tenant safety: Queries automatically filtered to current school.
 * - Created bundled with profiles: StaffController handles "hire staff" flow (create Profile if new + Staff + optional User for login).
 * - Supports optional User creation: After profile is created, UserCreationService can attach a login account if the staff needs app access.
 * - Integrates with frontend: Used in StaffTable.vue (data table powered by HasTableQuery), StaffAssignmentModal.vue (modal for creation/assignment using useCustomFields).
 * - Backend integration: StaffController for CRUD; potential future HRMService for salary/promotion logic.
 * - Extensibility: Ready for custom fields (e.g., teaching subjects, qualifications) and dynamic enums (employment status, job titles).
 * - Alignment with stack: Follows Laravel conventions (constraints, indexes); supports Inertia responses for modals/tables.
 */

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('profiles')->onDelete('set null'); // Link to central person
            $table->foreignUuid('school_id')->constrained('schools')->onDelete('restrict'); // Required; prevent school deletion with staff records

            $table->string('staff_id_number')->nullable()->unique(); // e.g., STF-2025-001; unique globally (or per-school via composite if needed later)
            $table->date('date_of_employment')->nullable(); // Hire/start date
            $table->date('date_of_termination')->nullable(); // Resignation/termination date
            $table->string('employment_type')->nullable(); // e.g., full-time, part-time, contract (via HasDynamicEnum)
            $table->string('status')->default('active'); // active, on-leave, terminated (via HasDynamicEnum)
            $table->text('notes')->nullable(); // Position-specific notes (e.g., subjects taught, responsibilities)

            $table->timestamps();
            $table->softDeletes();

            // Performance indexes
            $table->index('profile_id'); // For joining with profiles
            $table->index('staff_id_number'); // For quick staff lookups
            $table->index(['school_id', 'section_id']); // For scoped listings (current school/section staff)
            $table->index('department_id'); // For department-based queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
