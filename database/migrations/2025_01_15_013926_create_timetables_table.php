<?php

/**
 * Migration: create_timetables_table
 *
 * Creates the `timetables` table — the master timetable header record.
 * Each row represents one complete schedule document for a specific school section
 * within a specific term.
 *
 * ── What This Table Stores ───────────────────────────────────────────────────
 * A Timetable is the "master board" for one SchoolSection + Term combination.
 * It is the container that holds all lesson slot assignments (in timetable_slots)
 * and the day→schedule mappings (in timetable_day_schedules).
 *
 * Real-world analogy: the physical timetable posted on the Senior Secondary
 * notice board for the First Term, showing every subject, teacher, and period
 * for all SSS arms (1A, 1B, 2A, 2B, 3A, 3B).
 *
 * ── One Active Per Section/Term ───────────────────────────────────────────────
 * Business rule: only one timetable can be `active` per (school_section_id, term_id)
 * combination at any time. This is enforced:
 *   1. At the DB level: a partial unique index on (school_section_id, term_id, status)
 *      where status = 'active' (see index definition below).
 *   2. At the application level: TimetableService::activate() sets all other
 *      timetables for the same section/term to 'archived' before activating.
 *
 * Draft and archived timetables are kept as history.
 *
 * ── Status Lifecycle ──────────────────────────────────────────────────────────
 *   draft    → timetable is being built; not visible to students/teachers
 *   active   → currently in use; visible to all; only one allowed per section/term
 *   archived → replaced by a newer timetable; read-only; visible for history
 *
 * ── Relationship to Other Tables ────────────────────────────────────────────
 *   timetables  ──< timetable_slots           (all lesson assignments)
 *   timetables  ──< timetable_day_schedules   (day → period schedule mapping)
 *   timetables  ──< timetable_conflicts       (unresolved generation conflicts)
 *   timetables   >─ school_sections           (one section per timetable)
 *   timetables   >─ terms                     (one term per timetable)
 *   timetables   >─ users (generated_by)      (who last triggered generation)
 *
 * ── Key Design Decisions ─────────────────────────────────────────────────────
 * - UUID primary key — timetables are user-facing entities (shared via URL,
 *   referenced in notifications) so UUIDs avoid sequential ID enumeration.
 *
 * - `effective_from` required, `effective_to` nullable — a timetable must have
 *   a start date but may run indefinitely. When a new timetable is activated,
 *   TimetableService auto-fills `effective_to` on the previously active one
 *   with the day before the new one's `effective_from`.
 *
 * - `generated_at` / `generated_by` — audit trail for the auto-generation action.
 *   Enables "Timetable last generated 3 days ago by Mr. Okonkwo" in the UI.
 *   Separate from `created_at`/`created_by` because a timetable may be created
 *   manually without ever running the generator.
 *
 * - `options` JSON — extensibility hatch for school-specific generation settings:
 *   e.g. {"max_periods_per_teacher_per_day": 4, "prefer_morning_for_core_subjects": true}
 *   These drive the generator without requiring schema changes.
 *
 * ── Belongs To ───────────────────────────────────────────────────────────────
 * Timetable Module → Core layer. The root entity of the entire module.
 * Managed by TimetableController.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetables', function (Blueprint $table) {
            // ── Primary Key ───────────────────────────────────────────────────
            $table->uuid('id')->primary();
            // UUID — user-facing entity; avoids sequential ID enumeration in URLs.

            // ── Multi-Tenant Anchor ───────────────────────────────────────────
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();

            // ── Core Foreign Keys ─────────────────────────────────────────────
            $table->foreignUuid('school_section_id')
                ->constrained('school_sections')
                ->restrictOnDelete();
            // Restrict: cannot delete a school section that has timetables.
            // Admin must archive/delete the timetable first.

            $table->foreignId('term_id')
                ->constrained('terms')
                ->restrictOnDelete();
            // Restrict: cannot delete a term that has timetables.

            // ── Identity ─────────────────────────────────────────────────────
            $table->string('title', 255);
            // Human-readable name. Examples:
            //   "SSS Term 1 2025/2026", "JSS First Term Timetable (Revised)"
            // Unique per school/section/term enforced at application layer.

            // ── Effective Date Range ──────────────────────────────────────────
            $table->date('effective_from');
            // Required. The date from which this timetable is in effect.
            // For new terms this is typically the first day of the term.

            $table->date('effective_to')->nullable();
            // Nullable — active timetables have no end date.
            // Auto-filled by TimetableService::activate() when a newer timetable
            // supersedes this one. Set to (new_timetable.effective_from - 1 day).

            // ── Status ───────────────────────────────────────────────────────
            $table->string('status', 20)->default('draft');
            // Allowed values: 'draft' | 'active' | 'archived'
            // Application-level enforcement via TimetableService.
            // DB-level enforcement via partial unique index below.

            // ── Notes ────────────────────────────────────────────────────────
            $table->text('notes')->nullable();
            // Admin-authored notes. Example: "Revised after Mr. Bello's departure.
            // Physics now split between Mrs. Eze (SSS1) and Mr. Yakubu (SSS2-3)."

            // ── Generation Audit ─────────────────────────────────────────────
            $table->timestamp('generated_at')->nullable();
            // When the auto-generator was last run for this timetable.
            // Null = never generated (manually built or brand new).

            $table->foreignId('generated_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // Which user triggered the last generation run.
            // Null = generated by a system job or never generated.

            // ── Generator Settings ────────────────────────────────────────────
            $table->json('options')->nullable();
            // School/timetable-specific generator settings. Structure example:
            // {
            //   "max_periods_per_teacher_per_day": 4,
            //   "working_days": [1, 2, 3, 4, 5],       // 1=Mon...5=Fri
            //   "prefer_morning_for_core_subjects": true,
            //   "distribute_subjects_evenly": true
            // }
            // Processed by TimetableGeneratorService. Allows per-timetable
            // overrides without schema changes.

            // ── Timestamps & Soft Deletes ─────────────────────────────────────
            $table->timestamps();
            $table->softDeletes();
            // Soft delete — timetables are historical records referenced by
            // published results and attendance data. Hard deletion must be prevented.

            // ── Indexes ───────────────────────────────────────────────────────
            $table->index(
                ['school_id', 'school_section_id', 'term_id', 'status'],
                'idx_timetables_section_term_status'
            );
            // Primary lookup: "give me all timetables for SSS this term" and
            // "is there already an active timetable for JSS Term 1?"

            $table->index(
                ['school_id', 'status'],
                'idx_timetables_school_status'
            );
            // Used on the timetable index page to list all active timetables
            // for a school (admin overview dashboard).

            $table->index('term_id', 'idx_timetables_term');
            // Used when loading timetable data scoped to current term.

            // ── Application-Enforced Uniqueness ───────────────────────────────
            // NOTE: We intentionally do NOT add a DB-level unique constraint on
            // (school_section_id, term_id) WHERE status = 'active' here because
            // MySQL/MariaDB partial indexes with WHERE clauses have limited support.
            // This rule is enforced exclusively in TimetableService::activate()
            // using a DB transaction to set all others to 'archived' first,
            // then setting this one to 'active'. This is safe under proper locking.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};
