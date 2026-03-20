<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create School Sections Table
 *
 * Stores academic stream/division records for each school.
 * Examples: Pre-Primary, Primary, Junior Secondary, Senior Secondary.
 *
 * These are high-level organizational blocks — NOT individual classes.
 * Individual classes (JSS 1, Primary 4A, SS2 Science) belong to ClassLevel,
 * which references school_section_id as its parent.
 *
 * ── Architecture ────────────────────────────────────────────────────────
 * Every row belongs to exactly ONE school (school_id always required).
 * There are no global/shared rows — each school fully owns their sections.
 *
 * Sections are created in two ways:
 *   1. From predefined templates (config/school_section_templates.php)
 *      → source = 'template'
 *   2. Custom-created by the school from scratch
 *      → source = 'custom'
 *
 * If a school edits a template-sourced section, source changes to 'custom'
 * (handled by SchoolSectionObserver). Two states only, no is_modified flag.
 *
 * ── Key Design Decisions ────────────────────────────────────────────────
 * 1. restrictOnDelete on school_id FK:
 *    DB refuses to delete a school that still has sections.
 *    Cleanup must go through SchoolSectionService::deleteAllForSchool()
 *    which clears Laratrust role assignments first. This prevents orphaned
 *    role_user pivot rows pointing to deleted sections.
 *
 * 2. Unique [school_id, name] constraint:
 *    Prevents duplicate section names within one school.
 *    Does NOT account for soft-deleted rows — that is handled at the
 *    Form Request layer (StoreSchoolSectionRequest checks for soft-deleted
 *    conflicts and suggests restore instead of create).
 *
 * 3. is_active flag:
 *    Sections with existing class levels, students, or Laratrust role
 *    assignments should not be deleted — deactivate instead. Inactive
 *    sections are hidden from all dropdowns and pickers app-wide but
 *    their historical data (results, timetables) remains intact.
 *
 * 4. short_code is required (NOT NULL):
 *    Used in reports, DataTable badges, and dropdown labels throughout
 *    the app. All predefined templates include a short code. The Form
 *    Request enforces this on creation and update.
 *
 * 5. sort_order uses unsignedSmallInteger (0–65535):
 *    Consistent with CustomField, Grade, DynamicEnum in this codebase.
 *    TinyInteger (max 255) would be sufficient but SmallInteger costs
 *    nothing extra and avoids edge cases on bulk reorder imports.
 *
 * ── Indexes ─────────────────────────────────────────────────────────────
 * [school_id, name]     UNIQUE  — core uniqueness constraint
 * [school_id, is_active] INDEX  — most common query: active sections per school
 * [sort_order]           INDEX  — ORDER BY on all section listings
 * school_id FK index is created automatically by constrained() — not duplicated
 *
 * ── Integration ─────────────────────────────────────────────────────────
 * - Model: App\Models\SchoolSection (extends Laratrust Team)
 * - Used as Laratrust "teams" table (config/laratrust.php)
 * - BelongsToSections trait: polymorphic M:M on grades, exams, etc.
 * - BelongsToSchool trait: global scope for multi-tenant isolation
 * - SchoolSectionObserver: handles source mutation + cache invalidation
 * - SchoolSectionService: handles ordered cleanup before delete
 *
 * @see App\Models\SchoolSection
 * @see App\Models\ClassLevel
 * @see App\Services\SchoolSectionService
 * @see App\Observers\SchoolSectionObserver
 * @see config/laratrust.php (teams table reference)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_sections', function (Blueprint $table) {

            // ── Primary Key ───────────────────────────────────────────
            $table->uuid('id')->primary();

            // ── Ownership ────────────────────────────────────────────
            // restrictOnDelete: school cannot be force-deleted while
            // sections exist. SchoolSectionService handles ordered cleanup.
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->restrictOnDelete()
                ->comment('Owning school. restrictOnDelete forces cleanup through service layer.');

            // ── Core Identity Fields ──────────────────────────────────
            $table->string('name', 80)
                ->comment('Canonical slug — unique per school. e.g. junior_secondary, primary');

            $table->string('display_name', 100)
                ->comment('Human-readable UI label. e.g. Junior Secondary School');

            // Required — used in badges, reports, and dropdown labels
            $table->string('short_code', 20)
                ->comment('Required abbreviated code for reports and UI badges. e.g. JSS, PRI, SSS');

            $table->text('description')
                ->nullable()
                ->comment('Optional notes or purpose description for this division');

            // ── Status & Ordering ─────────────────────────────────────
            $table->boolean('is_active')
                ->default(true)
                ->comment('Soft toggle. Inactive sections hidden from pickers but data preserved.');

            $table->unsignedSmallInteger('sort_order')
                ->default(99)
                ->comment('Display order — lower appears first. Consistent with CustomField, Grade models.');

            // ── Origin Tracking ───────────────────────────────────────
            // template: created from config/school_section_templates.php
            // custom:   created from scratch by school admin
            // Note: if a template section is edited, Observer changes this to 'custom'
            $table->string('source', 20)
                ->default('custom')
                ->comment('Origin tracking: template | custom. Changes to custom when template is edited.');

            // ── Timestamps & Soft Deletes ─────────────────────────────
            $table->timestamps();
            $table->softDeletes();

            // ── Constraints ───────────────────────────────────────────
            // Unique name per school.
            // Note: does NOT exclude soft-deleted rows — Form Request handles
            // that edge case and suggests restore instead of create.
            $table->unique(
                ['school_id', 'name'],
                'school_sections_school_name_unique'
            );

            // ── Indexes ───────────────────────────────────────────────
            // Most common query: fetch active sections for a school (dropdowns, pickers)
            $table->index(
                ['school_id', 'is_active'],
                'school_sections_school_active_index'
            );

            // ORDER BY on all listings
            $table->index('sort_order', 'school_sections_sort_order_index');

            // Note: school_id single-column index is automatically created
            // by constrained() above — not duplicated here.
        });
    }

    public function down(): void
    {
        // Drop in reverse order of creation
        // FK constraint means schools table must still exist
        Schema::dropIfExists('school_sections');
    }
};
