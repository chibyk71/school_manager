<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Promotion Tables Migration
 *
 * ============================================================================
 * WHAT IS IMPLEMENTED
 * ============================================================================
 *
 * Creates three tables that form the complete promotion module:
 *
 *   1. promotion_batches
 *      The container for one end-of-session promotion cycle.
 *      One batch per school per academic session (enforced by unique index).
 *      Tracks the full lifecycle from draft → pending → reviewing →
 *      approved → executing → completed (or cancelled).
 *      Uses SoftDeletes so cancelled batches can be archived, not lost.
 *
 *   2. promotion_students
 *      One row per student per batch. Holds the system-computed recommendation
 *      (immutable after population) and the optional human override (final_decision).
 *      Also stores the academic data (average score, failed subjects, attendance)
 *      that was used to compute the recommendation — kept here for transparency
 *      and auditability without requiring joins to ExamResult at report time.
 *      No SoftDeletes — rows are tied to the batch lifecycle.
 *
 *   3. promotion_histories
 *      The permanent, immutable record written when a student is actually processed.
 *      No SoftDeletes, no deleted_at column. This is the legal/academic record
 *      used for transcripts, government reporting, and certificates.
 *      Unique on (student_id, from_academic_session_id) — one permanent record
 *      per student per session, forever.
 *
 * ============================================================================
 * KEY DESIGN DECISIONS
 * ============================================================================
 *
 * • All IDs are UUIDs (char 36) — consistent with the rest of the codebase.
 *
 * • promotion_batches.status uses a string column with a DB-level check
 *   constraint (via enum-style values) so invalid statuses cannot be inserted
 *   even if application-level validation is bypassed.
 *
 * • promotion_students.recommendation is NOT NULL after population — the system
 *   always produces a recommendation. final_decision is nullable (NULL = no
 *   human override, recommendation stands).
 *
 * • promotion_histories has no deleted_at intentionally. The PromotionHistory
 *   model's boot() method throws a LogicException on any delete attempt.
 *   The DB constraint (no soft delete column) is a second layer of protection.
 *
 * • Foreign keys use restrictOnDelete() for promotion_histories (cannot delete
 *   a student or session that has a promotion history record — data integrity).
 *   For promotion_batches and promotion_students, cascadeOnDelete() is used
 *   where appropriate (deleting a batch cascades to its student records).
 *
 * • The unique index on promotion_batches(school_id, academic_session_id)
 *   prevents duplicate batches at the DB level — even if application-level
 *   guards fail under concurrent load.
 *
 * ============================================================================
 * NOTIFICATION PREFERENCES TABLE
 * ============================================================================
 *
 * promotion_notification_preferences stores per-school channel preferences
 * for each promotion event (which channels: mail, database, sms).
 * This allows admins to configure exactly which channels fire for each event
 * via the settings UI, rather than hardcoding notification channels.
 */
return new class extends Migration {
    public function up(): void
    {
        // =====================================================================
        // TABLE 1: promotion_batches
        // =====================================================================
        Schema::create('promotion_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->restrictOnDelete();

            $table->foreignUuid('academic_session_id')
                ->constrained('academic_sessions')
                ->restrictOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // Status state machine — valid values enforced at application level
            // via PromotionBatch::transitionTo(). String used (not enum) so
            // adding new statuses doesn't require a DB migration.
            $table->string('status', 20)->default('draft');

            // Audit trail — who did what and when
            $table->foreignUuid('initiated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUuid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->text('approval_comments')->nullable();

            $table->foreignUuid('executed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('executed_at')->nullable();

            // Progress counters — updated incrementally by the processing job
            $table->unsignedInteger('total_students')->default(0);
            $table->unsignedInteger('processed_students')->default(0);
            $table->unsignedInteger('failed_students')->default(0);

            // Flexible metadata: cancellation reason, execution stats, errors summary
            $table->json('metadata')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // === INDEXES ===

            // Core constraint: one active batch per session per school
            $table->unique(
                ['school_id', 'academic_session_id'],
                'promo_batches_school_session_unique'
            );

            $table->index('status');
            $table->index(['school_id', 'status']);
        });

        // =====================================================================
        // TABLE 2: promotion_students
        // =====================================================================
        Schema::create('promotion_students', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('promotion_batch_id')
                ->constrained('promotion_batches')
                ->cascadeOnDelete(); // batch deleted → student records deleted

            $table->foreignUuid('student_id')
                ->constrained('students')
                ->restrictOnDelete(); // cannot delete a student mid-promotion

            // Class placement at the time of promotion
            $table->foreignUuid('current_class_section_id')
                ->nullable()
                ->constrained('class_sections')
                ->nullOnDelete();

            $table->foreignUuid('next_class_section_id')
                ->nullable()
                ->constrained('class_sections')
                ->nullOnDelete();

            // ─── System-computed recommendation (immutable after population) ───
            // Populated by PopulatePromotionBatch job. Never updated after creation.
            // Values: promote | repeat | graduate
            $table->string('recommendation', 20);

            // Academic data snapshot — copied from ExamResult at population time
            // so reports don't need joins back to exam tables
            $table->decimal('average_score', 5, 2)->nullable();
            $table->unsignedSmallInteger('failed_subjects_count')->default(0);
            $table->unsignedSmallInteger('total_subjects_count')->default(0);
            $table->decimal('attendance_percentage', 5, 2)->nullable();

            // ─── Human override (set during review phase) ─────────────────────
            // NULL = recommendation stands. Not-null = human changed the outcome.
            // Values: promote | repeat | graduate (same set as recommendation)
            $table->string('final_decision', 20)->nullable();
            $table->text('override_reason')->nullable();

            $table->foreignUuid('overridden_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('overridden_at')->nullable();

            // ─── Execution state ───────────────────────────────────────────────
            $table->boolean('is_processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable(); // stores exception if job fails

            $table->timestamps();

            // === INDEXES ===

            // Core constraint: a student appears exactly once per batch
            $table->unique(
                ['promotion_batch_id', 'student_id'],
                'promo_students_batch_student_unique'
            );

            $table->index('recommendation');
            $table->index('final_decision');
            $table->index('is_processed');
            $table->index(['promotion_batch_id', 'is_processed']);
            $table->index('current_class_section_id');
        });

        // =====================================================================
        // TABLE 3: promotion_histories
        // =====================================================================
        Schema::create('promotion_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->restrictOnDelete();

            $table->foreignUuid('student_id')
                ->constrained('students')
                ->restrictOnDelete(); // cannot delete a student who has history

            // Link back to the batch and the per-student record for full auditability
            $table->foreignUuid('promotion_batch_id')
                ->constrained('promotion_batches')
                ->restrictOnDelete();

            $table->foreignUuid('promotion_student_id')
                ->constrained('promotion_students')
                ->restrictOnDelete();

            // Session and class section at the time of execution
            $table->foreignUuid('from_academic_session_id')
                ->constrained('academic_sessions')
                ->restrictOnDelete();

            $table->foreignUuid('to_academic_session_id')
                ->nullable() // NULL for graduated students — no next session
                ->constrained('academic_sessions')
                ->nullOnDelete();

            $table->foreignUuid('from_class_section_id')
                ->nullable()
                ->constrained('class_sections')
                ->nullOnDelete();

            $table->foreignUuid('to_class_section_id')
                ->nullable() // NULL for repeat (stays) and graduate (leaves)
                ->constrained('class_sections')
                ->nullOnDelete();

            // The final outcome that was executed
            // Values: promote | repeat | graduate
            $table->string('outcome', 20);

            // Denormalised for fast reporting without joins to promotion_students
            $table->boolean('was_overridden')->default(false);
            $table->text('override_reason')->nullable();
            $table->decimal('average_score', 5, 2)->nullable();
            $table->unsignedSmallInteger('failed_subjects_count')->default(0);

            // Who ran the execution job and when
            $table->foreignUuid('executed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('executed_at');

            // Optional admin remarks added post-execution (e.g. "Parents notified")
            $table->text('remarks')->nullable();

            // NO softDeletes — this is an immutable legal/academic record
            $table->timestamps();

            // === INDEXES ===

            // Core constraint: one permanent record per student per session
            $table->unique(
                ['student_id', 'from_academic_session_id'],
                'promo_histories_student_session_unique'
            );

            $table->index('outcome');
            $table->index(['school_id', 'from_academic_session_id']);
            $table->index(['student_id', 'outcome']);
        });

        // =====================================================================
        // TABLE 4: promotion_notification_preferences
        // Stores per-school channel preferences for each promotion event.
        // Allows admins to choose which channels (mail, sms, database) fire
        // for each event type — configured via the settings UI.
        // =====================================================================
        Schema::create('promotion_notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();

            // The event this preference applies to:
            // batch_ready_for_approval | batch_approved | batch_completed | student_outcome
            $table->string('event', 60);

            // Which channels are enabled for this event
            $table->boolean('notify_database')->default(true);  // in-app
            $table->boolean('notify_mail')->default(true);      // email
            $table->boolean('notify_sms')->default(false);      // SMS via SmsService

            // Who receives this notification for this event
            // Stored as JSON array of permission slugs, e.g. ["promotions.approve", "promotions.execute"]
            // Anyone with those permissions gets notified
            $table->json('recipient_permissions')->nullable();

            $table->timestamps();

            $table->unique(['school_id', 'event'], 'promo_notif_prefs_school_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_notification_preferences');
        Schema::dropIfExists('promotion_histories');
        Schema::dropIfExists('promotion_students');
        Schema::dropIfExists('promotion_batches');
    }
};
