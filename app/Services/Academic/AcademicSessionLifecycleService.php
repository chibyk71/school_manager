<?php

namespace App\Services\Academic;

use App\Contracts\Academic\AcademicSessionOperationalDataBoundary;
use App\Events\Academic\SessionActivated;
use App\Events\Academic\SessionClosed;
use App\Events\Academic\SessionPaused;
use App\Events\Academic\SessionPlanned;
use App\Events\Academic\SessionReopened;
use App\Events\Academic\SessionResumed;
use App\Models\School;
use App\Models\Academic\AcademicSession;
use App\States\Academic\AcademicSession\Active;
use App\States\Academic\AcademicSession\Closed;
use App\States\Academic\AcademicSession\Draft;
use App\States\Academic\AcademicSession\Paused;
use App\States\Academic\AcademicSession\Planned;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\ModelStates\Exceptions\TransitionNotAllowed;

/**
 * Authoritative domain service for AcademicSession lifecycle operations (Phase 2).
 *
 * Controllers remain thin: authorize → validate input → invoke these operations.
 * Current operational session = ACTIVE or PAUSED (at most one per school).
 * Activation/resume never silently close or modify another session.
 */
class AcademicSessionLifecycleService
{
    private const CACHE_KEY_SESSION = 'current_academic_session_';
    private const CACHE_KEY_TERM = 'current_academic_term_';

    public function __construct(
        protected AcademicSessionOperationalDataBoundary $operationalData
    ) {
    }

    public function plan(AcademicSession $session): AcademicSession
    {
        $this->assertSchoolOwnership($session);

        if (! ($session->state instanceof Draft)) {
            throw ValidationException::withMessages([
                'state' => 'Only a DRAFT session can be planned.',
            ]);
        }

        $this->assertPlanningRequirements($session);
        $this->assertNoDateOverlap($session);

        return DB::transaction(function () use ($session) {
            $session->state->transitionTo(Planned::class);
            $session->save();
            $this->logLifecycle($session, 'planned', Draft::$name, Planned::$name);
            event(new SessionPlanned($session));

            return $session->fresh();
        });
    }

    public function activate(AcademicSession $session): AcademicSession
    {
        $this->assertSchoolOwnership($session);

        if ($session->state instanceof Active) {
            return $session;
        }

        if (! ($session->state instanceof Draft) && ! ($session->state instanceof Planned)) {
            throw ValidationException::withMessages([
                'state' => 'Session must be DRAFT or PLANNED to activate.',
            ]);
        }

        $this->assertPlanningRequirements($session);
        $this->assertNoDateOverlap($session);

        return DB::transaction(function () use ($session) {
            $this->lockSchoolSessions($session->school_id);
            $this->assertNoCurrentOperationalSession($session->school_id, $session->id);

            try {
                $session->state->transitionTo(Active::class);
            } catch (TransitionNotAllowed $e) {
                throw ValidationException::withMessages([
                    'state' => 'Session cannot transition to ACTIVE from its current state.',
                ]);
            }

            $session->forceFill(['activated_at' => now(), 'closed_at' => null])->save();
            $this->invalidateCaches($session->school_id);
            $this->logLifecycle($session, 'activated', (string) ($session->getOriginal('state') ?? 'unknown'), Active::$name);
            event(new SessionActivated($session));

            return $session->fresh();
        });
    }

    public function pause(AcademicSession $session): AcademicSession
    {
        $this->assertSchoolOwnership($session);

        if (! ($session->state instanceof Active)) {
            throw ValidationException::withMessages([
                'state' => 'Only an ACTIVE session can be paused.',
            ]);
        }

        return DB::transaction(function () use ($session) {
            $this->lockSchoolSessions($session->school_id);
            $session->state->transitionTo(Paused::class);
            $session->save();
            $this->invalidateCaches($session->school_id);
            $this->logLifecycle($session, 'paused', Active::$name, Paused::$name);
            event(new SessionPaused($session));

            return $session->fresh();
        });
    }

    public function resume(AcademicSession $session): AcademicSession
    {
        $this->assertSchoolOwnership($session);

        if (! ($session->state instanceof Paused)) {
            throw ValidationException::withMessages([
                'state' => 'Only a PAUSED session can be resumed.',
            ]);
        }

        return DB::transaction(function () use ($session) {
            $this->lockSchoolSessions($session->school_id);
            $this->assertNoCurrentOperationalSession($session->school_id, $session->id);
            $session->state->transitionTo(Active::class);
            $session->save();
            $this->invalidateCaches($session->school_id);
            $this->logLifecycle($session, 'resumed', Paused::$name, Active::$name);
            event(new SessionResumed($session));
            event(new SessionActivated($session));

            return $session->fresh();
        });
    }

    public function close(AcademicSession $session): AcademicSession
    {
        $this->assertSchoolOwnership($session);

        if (! ($session->state instanceof Active) && ! ($session->state instanceof Paused)) {
            throw ValidationException::withMessages([
                'state' => 'Only an ACTIVE or PAUSED session can be closed.',
            ]);
        }

        $previous = $session->state instanceof Active ? Active::$name : Paused::$name;

        return DB::transaction(function () use ($session, $previous) {
            $this->lockSchoolSessions($session->school_id);
            $session->state->transitionTo(Closed::class);
            $session->forceFill(['closed_at' => now()])->save();
            $this->invalidateCaches($session->school_id);
            $this->logLifecycle($session, 'closed', $previous, Closed::$name);
            event(new SessionClosed($session));

            return $session->fresh();
        });
    }

    public function reopen(AcademicSession $session): AcademicSession
    {
        $this->assertSchoolOwnership($session);

        if (! ($session->state instanceof Closed)) {
            throw ValidationException::withMessages([
                'state' => 'Only a CLOSED session can be reopened.',
            ]);
        }

        $this->assertPlanningRequirements($session);
        $this->assertNoDateOverlap($session);

        return DB::transaction(function () use ($session) {
            $this->lockSchoolSessions($session->school_id);
            $this->assertNoCurrentOperationalSession($session->school_id, $session->id);
            $this->assertReopenNotBlockedByLaterSessions($session);
            $session->state->transitionTo(Active::class);
            $session->forceFill([
                'activated_at' => $session->activated_at ?? now(),
                'closed_at' => null,
            ])->save();
            $this->invalidateCaches($session->school_id);
            $this->logLifecycle($session, 'reopened', Closed::$name, Active::$name);
            event(new SessionReopened($session));
            event(new SessionActivated($session));

            return $session->fresh();
        });
    }

    public function updateDates(AcademicSession $session, ?string $startDate, ?string $endDate): AcademicSession
    {
        $this->assertSchoolOwnership($session);
        $hasOps = $this->operationalData->hasOperationalData($session);

        if ($hasOps && $startDate !== null && (string) $session->start_date?->format('Y-m-d') !== $startDate) {
            throw ValidationException::withMessages([
                'start_date' => 'Start date cannot be changed after operational data exists for this session.',
            ]);
        }

        $newStart = $startDate !== null ? $startDate : $session->start_date?->format('Y-m-d');
        $newEnd = $endDate !== null ? $endDate : $session->end_date?->format('Y-m-d');

        if ($newStart && $newEnd && $newStart >= $newEnd) {
            throw ValidationException::withMessages([
                'dates' => 'Session start date must be before end date.',
            ]);
        }

        $originalStart = $session->start_date;
        $originalEnd = $session->end_date;
        $session->start_date = $newStart;
        $session->end_date = $newEnd;

        try {
            $this->assertNoDateOverlap($session);
        } finally {
            $session->start_date = $originalStart;
            $session->end_date = $originalEnd;
        }

        $session->forceFill(['start_date' => $newStart, 'end_date' => $newEnd])->save();

        return $session->fresh();
    }

    public function isCurrentOperational(AcademicSession $session): bool
    {
        return $session->state instanceof Active || $session->state instanceof Paused;
    }

    public function currentOperationalSession(string $schoolId): ?AcademicSession
    {
        return AcademicSession::query()
            ->where('school_id', $schoolId)
            ->whereIn('state', [Active::$name, Paused::$name])
            ->first();
    }

    public function invalidateCaches(string $schoolId): void
    {
        Cache::forget(self::CACHE_KEY_SESSION . $schoolId);
        Cache::forget(self::CACHE_KEY_TERM . $schoolId);
    }

    protected function assertSchoolOwnership(AcademicSession $session): void
    {
        $school = GetSchoolModel();
        if (! $school || $session->school_id !== $school->id) {
            throw ValidationException::withMessages([
                'session' => 'Session does not belong to the current school.',
            ]);
        }
    }

    protected function assertPlanningRequirements(AcademicSession $session): void
    {
        $errors = [];
        if (blank($session->name)) {
            $errors['name'] = 'Session name is required.';
        }
        if (! $session->start_date) {
            $errors['start_date'] = 'Session start date is required for this operation.';
        }
        if (! $session->end_date) {
            $errors['end_date'] = 'Session end date is required for this operation.';
        }
        if ($session->start_date && $session->end_date && $session->start_date->gte($session->end_date)) {
            $errors['dates'] = 'Session start date must be before end date.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function assertNoDateOverlap(AcademicSession $session): void
    {
        if (! $session->start_date || ! $session->end_date) {
            return;
        }

        $overlapping = AcademicSession::query()
            ->where('school_id', $session->school_id)
            ->where('id', '!=', $session->id)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '<=', $session->end_date)
            ->where('end_date', '>=', $session->start_date)
            ->exists();

        if ($overlapping) {
            throw ValidationException::withMessages([
                'dates' => 'Session dates overlap another session for this school. Adjacent ranges are allowed; overlapping ranges are not.',
            ]);
        }
    }

    protected function assertNoCurrentOperationalSession(string $schoolId, ?string $exceptId = null): void
    {
        $query = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->whereIn('state', [Active::$name, Paused::$name]);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'session' => 'Another session is already current (ACTIVE or PAUSED). Close or pause it explicitly before activating or resuming this session.',
            ]);
        }
    }

    protected function assertReopenNotBlockedByLaterSessions(AcademicSession $session): void
    {
        if (! $session->end_date) {
            return;
        }

        $later = AcademicSession::query()
            ->where('school_id', $session->school_id)
            ->where('id', '!=', $session->id)
            ->where(function ($q) use ($session) {
                $q->where('start_date', '>', $session->end_date)
                    ->orWhere('created_at', '>', $session->created_at);
            })
            ->get();

        foreach ($later as $other) {
            if ($other->state instanceof Active || $other->state instanceof Paused) {
                throw ValidationException::withMessages([
                    'session' => 'Cannot reopen: a later operational session (ACTIVE or PAUSED) exists.',
                ]);
            }
            if ($other->state instanceof Closed && $this->operationalData->hasOperationalData($other)) {
                throw ValidationException::withMessages([
                    'session' => 'Cannot reopen: a later CLOSED session with operational data exists.',
                ]);
            }
        }
    }

    /**
     * School-level serialization for concurrency-sensitive lifecycle ops.
     *
     * Locking only academic_sessions rows fails when the school has zero sessions:
     * two concurrent activates both see an empty lock set and both proceed.
     * Lock the stable schools row (always present) inside the transaction first.
     */
    protected function lockSchoolSessions(string $schoolId): void
    {
        School::query()
            ->whereKey($schoolId)
            ->lockForUpdate()
            ->first(['id']);

        AcademicSession::query()
            ->where('school_id', $schoolId)
            ->lockForUpdate()
            ->get(['id', 'state']);
    }

    protected function logLifecycle(AcademicSession $session, string $operation, string $from, string $to): void
    {
        activity('academic_session')
            ->performedOn($session)
            ->withProperties([
                'operation' => $operation,
                'previous_state' => $from,
                'new_state' => $to,
                'session_id' => $session->id,
                'school_id' => $session->school_id,
            ])
            ->log("Academic session \"{$session->name}\" {$operation} ({$from} → {$to})");

        Log::info("Academic session lifecycle: {$operation}", [
            'session_id' => $session->id,
            'from' => $from,
            'to' => $to,
            'user_id' => auth()->id(),
        ]);
    }
}
