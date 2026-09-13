<?php

namespace App\Services\Academic;

use App\Contracts\Academic\TracksAcademicUsage;
use App\Models\Academic\AcademicPeriodUsage;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Academic period dependency registry.
 *
 * Sole responsibility: answer whether a Session or Term has dependent resources,
 * and keep those dependency rows synchronized for opt-in resources.
 *
 * Does not own deletion/date rules — those remain in Session/Term lifecycle services.
 *
 * Concurrency: register/unregister acquire AcademicPeriodLock::lockSchool() so they
 * serialize with Session/Term lifecycle mutations that use the same school lock.
 * Prefer calling register inside the owning service's DB transaction so resource
 * insert and usage row commit together while the school lock is held.
 */
class AcademicPeriodUsageRegistry
{
    public function register(TracksAcademicUsage&Model $resource): void
    {
        if (! $resource->shouldTrackAcademicUsage()) {
            $this->unregister($resource);

            return;
        }

        $schoolId = $resource->academicUsageSchoolId();
        $sessionId = $resource->academicUsageSessionId();
        $termId = $resource->academicUsageTermId();

        if ($schoolId === null || $sessionId === null) {
            throw ValidationException::withMessages([
                'academic_usage' => 'Resource must have school_id and academic_session_id to register academic usage.',
            ]);
        }

        $write = function () use ($resource, $schoolId, $sessionId, $termId) {
            AcademicPeriodLock::lockSchool($schoolId);
            $this->assertValidReferences($schoolId, $sessionId, $termId);

            $type = $resource->getMorphClass();
            $id = (string) $resource->getKey();

            AcademicPeriodUsage::query()->updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'resource_type' => $type,
                    'resource_id' => $id,
                ],
                [
                    'academic_session_id' => $sessionId,
                    'term_id' => $termId,
                ]
            );
        };

        if (AcademicPeriodLock::inTransaction()) {
            $write();
        } else {
            DB::transaction($write);
        }
    }

    public function unregister(TracksAcademicUsage&Model $resource): void
    {
        $type = $resource->getMorphClass();
        $id = (string) $resource->getKey();

        if ($id === '' || $id === '0') {
            return;
        }

        $schoolId = $resource->academicUsageSchoolId();

        $write = function () use ($type, $id, $schoolId) {
            if ($schoolId !== null) {
                AcademicPeriodLock::lockSchool($schoolId);
            }

            $query = AcademicPeriodUsage::query()
                ->where('resource_type', $type)
                ->where('resource_id', $id);

            if ($schoolId !== null) {
                $query->where('school_id', $schoolId);
            }

            $query->delete();
        };

        if (AcademicPeriodLock::inTransaction()) {
            $write();
        } else {
            DB::transaction($write);
        }
    }

    public function syncFromResource(TracksAcademicUsage&Model $resource): void
    {
        if ($resource->shouldTrackAcademicUsage()) {
            $this->register($resource);
        } else {
            $this->unregister($resource);
        }
    }

    public function hasSessionDependencies(AcademicSession $session): bool
    {
        return AcademicPeriodUsage::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->exists();
    }

    public function hasTermDependencies(Term $term): bool
    {
        $session = $this->resolveTermSession($term);

        return AcademicPeriodUsage::query()
            ->where('school_id', $session->school_id)
            ->where('term_id', $term->id)
            ->exists();
    }

    /** @return Collection<int, AcademicPeriodUsage> */
    public function getSessionDependencies(AcademicSession $session): Collection
    {
        return AcademicPeriodUsage::query()
            ->where('school_id', $session->school_id)
            ->where('academic_session_id', $session->id)
            ->orderBy('resource_type')
            ->orderBy('resource_id')
            ->get();
    }

    /** @return Collection<int, AcademicPeriodUsage> */
    public function getTermDependencies(Term $term): Collection
    {
        $session = $this->resolveTermSession($term);

        return AcademicPeriodUsage::query()
            ->where('school_id', $session->school_id)
            ->where('term_id', $term->id)
            ->orderBy('resource_type')
            ->orderBy('resource_id')
            ->get();
    }

    protected function resolveTermSession(Term $term): AcademicSession
    {
        $session = $term->relationLoaded('academicSession')
            ? $term->academicSession
            : AcademicSession::query()->whereKey($term->academic_session_id)->first();

        if ($session === null) {
            throw ValidationException::withMessages([
                'term_id' => 'Term has no resolvable academic session; cannot evaluate dependencies without a school boundary.',
            ]);
        }

        return $session;
    }

    protected function assertValidReferences(string $schoolId, string $sessionId, ?string $termId): void
    {
        $session = AcademicSession::query()
            ->whereKey($sessionId)
            ->first();

        if ($session === null) {
            throw ValidationException::withMessages([
                'academic_session_id' => 'Academic session does not exist.',
            ]);
        }

        if ((string) $session->school_id !== (string) $schoolId) {
            throw ValidationException::withMessages([
                'academic_session_id' => 'Academic session does not belong to the same school as the resource.',
            ]);
        }

        if ($termId === null) {
            return;
        }

        $term = Term::query()->whereKey($termId)->first();

        if ($term === null) {
            throw ValidationException::withMessages([
                'term_id' => 'Term does not exist.',
            ]);
        }

        if ((string) $term->academic_session_id !== (string) $sessionId) {
            throw ValidationException::withMessages([
                'term_id' => 'Term does not belong to the supplied academic session.',
            ]);
        }
    }
}
