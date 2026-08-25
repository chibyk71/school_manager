<?php

/**
 * Migration: 2026_03_30_000001_create_student_applications_table.php
 *
 * Creates the student_applications table — the entry point for ALL student onboarding flows.
 *
 * This table acts as a temporary, unverified holding area for new student applications
 * (whether submitted via public portal or created by admin) before they are admitted
 * and converted into a proper Profile + Student record.
 *
 * ── Core Design Principle ────────────────────────────────────────────────────
 *
 * Applications are deliberately kept SEPARATE from the students table because:
 * 1. They contain raw, unverified data from the public form.
 * 2. Many fields (rejection_reason, application_token, raw guardians_data, custom_data)
 *    become irrelevant after successful admission.
 * 3. Schools frequently need to bulk archive or delete old/rejected applications
 *    without affecting historical student records.
 * 4. Provides clean audit trail: Application → Review → Admission → Student record.
 *
 * On successful admission, data from this table is used to:
 *   - Create/Update a central Profile record
 *   - Create a new Student record (school-scoped)
 *   - Create/link Guardian records via the guardian_student pivot
 *   - Populate initial CustomFieldResponses via HasCustomFields trait
 *
 * ── Status & Source Handling ─────────────────────────────────────────────────
 *
 * Both `status` and `source` are stored as plain `string` columns (NOT database ENUMs).
 * This allows future use of HasDynamicEnum trait on the StudentApplication model
 * so schools can customize application statuses if needed (e.g. "under_review", "interview_scheduled").
 *
 * Default values are enforced at the application level (in StudentApplicationService).
 *
 * ── Multi-Tenancy ────────────────────────────────────────────────────────────
 *
 * - school_id is a hard foreign key.
 * - BelongsToSchool trait will be used on the StudentApplication model for automatic scoping.
 * - Public application portal is school-specific via slug.
 *
 * ── Key Column Groups ────────────────────────────────────────────────────────
 *
 * • Academic Intent → session, section, class level desired
 * • Personal Snapshot → first_name, last_name, dob, gender, etc. (copied to Profile on admission)
 * • Guardian Raw Data → JSON array (converted to proper Guardian records on admission)
 * • Metadata → source, application_number, token, review info
 * • Outcome → student_id (set after admission)
 * • Custom → custom_data JSON for school-specific application questions
 *
 * Fits into the Student Management Module:
 * - Used by PublicApplicationController, ApplicationController, and StudentApplicationService.
 * - Feeds directly into admitApplication() flow.
 * - Integrates with HasDynamicEnum (for gender, religion, etc.) and HasCustomFields (via custom_data mapping).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_applications', function (Blueprint $table) {

            // ── Primary Key ───────────────────────────────────────────────────
            $table->uuid('id')->primary();

            // ── School & Tenant Context ───────────────────────────────────────
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->restrictOnDelete(); // Prevent deleting school with pending applications

            // ── Desired Academic Placement ────────────────────────────────────
            $table->foreignUuid('academic_session_id')
                ->nullable()
                ->constrained('academic_sessions')
                ->nullOnDelete();

            $table->foreignUuid('school_section_id')
                ->nullable()
                ->constrained('school_sections')
                ->nullOnDelete();

            $table->foreignUuid('class_level_id')
                ->nullable()
                ->constrained('class_levels')
                ->nullOnDelete();

            // ── Applicant Personal Information (Snapshot) ─────────────────────
            // These fields are copied to Profile + Student on successful admission.
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 30)->nullable();           // Will use HasDynamicEnum value
            $table->string('phone', 30)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('state_of_origin', 100)->nullable();
            $table->string('religion', 50)->nullable();         // HasDynamicEnum candidate
            $table->string('blood_group', 10)->nullable();      // HasDynamicEnum candidate

            // Previous school info (mainly for transfer applicants)
            $table->string('previous_school', 255)->nullable();
            $table->string('previous_class', 100)->nullable();
            $table->string('previous_school_address', 500)->nullable();

            // ── Guardian Information (Raw from Form) ──────────────────────────
            // Stored as JSON to easily support multiple guardians at application stage.
            // On admission, this is parsed and converted into proper Guardian + pivot records.
            $table->json('guardians_data')->nullable();

            // ── Application Metadata ──────────────────────────────────────────
            $table->string('source', 30)->default('public_portal'); // 'public_portal' or 'admin_direct'

            // Status as string → allows future dynamic customization via HasDynamicEnum
            $table->string('status', 30)->default('pending');

            // ── Identifiers ───────────────────────────────────────────────────
            $table->string('application_number', 50)->nullable();
            $table->string('application_token', 100)->nullable()->unique();

            // ── Review & Decision ─────────────────────────────────────────────
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();

            // ── Outcome ───────────────────────────────────────────────────────
            $table->foreignUuid('student_id')
                ->nullable()
                ->constrained('students')
                ->nullOnDelete();

            // ── Supporting Documents ──────────────────────────────────────────
            $table->json('documents')->nullable();

            // ── School-Specific Custom Fields ─────────────────────────────────
            // Temporary storage for extra questions on the application form.
            // Mapped to CustomFieldResponses when student record is created.
            $table->json('custom_data')->nullable();

            // ── Timestamps & Soft Deletes ─────────────────────────────────────
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ───────────────────────────────────────────────────────
            $table->unique(['school_id', 'application_number'], 'uq_application_number_per_school');

            $table->index(['school_id', 'status'], 'idx_applications_school_status');
            $table->index(['school_id', 'academic_session_id'], 'idx_applications_school_session');
            $table->index('application_token', 'idx_applications_token');
            $table->index('student_id', 'idx_applications_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_applications');
    }
};
