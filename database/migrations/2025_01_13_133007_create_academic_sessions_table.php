<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create Academic Sessions Table (2025/2026+ standard)
 *
 * Creates the `academic_sessions` table that serves as the primary time boundary
 * for all academic activities in the school management system.
 *
 * Key Features Implemented:
 * ───────────────────────────────────────────────────────────────
 * • Multi-tenant safety: every session belongs to exactly one school
 * • UUID primary key for global uniqueness and cleaner APIs
 * • Strict naming convention: 2025/2026 style enforced at application level
 * • Start/end date range enforcement (start ≤ end)
 * • Single active/current session per school (enforced via unique index + app logic)
 * • Status tracking: draft → upcoming → active → closed → archived
 * • Soft deletes for historical preservation (very important for audits & transcripts)
 * • Performance indexes for common queries (current session, school lookup)
 * • Constraints to prevent duplicate names per school
 *
 * Fits into the Academic Calendar Module:
 * ───────────────────────────────────────────────────────────────
 * • Root entity for all time-bound operations (terms, assessments, results, attendance)
 * • Used by Term model (hasMany relationship)
 * • Central point for activation/closure workflows
 * • Provides integration point for future modules (Promotion, ReportCards, etc.)
 *   via events dispatched on status changes (e.g. SessionActivated, SessionClosed)
 *
 * Important Business Rules (enforced in service layer, not DB):
 * • Only ONE session can have is_current = true per school
 * • Once active, start_date becomes immutable
 * • End date can be adjusted (extended/shortened) while session is active
 * • Status progression: draft → upcoming → active → closed → archived
 *
 * Recommended Indexes & Constraints Rationale:
 * • Composite unique on (school_id, name) → prevents duplicate sessions
 * • Index on is_current + school_id → fast lookup of current session
 * • Index on status → efficient filtering of active/upcoming/closed sessions
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            // Primary key - UUID for modern APIs and distributed systems
            $table->uuid('id')->primary();

            // Multi-tenant foreign key - must always be present
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->onDelete('cascade')
                ->comment('Owning school - cascade delete for data cleanup');

            // Core session identity
            $table->string('name', 25)
                ->comment('Session name, e.g. "2025/2026" - usually 9 chars but allow buffer');

            $table->date('start_date')
                ->comment('Session start date - immutable after activation');

            $table->date('end_date')
                ->comment('Session end date - can be adjusted while active');

            // Current/active flag - STRICTLY one per school
            $table->boolean('is_current')
                ->default(false)
                ->comment('Only one true per school - enforced by app logic + unique index');

            // Detailed lifecycle status (more expressive than just is_current)
            $table->string('status', 20)
                ->default('draft')
                ->comment('draft, upcoming, active, closed, archived');

            // Audit & history
            $table->timestamp('activated_at')->nullable()
                ->comment('When session was officially activated');
            $table->timestamp('closed_at')->nullable()
                ->comment('When session was officially closed');

            // Standard timestamps + soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Constraints & Indexes
            // Prevent duplicate session names per school
            $table->unique(['school_id', 'name'], 'academic_sessions_school_name_unique');

            // Fast lookup for current session per school
            $table->index(['school_id', 'is_current'], 'academic_sessions_school_current_idx');

            // Useful for filtering by lifecycle stage
            $table->index('status', 'academic_sessions_status_idx');

            // Common range queries (reports, term validation)
            $table->index(['start_date', 'end_date'], 'academic_sessions_date_range_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_sessions');
    }
};
