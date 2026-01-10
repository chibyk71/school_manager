<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create Terms Table (Academic Calendar Module)
 *
 * Creates the `terms` table that represents academic terms (e.g., First Term, Second Term, Third Term)
 * within an AcademicSession. This is the second-level time boundary in the academic calendar.
 *
 * Key Features Implemented:
 * ───────────────────────────────────────────────────────────────
 * • Multi-tenant + session-scoped: every term belongs to one school and one session
 * • UUID primary key for clean APIs and future scalability
 * • Strict naming uniqueness per session (prevents duplicate "First Term" in same session)
 * • Date constraints: term dates MUST be within parent session's date range
 * • Single active term per session enforcement (via app logic + unique index on is_active)
 * • Soft deletes + audit timestamps for historical preservation
 * • Status field using string (NOT enum) → allows full customizability via DynamicEnums
 * • Color field for UI visualization (e.g. timeline/calendar)
 * • json options field for future school-specific flags (e.g. has_midterm, weight_in_annual)
 *
 * Integration with DynamicEnums Module:
 * ───────────────────────────────────────────────────────────────
 * The following string columns are designed to be validated/managed via DynamicEnums:
 *   • status        → Default/common values: 'pending', 'active', 'closed'
 *   • name          → Default/common values: 'First Term', 'Second Term', 'Third Term'
 *   • short_name    → Default/common values: '1st', '2nd', '3rd'
 *
 * Schools can override/customize these values via the DynamicEnum admin interface.
 * Validation will be handled in the Term model + AcademicCalendarService.
 *
 * Important Business Rules (enforced in service layer, not DB):
 * • Only ONE term can have is_active = true per academic_session
 * • term.start_date ≥ parent_session.start_date
 * • term.end_date ≤ parent_session.end_date
 * • Once term becomes active → start_date becomes immutable
 * • End date can be adjusted while term is active (extension/shortening)
 * • Reopening a closed term only allowed for the most recently closed term
 *   AND only if the next term is still pending
 *
 * Fits into the Academic Calendar Module:
 * ───────────────────────────────────────────────────────────────
 * • Child of AcademicSession (belongsTo)
 * • Root for all term-bound operations (assessments, attendance, exams, results)
 * • Central entity for term activation/closure workflows
 * • Provides natural boundaries for continuous assessment vs end-of-term results
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            // Primary key - UUID for modern APIs
            $table->uuid('id')->primary();

            // Multi-tenant & session scoping
            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->cascadeOnDelete()
                ->comment('Owning school');

            $table->foreignUuid('academic_session_id')
                ->constrained('academic_sessions')
                ->cascadeOnDelete()
                ->comment('Parent academic session');

            // Core identity fields
            $table->string('name', 60)
                ->comment('Full term name - e.g. "First Term", "Second Term" (customizable via DynamicEnums)');

            $table->string('short_name', 10)->nullable()
                ->comment('Short/abbreviated name - e.g. "1st", "2nd", "3rd" (customizable)');

            $table->unsignedTinyInteger('ordinal_number')
                ->comment('Numeric order: 1,2,3... used for sorting & next-term logic');

            // Dates - nullable during creation, required when activating
            $table->date('start_date')->nullable()
                ->comment('Term start date - must be ≥ session start_date');

            $table->date('end_date')->nullable()
                ->comment('Term end date - must be ≤ session end_date');

            // Lifecycle & visibility
            $table->string('status', 20)
                ->default('pending')
                ->comment('Lifecycle status: pending, active, closed (customizable via DynamicEnums)');

            $table->boolean('is_active')
                ->default(false)
                ->comment('Only one true per session - enforced by app logic + unique index');

            $table->boolean('is_closed')
                ->default(false)
                ->comment('Soft closed flag - allows reopening under restrictions');

            $table->timestamp('closed_at')->nullable()
                ->comment('When term was closed (audit)');

            // UI & customization
            $table->string('color', 20)->nullable()
                ->comment('Tailwind/HEX color for UI timeline/calendar (e.g. blue-600)');

            $table->json('options')->nullable()
                ->comment('School-specific flags/config (future: weight, has_midterm, etc.)');

            // Standard timestamps + soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Constraints & Indexes
            // Prevent duplicate term names within the same session
            $table->unique(
                ['academic_session_id', 'name'],
                'terms_session_name_unique'
            );

            // Fast lookup of active term per session
            $table->index(
                ['academic_session_id', 'is_active'],
                'terms_session_active_idx'
            );

            // Common queries: by status, ordinal, dates
            $table->index('status', 'terms_status_idx');
            $table->index('ordinal_number', 'terms_ordinal_idx');
            $table->index(['start_date', 'end_date'], 'terms_date_range_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
