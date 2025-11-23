<?php

namespace App\Services;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Central business logic for sessions & terms.
 *
 * All controllers should call these methods instead of raw queries.
 */
class AcademicSessionService
{
    /** Cache key for current session (per school) */
    private const CACHE_CURRENT_SESSION = 'current_academic_session_';

    /** Cache key for current term (per school) */
    private const CACHE_CURRENT_TERM = 'current_academic_term_';

    /* ------------------------------------------------------------------ */
    /* Current Session                                                    */
    /* ------------------------------------------------------------------ */
    public function currentSession(): ?AcademicSession
    {
        $school = GetSchoolModel();
        $key    = self::CACHE_CURRENT_SESSION . $school?->id;

        return Cache::remember($key, now()->addMinutes(15), fn() =>
            AcademicSession::where('school_id', $school?->id)
                ->where('is_current', true)
                ->first()
        );
    }

    public function setCurrentSession(AcademicSession $session): bool
    {
        $school = GetSchoolModel();

        // Deactivate any other current session
        AcademicSession::where('school_id', $school?->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $session->update(['is_current' => true]);

        Cache::forget(self::CACHE_CURRENT_SESSION . $school?->id);
        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Current Term                                                       */
    /* ------------------------------------------------------------------ */
    public function currentTerm(): ?Term
    {
        $school = GetSchoolModel();
        $key    = self::CACHE_CURRENT_TERM . $school->id;

        return Cache::remember($key, now()->addMinutes(15), function () {
            $session = $this->currentSession();
            if (! $session) {
                return null;
            }
            return Term::active()
                ->forAcademicSession($session->id)
                ->first();
        });
    }

    public function setActiveTerm(Term $term): bool
    {
        // Deactivate others in same session
        Term::where('academic_session_id', $term->academic_session_id)
            ->where('status', 'active')
            ->update(['status' => 'pending']);

        $term->update(['status' => 'active']);

        Cache::forget(self::CACHE_CURRENT_TERM . GetSchoolModel()->id);
        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */
    public function isDateInCurrentTerm($date): bool
    {
        $term = $this->currentTerm();
        if (! $term) {
            return false;
        }
        $date = \Carbon\Carbon::parse($date);
        return $date->between($term->start_date, $term->end_date);
    }
}
