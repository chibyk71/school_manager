<?php

namespace App\Support;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Models\School;
use App\States\Academic\AcademicSession\Active as SessionActive;
use App\States\Academic\AcademicSession\Paused as SessionPaused;
use App\States\Academic\Term\Active as TermActive;
use InvalidArgumentException;

/**
 * Immutable snapshot of the school's current operational academic context.
 *
 * Invariants:
 * - session belongs to school
 * - session is ACTIVE or PAUSED
 * - sessionState matches the session's authoritative state
 * - term, when present, belongs to the session and is ACTIVE
 * - term may be null
 */
final class AcademicContext
{
    public function __construct(
        public readonly School $school,
        public readonly AcademicSession $session,
        public readonly ?Term $term,
        public readonly string $sessionState,
    ) {
        if ((string) $session->school_id !== (string) $school->id) {
            throw new InvalidArgumentException('AcademicContext session must belong to the supplied school.');
        }

        $stateName = $this->resolveStateName($session);
        if (! in_array($stateName, [SessionActive::$name, SessionPaused::$name], true)) {
            throw new InvalidArgumentException(
                'AcademicContext session must be ACTIVE or PAUSED; got: '.$stateName
            );
        }

        if ($sessionState !== $stateName) {
            throw new InvalidArgumentException(
                'AcademicContext sessionState must match the session authoritative state.'
            );
        }

        if ($term !== null) {
            if ((string) $term->academic_session_id !== (string) $session->id) {
                throw new InvalidArgumentException('AcademicContext term must belong to the session.');
            }

            $termState = $this->resolveTermStateName($term);
            if ($termState !== TermActive::$name) {
                throw new InvalidArgumentException(
                    'AcademicContext term must be ACTIVE when present; got: '.$termState
                );
            }
        }
    }

    private function resolveStateName(AcademicSession $session): string
    {
        $state = $session->state;
        if (is_object($state) && method_exists($state, 'getValue')) {
            return (string) $state->getValue();
        }

        return (string) $state;
    }

    private function resolveTermStateName(Term $term): string
    {
        $state = $term->state;
        if (is_object($state) && method_exists($state, 'getValue')) {
            return (string) $state->getValue();
        }

        return (string) $state;
    }
}
