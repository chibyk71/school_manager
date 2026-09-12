<?php

namespace App\Services\Academic;

use App\Contracts\Academic\TermOperationalDataBoundary;
use App\Events\Academic\TermActivated;
use App\Events\Academic\TermClosed;
use App\Events\Academic\TermCreated;
use App\Events\Academic\TermDeleted;
use App\Events\Academic\TermRestored;
use App\Events\Academic\TermUpdated;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\States\Academic\AcademicSession\Active as SessionActive;
use App\States\Academic\Term\Active as TermActive;
use App\States\Academic\Term\Closed as TermClosed;
use App\States\Academic\Term\Planned as TermPlanned;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative domain service for Term lifecycle operations (Phase 3).
 *
 * Controllers remain thin: authorize → validate input → invoke these operations.
 * Term ownership is derived through AcademicSession → School (no school_id on Term).
 *
 * Lifecycle: PLANNED → ACTIVE → CLOSED (no pause, no reopen).
 * Sequence (ordinal_number) is contiguous 1..N per session; service-owned.
 * Activation uses session-level locking to enforce single ACTIVE term.
 */
class TermLifecycleService
{
    private const CACHE_KEY_TERM = 'current_academic_term_';

    public function __construct(
        protected TermOperationalDataBoundary $operationalData
    ) {
    }

    /**
     * @param  array{name: string, short_name?: ?string, description?: ?string, start_date?: ?string, end_date?: ?string, color?: ?string, options?: ?array}  $attributes
     */
    public function create(AcademicSession $session, array $attributes): Term
    {
        $this->assertSessionBelongsToCurrentSchool($session);

        return DB::transaction(function () use ($session, $attributes) {
            $this->lockSessionTerms($session->id);
            $session = AcademicSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->assertSessionBelongsToCurrentSchool($session);

            $nextSequence = (int) Term::query()
                ->where('academic_session_id', $session->id)
                ->max('ordinal_number');
            $nextSequence = $nextSequence > 0 ? $nextSequence + 1 : 1;

            $term = new Term([
                'academic_session_id' => $session->id,
                'name' => $attributes['name'],
                'short_name' => $attributes['short_name'] ?? null,
                'ordinal_number' => $nextSequence,
                'description' => $attributes['description'] ?? null,
                'start_date' => $attributes['start_date'] ?? null,
                'end_date' => $attributes['end_date'] ?? null,
                'color' => $attributes['color'] ?? null,
                'options' => $attributes['options'] ?? null,
            ]);

            $term->save();

            $this->invalidateCaches($session->school_id);
            event(new TermCreated($term));
            $this->logLifecycle($term, 'created');

            return $term->fresh();
        });
    }

    /**
     * @param  array{name?: string, short_name?: ?string, description?: ?string, start_date?: ?string, end_date?: ?string, color?: ?string, options?: ?array}  $attributes
     */
    public function update(Term $term, array $attributes): Term
    {
        $session = $term->academicSession;
        $this->assertSessionBelongsToCurrentSchool($session);

        return DB::transaction(function () use ($term, $session, $attributes) {
            $this->lockSessionTerms($session->id);
            $term = Term::query()->whereKey($term->id)->lockForUpdate()->firstOrFail();
            $session = AcademicSession::query()->whereKey($term->academic_session_id)->lockForUpdate()->firstOrFail();
            $this->assertSessionBelongsToCurrentSchool($session);

            $hasOps = $this->operationalData->hasOperationalData($term);

            if (array_key_exists('start_date', $attributes)) {
                $newStart = $attributes['start_date'];
                $currentStart = $term->start_date?->format('Y-m-d');
                if ($hasOps && $newStart !== null && $newStart !== $currentStart) {
                    throw ValidationException::withMessages([
                        'start_date' => 'Start date cannot be changed after operational data exists for this term.',
                    ]);
                }
            }

            $fill = collect($attributes)->only([
                'name', 'short_name', 'description', 'start_date', 'end_date', 'color', 'options',
            ])->filter(fn ($v, $k) => array_key_exists($k, $attributes))->all();

            if (isset($fill['start_date']) || isset($fill['end_date'])) {
                $start = $fill['start_date'] ?? $term->start_date?->format('Y-m-d');
                $end = $fill['end_date'] ?? $term->end_date?->format('Y-m-d');
                if ($start && $end) {
                    $this->assertValidTermDates($session, $start, $end, $term->id);
                }
            }

            $term->fill($fill);
            $term->save();

            $this->invalidateCaches($session->school_id);
            event(new TermUpdated($term));
            $this->logLifecycle($term, 'updated');

            return $term->fresh();
        });
    }

    /**
     * @param  list<string>  $orderedTermIds
     */
    public function resequence(AcademicSession $session, array $orderedTermIds): void
    {
        $this->assertSessionBelongsToCurrentSchool($session);

        DB::transaction(function () use ($session, $orderedTermIds) {
            $this->lockSessionTerms($session->id);
            $session = AcademicSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->assertSessionBelongsToCurrentSchool($session);

            $terms = Term::query()
                ->where('academic_session_id', $session->id)
                ->orderBy('ordinal_number')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($terms->count() !== count($orderedTermIds)) {
                throw ValidationException::withMessages([
                    'sequence' => 'Reorder must include every non-deleted term in the session exactly once.',
                ]);
            }

            foreach ($orderedTermIds as $id) {
                if (! $terms->has($id)) {
                    throw ValidationException::withMessages([
                        'sequence' => 'Reorder includes a term that does not belong to this session.',
                    ]);
                }
            }

            $tempBase = 10000;
            foreach ($orderedTermIds as $i => $id) {
                $term = $terms->get($id);
                $newSeq = $i + 1;
                if ((int) $term->ordinal_number === $newSeq) {
                    continue;
                }
                if ($this->operationalData->hasOperationalData($term)) {
                    throw ValidationException::withMessages([
                        'sequence' => "Term \"{$term->name}\" has operational usage and cannot be renumbered.",
                    ]);
                }
                $term->forceFill(['ordinal_number' => $tempBase + $i])->save();
            }

            foreach ($orderedTermIds as $i => $id) {
                $term = $terms->get($id)->fresh();
                $newSeq = $i + 1;
                if ((int) $term->ordinal_number !== $newSeq) {
                    $term->forceFill(['ordinal_number' => $newSeq])->save();
                }
            }

            $this->invalidateCaches($session->school_id);
        });
    }

    public function activate(Term $term): Term
    {
        $session = $term->academicSession;
        $this->assertSessionBelongsToCurrentSchool($session);

        if (! ($term->state instanceof TermPlanned)) {
            throw ValidationException::withMessages([
                'state' => 'Only a PLANNED term can be activated.',
            ]);
        }

        return DB::transaction(function () use ($term, $session) {
            $this->lockSessionTerms($session->id);
            $term = Term::query()->whereKey($term->id)->lockForUpdate()->firstOrFail();
            $session = AcademicSession::query()->whereKey($term->academic_session_id)->lockForUpdate()->firstOrFail();
            $this->assertSessionBelongsToCurrentSchool($session);

            if (! ($term->state instanceof TermPlanned)) {
                throw ValidationException::withMessages([
                    'state' => 'Only a PLANNED term can be activated.',
                ]);
            }

            if (! ($session->state instanceof SessionActive)) {
                throw ValidationException::withMessages([
                    'session' => 'Parent session must be ACTIVE to activate a term. DRAFT, PLANNED, PAUSED, and CLOSED sessions cannot activate terms.',
                ]);
            }

            if (! $term->name || $term->ordinal_number === null) {
                throw ValidationException::withMessages([
                    'configuration' => 'Term must have a name and sequence before activation.',
                ]);
            }

            if (! $term->start_date || ! $term->end_date) {
                throw ValidationException::withMessages([
                    'dates' => 'Term must have start and end dates before activation.',
                ]);
            }

            $start = $term->start_date->format('Y-m-d');
            $end = $term->end_date->format('Y-m-d');
            $this->assertValidTermDates($session, $start, $end, $term->id);

            $activeSibling = Term::query()
                ->where('academic_session_id', $session->id)
                ->where('state', TermActive::$name)
                ->where('id', '!=', $term->id)
                ->lockForUpdate()
                ->exists();

            if ($activeSibling) {
                throw ValidationException::withMessages([
                    'state' => 'Another term is already ACTIVE in this session. Close it before activating a different term.',
                ]);
            }

            $term->state->transitionTo(TermActive::class);
            $term->save();

            $this->invalidateCaches($session->school_id);
            event(new TermActivated($term));
            $this->logLifecycle($term, 'activated', TermPlanned::$name, TermActive::$name);

            return $term->fresh();
        });
    }

    public function close(Term $term): Term
    {
        $session = $term->academicSession;
        $this->assertSessionBelongsToCurrentSchool($session);

        if (! ($term->state instanceof TermActive)) {
            throw ValidationException::withMessages([
                'state' => 'Only an ACTIVE term can be closed.',
            ]);
        }

        return DB::transaction(function () use ($term, $session) {
            $this->lockSessionTerms($session->id);
            $term = Term::query()->whereKey($term->id)->lockForUpdate()->firstOrFail();
            $session = AcademicSession::query()->whereKey($term->academic_session_id)->lockForUpdate()->firstOrFail();
            $this->assertSessionBelongsToCurrentSchool($session);

            if (! ($term->state instanceof TermActive)) {
                throw ValidationException::withMessages([
                    'state' => 'Only an ACTIVE term can be closed.',
                ]);
            }

            $term->state->transitionTo(TermClosed::class);
            $term->forceFill(['closed_at' => now()])->save();

            $this->invalidateCaches($session->school_id);
            event(new TermClosed($term));
            $this->logLifecycle($term, 'closed', TermActive::$name, TermClosed::$name);

            return $term->fresh();
        });
    }

    public function delete(Term $term): void
    {
        $session = $term->academicSession;
        $this->assertSessionBelongsToCurrentSchool($session);

        DB::transaction(function () use ($term, $session) {
            $this->lockSessionTerms($session->id);
            $term = Term::query()->whereKey($term->id)->lockForUpdate()->firstOrFail();
            $session = AcademicSession::query()->whereKey($term->academic_session_id)->lockForUpdate()->firstOrFail();
            $this->assertSessionBelongsToCurrentSchool($session);

            if ($this->operationalData->hasOperationalData($term)) {
                throw ValidationException::withMessages([
                    'term' => 'Cannot delete a term that has operational usage.',
                ]);
            }

            $this->assertNoDependentRecords($term);

            $term->delete();

            $remaining = Term::query()
                ->where('academic_session_id', $session->id)
                ->orderBy('ordinal_number')
                ->lockForUpdate()
                ->get();

            $expected = 1;
            foreach ($remaining as $t) {
                if ((int) $t->ordinal_number !== $expected) {
                    if ($this->operationalData->hasOperationalData($t)) {
                        throw ValidationException::withMessages([
                            'sequence' => "Deleting this term would require renumbering protected term \"{$t->name}\".",
                        ]);
                    }
                    $t->forceFill(['ordinal_number' => $expected])->save();
                }
                $expected++;
            }

            $this->invalidateCaches($session->school_id);
            event(new TermDeleted($term));
            $this->logLifecycle($term, 'deleted');
        });
    }

    public function restore(Term $term): Term
    {
        if (! $term->trashed()) {
            throw ValidationException::withMessages([
                'term' => 'Term is not deleted.',
            ]);
        }

        $session = $term->academicSession()->withTrashed()->first()
            ?? AcademicSession::query()->whereKey($term->academic_session_id)->firstOrFail();
        $this->assertSessionBelongsToCurrentSchool($session);

        return DB::transaction(function () use ($term, $session) {
            $this->lockSessionTerms($session->id);
            $term = Term::withTrashed()->whereKey($term->id)->lockForUpdate()->firstOrFail();
            $session = AcademicSession::query()->whereKey($term->academic_session_id)->lockForUpdate()->firstOrFail();
            $this->assertSessionBelongsToCurrentSchool($session);

            $conflict = Term::query()
                ->where('academic_session_id', $session->id)
                ->where('ordinal_number', $term->ordinal_number)
                ->exists();

            if ($conflict) {
                $max = (int) Term::query()->where('academic_session_id', $session->id)->max('ordinal_number');
                $term->forceFill(['ordinal_number' => $max + 1]);
            }

            $term->restore();

            $this->invalidateCaches($session->school_id);
            event(new TermRestored($term));
            $this->logLifecycle($term, 'restored');

            return $term->fresh();
        });
    }

    public function assertValidTermDates(
        AcademicSession $session,
        string $start,
        string $end,
        ?string $excludeTermId = null
    ): void {
        if ($start >= $end) {
            throw ValidationException::withMessages([
                'dates' => 'Term start date must be before end date.',
            ]);
        }

        $sessionStart = $session->start_date?->format('Y-m-d');
        $sessionEnd = $session->end_date?->format('Y-m-d');

        if ($sessionStart && $start < $sessionStart) {
            throw ValidationException::withMessages([
                'start_date' => 'Term start date must be on or after the session start date.',
            ]);
        }

        if ($sessionEnd && $end > $sessionEnd) {
            throw ValidationException::withMessages([
                'end_date' => 'Term end date must be on or before the session end date.',
            ]);
        }

        $siblings = Term::query()
            ->where('academic_session_id', $session->id)
            ->when($excludeTermId, fn ($q) => $q->where('id', '!=', $excludeTermId))
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->get(['id', 'name', 'start_date', 'end_date']);

        foreach ($siblings as $sibling) {
            $sStart = $sibling->start_date->format('Y-m-d');
            $sEnd = $sibling->end_date->format('Y-m-d');
            if ($start < $sEnd && $end > $sStart) {
                throw ValidationException::withMessages([
                    'dates' => "Term dates overlap with \"{$sibling->name}\" ({$sStart} – {$sEnd}). Adjacent periods are allowed.",
                ]);
            }
        }
    }

    protected function assertNoDependentRecords(Term $term): void
    {
        if (DB::getSchemaBuilder()->hasTable('timetables')) {
            $count = DB::table('timetables')->where('term_id', $term->id)->count();
            if ($count > 0) {
                throw ValidationException::withMessages([
                    'term' => 'Cannot delete term: dependent timetable records exist.',
                ]);
            }
        }

        if (DB::getSchemaBuilder()->hasTable('exams')) {
            $count = DB::table('exams')->where('term_id', $term->id)->count();
            if ($count > 0) {
                throw ValidationException::withMessages([
                    'term' => 'Cannot delete term: dependent exam records exist.',
                ]);
            }
        }
    }

    protected function assertSessionBelongsToCurrentSchool(AcademicSession $session): void
    {
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        if ($school && (string) $session->school_id !== (string) $school->id) {
            throw ValidationException::withMessages([
                'session' => 'The academic session does not belong to the current school.',
            ]);
        }
    }

    protected function lockSessionTerms(string $sessionId): void
    {
        AcademicSession::query()->whereKey($sessionId)->lockForUpdate()->first();
        Term::query()->where('academic_session_id', $sessionId)->lockForUpdate()->get();
    }

    protected function invalidateCaches(string $schoolId): void
    {
        Cache::forget(self::CACHE_KEY_TERM . $schoolId);
        Cache::forget('current_academic_session_' . $schoolId);
    }

    protected function logLifecycle(Term $term, string $action, ?string $from = null, ?string $to = null): void
    {
        Log::info("Term {$action}: {$term->name}", [
            'term_id' => $term->id,
            'session_id' => $term->academic_session_id,
            'from' => $from,
            'to' => $to,
            'user_id' => auth()->id(),
        ]);
    }
}
