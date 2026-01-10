<?php

namespace App\Services;

use App\Events\Academic\SessionActivated;
use App\Events\Academic\SessionClosed;
use App\Events\Academic\TermActivated;
use App\Events\Academic\TermClosed;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * AcademicCalendarService v1.0 – Core Business Logic for Academic Sessions & Terms
 *
 * This is the **single source of truth** for all calendar-related operations in the system.
 * All controllers, jobs, commands, etc., MUST use this service instead of direct model queries.
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Strict enforcement of single active session per school
 * • Strict enforcement of single active term per session
 * • Date immutability: start_date locked after activation
 * • Date hierarchy: term dates MUST be inside parent session dates
 * • Safe activation/closure with transaction + events
 * • Restricted reopen logic: only last closed term, only if next is pending
 * • Cache invalidation for current session/term (performance + consistency)
 * • Comprehensive validation exceptions + logging
 * • Prepares for future integration (promotion module listens to SessionClosed event)
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Central orchestrator — used by controllers (AcademicSessionController, TermController)
 * • Protects business invariants that raw Eloquent queries could break
 * • Dispatches domain events for loose coupling (e.g. SessionClosedEvent → PromotionEngine)
 * • Works with HasDynamicEnum for school-customizable status values
 *
 * Usage Guidelines:
 *   $service = app(AcademicCalendarService::class);
 *   $service->activateSession($session);
 *   $service->closeTerm($term);
 *   $service->currentSession(); // cached & safe
 */
class AcademicCalendarService
{
    /** Cache TTL for current session/term (short for frequent changes) */
    private const CACHE_TTL_MINUTES = 15;

    private const CACHE_KEY_SESSION = 'current_academic_session_';
    private const CACHE_KEY_TERM    = 'current_academic_term_';

    // ────────────────────────────────────────────────────────────────
    // Current Session / Term Retrieval (cached)
    // ────────────────────────────────────────────────────────────────

    /**
     * Get the currently active academic session for the current school.
     */
    public function currentSession(): ?AcademicSession
    {
        $school = GetSchoolModel();
        if (! $school) {
            return null;
        }

        $key = self::CACHE_KEY_SESSION . $school->id;

        return Cache::remember($key, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($school) {
            return AcademicSession::where('school_id', $school->id)
                ->where('is_current', true)
                ->first();
        });
    }

    /**
     * Get the currently active term for the current school.
     */
    public function currentTerm(): ?Term
    {
        $school = GetSchoolModel();
        if (! $school) {
            return null;
        }

        $key = self::CACHE_KEY_TERM . $school->id;

        return Cache::remember($key, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($school) {
            $session = $this->currentSession();
            if (! $session) {
                return null;
            }

            return Term::where('academic_session_id', $session->id)
                ->where('is_active', true)
                ->first();
        });
    }

    // ────────────────────────────────────────────────────────────────
    // Activation & Closure – Session
    // ────────────────────────────────────────────────────────────────

    /**
     * Activate a session – enforces single active session + immutability.
     *
     * @throws ValidationException
     */
    public function activateSession(AcademicSession $session): void
    {
        $school = GetSchoolModel();
        if ($session->school_id !== $school->id) {
            throw ValidationException::withMessages(['session' => 'Session does not belong to current school.']);
        }

        if ($session->status === AcademicSession::STATUS_ACTIVE) {
            return; // Already active
        }

        DB::transaction(function () use ($session) {
            // Deactivate any other current session
            AcademicSession::where('school_id', $session->school_id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $session->update([
                'is_current'   => true,
                'status'       => AcademicSession::STATUS_ACTIVE,
                'activated_at' => now(),
            ]);

            Cache::forget(self::CACHE_KEY_SESSION . $session->school_id);
        });

        event(new SessionActivated($session));
    }

    /**
     * Close a session (soft close – can be reopened until archived).
     *
     * @throws ValidationException
     */
    public function closeSession(AcademicSession $session): void
    {
        if ($session->status !== AcademicSession::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['status' => 'Only active sessions can be closed.']);
        }

        DB::transaction(function () use ($session) {
            $session->update([
                'status'    => AcademicSession::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            Cache::forget(self::CACHE_KEY_SESSION . $session->school_id);
        });

        event(new SessionClosed($session));
    }

    // ────────────────────────────────────────────────────────────────
    // Activation & Closure – Term
    // ────────────────────────────────────────────────────────────────

    /**
     * Activate a term – enforces single active term + date validation.
     *
     * @throws ValidationException
     */
    public function activateTerm(Term $term): void
    {
        $session = $term->academicSession;
        if (! $session->isActive) {
            throw ValidationException::withMessages(['session' => 'Parent session must be active first.']);
        }

        $this->validateTermDates($term, $session);

        DB::transaction(function () use ($term) {
            // Deactivate any other active term in this session
            Term::where('academic_session_id', $term->academic_session_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $term->update([
                'is_active'    => true,
                'status'       => 'active', // or use DynamicEnum lookup if needed
                'activated_at' => now(),
            ]);

            Cache::forget(self::CACHE_KEY_TERM . $term->school_id);
        });

        event(new TermActivated($term));
    }

    /**
     * Close a term – locks it for editing (can be reopened under restrictions).
     *
     * @throws ValidationException
     */
    public function closeTerm(Term $term): void
    {
        if (! $term->is_active) {
            throw ValidationException::withMessages(['status' => 'Only active terms can be closed.']);
        }

        DB::transaction(function () use ($term) {
            $term->update([
                'is_active' => false,
                'is_closed' => true,
                'status'    => 'closed',
                'closed_at' => now(),
            ]);

            Cache::forget(self::CACHE_KEY_TERM . $term->school_id);
        });

        event(new TermClosed($term));
    }

    /**
     * Attempt to reopen the most recently closed term (restricted).
     *
     * Allowed only if:
     * - This is the LAST closed term in the session
     * - The next term is still pending (not active/closed)
     *
     * @throws ValidationException
     */
    public function reopenTerm(Term $term): void
    {
        if (! $term->is_closed) {
            throw ValidationException::withMessages(['status' => 'Term is not closed.']);
        }

        $session = $term->academicSession;

        // Check if this is the most recently closed term
        $lastClosed = Term::where('academic_session_id', $session->id)
            ->where('is_closed', true)
            ->orderByDesc('closed_at')
            ->first();

        if ($lastClosed?->id !== $term->id) {
            throw ValidationException::withMessages(['term' => 'Only the most recently closed term can be reopened.']);
        }

        // Check if next term is still pending
        $nextTerm = Term::where('academic_session_id', $session->id)
            ->where('ordinal_number', $term->ordinal_number + 1)
            ->first();

        if ($nextTerm && $nextTerm->is_active || $nextTerm?->is_closed) {
            throw ValidationException::withMessages(['next_term' => 'Cannot reopen: next term has already started or closed.']);
        }

        DB::transaction(function () use ($term) {
            $term->update([
                'is_closed' => false,
                'is_active' => true, // Re-activate immediately
                'status'    => 'active',
                'closed_at' => null,
            ]);

            Cache::forget(self::CACHE_KEY_TERM . $term->school_id);
        });

        Log::info("Term {$term->name} reopened in session {$term->academicSession->name}", [
            'term_id' => $term->id,
            'user'    => auth()->id(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // Validation Helpers
    // ────────────────────────────────────────────────────────────────

    /**
     * Validate term dates against parent session.
     *
     * @throws ValidationException
     */
    public function validateTermDates(Term $term, AcademicSession $session): void
    {
        $errors = [];

        if ($term->start_date && $term->start_date->lt($session->start_date)) {
            $errors['start_date'] = 'Term start date cannot be before session start date.';
        }

        if ($term->end_date && $term->end_date->gt($session->end_date)) {
            $errors['end_date'] = 'Term end date cannot be after session end date.';
        }

        if ($term->start_date && $term->end_date && $term->start_date->gt($term->end_date)) {
            $errors['dates'] = 'Term start date must be before or equal to end date.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Check if a given date falls within the current active term.
     */
    public function isDateInCurrentTerm(Carbon|string $date): bool
    {
        $term = $this->currentTerm();
        if (! $term) {
            return false;
        }

        $date = Carbon::parse($date);

        return $date->between($term->start_date, $term->end_date);
    }
}
