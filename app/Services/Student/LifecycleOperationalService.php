<?php

namespace App\Services\Student;

use App\Models\Academic\ClassSection;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\StudentApplication;
use App\Models\Student\StudentSessionPlacement;
use Illuminate\Support\Collection;

/**
 * Phase 7 — derived operational queries for lifecycle staff UX.
 * TEMP: full implementation is in git bundle artifacts/phase7/phase7-complete.bundle (commit 36ab59a).
 * This stub prevents a fatal parse error until the full file is restored.
 */
class LifecycleOperationalService
{
    public function dashboardCounts(School $school): array
    {
        $schoolId = $school->id;

        return [
            'applications_awaiting_review' => StudentApplication::query()->where('school_id', $schoolId)->whereIn('status', [
                StudentApplication::STATUS_SUBMITTED,
                StudentApplication::STATUS_UNDER_REVIEW,
                StudentApplication::STATUS_PENDING,
            ])->count(),
            'offers_awaiting_acceptance' => Admission::query()->where('school_id', $schoolId)->whereIn('status', [Admission::STATUS_OFFERED, Admission::STATUS_PENDING])->count(),
            'offers_expiring_soon' => 0,
            'accepted_awaiting_registration' => Admission::query()->where('school_id', $schoolId)->where('status', Admission::STATUS_ACCEPTED)->whereDoesntHave('enrollment')->count(),
            'enrollments_in_progress' => Enrollment::query()->where('school_id', $schoolId)->whereIn('status', [Enrollment::STATUS_DRAFT, Enrollment::STATUS_IN_PROGRESS])->count(),
            'ready_for_finalization' => 0,
            'awaiting_placement' => 0,
            'sections_near_capacity' => 0,
        ];
    }

    public function needsAttention(School $school, int $limit = 50): array
    {
        return ['items' => [], 'returned_count' => 0, 'total' => 0];
    }

    public function upcomingDeadlines(School $school, int $withinDays = 14, int $limit = 50): array
    {
        return ['items' => [], 'returned_count' => 0, 'total' => 0];
    }

    public function recentlyCompleted(School $school, int $withinDays = 14, int $limit = 50): array
    {
        return ['items' => [], 'returned_count' => 0, 'total' => 0];
    }

    public function applicationsQuery(School $school, array $filters = [])
    {
        $q = StudentApplication::query()->where('school_id', $school->id);
        if (! empty($filters['academic_session_id'])) {
            $q->where('academic_session_id', $filters['academic_session_id']);
        }
        if (! empty($filters['status'])) {
            $q->whereIn('status', (array) $filters['status']);
        }
        if (! empty($filters['class_level_id'])) {
            $q->where('class_level_id', $filters['class_level_id']);
        }
        if (! empty($filters['source'])) {
            $q->where('source', $filters['source']);
        }
        if (! empty($filters['date_from'])) {
            $q->where('submitted_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $q->where('submitted_at', '<=', $filters['date_to']);
        }

        return $q->orderBy('submitted_at');
    }

    public function admissionsQuery(School $school, array $filters = [])
    {
        $q = Admission::query()->where('school_id', $school->id);
        if (! empty($filters['academic_session_id'])) {
            $q->where('academic_session_id', $filters['academic_session_id']);
        }
        if (! empty($filters['status'])) {
            $q->whereIn('status', (array) $filters['status']);
        }
        if (! empty($filters['class_level_id'])) {
            $q->where('class_level_id', $filters['class_level_id']);
        }
        if (! empty($filters['origin'])) {
            if ($filters['origin'] === 'application') {
                $q->whereNotNull('application_id');
            } elseif ($filters['origin'] === 'direct') {
                $q->whereNull('application_id');
            }
        }
        if (! empty($filters['date_from'])) {
            $q->where(function ($inner) use ($filters) {
                $inner->where('offered_at', '>=', $filters['date_from'])
                    ->orWhere('created_at', '>=', $filters['date_from']);
            });
        }
        if (! empty($filters['date_to'])) {
            $q->where(function ($inner) use ($filters) {
                $inner->where('offered_at', '<=', $filters['date_to'])
                    ->orWhere(function ($q2) use ($filters) {
                        $q2->whereNull('offered_at')->where('created_at', '<=', $filters['date_to']);
                    });
            });
        }

        return $q->orderByDesc('offered_at');
    }

    public function enrollmentsQuery(School $school, array $filters = [])
    {
        $q = Enrollment::query()->where('school_id', $school->id);
        if (! empty($filters['academic_session_id'])) {
            $q->where('academic_session_id', $filters['academic_session_id']);
        }
        if (! empty($filters['status'])) {
            $q->whereIn('status', (array) $filters['status']);
        }
        if (array_key_exists('finalized', $filters) && $filters['finalized'] !== null && $filters['finalized'] !== '') {
            if (filter_var($filters['finalized'], FILTER_VALIDATE_BOOLEAN)) {
                $q->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED]);
            } else {
                $q->whereIn('status', [Enrollment::STATUS_DRAFT, Enrollment::STATUS_IN_PROGRESS]);
            }
        }
        if (! empty($filters['origin'])) {
            if ($filters['origin'] === 'admission') {
                $q->whereNotNull('admission_id');
            } elseif ($filters['origin'] === 'direct') {
                $q->whereNull('admission_id');
            }
        }
        if (! empty($filters['date_from'])) {
            $q->where('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $q->where('created_at', '<=', $filters['date_to']);
        }

        return $q->orderByDesc('created_at');
    }

    public function placementsQuery(School $school, array $filters = [])
    {
        $q = StudentSessionPlacement::query()
            ->whereHas('student', fn ($s) => $s->where('school_id', $school->id))
            ->where('is_current', true);

        if (! empty($filters['academic_session_id'])) {
            $q->where('academic_session_id', $filters['academic_session_id']);
        }
        if (! empty($filters['class_level_id'])) {
            $q->where('class_level_id', $filters['class_level_id']);
        }
        if (! empty($filters['class_section_id']) || ! empty($filters['section_id'])) {
            $q->where('class_section_id', $filters['class_section_id'] ?? $filters['section_id']);
        }

        return $q->orderByDesc('enrolled_at');
    }

    public function applicationReport(School $school, array $filters = []): array
    {
        $q = $this->applicationsQuery($school, $filters);
        $byStatus = (clone $q)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->all();

        return ['total' => array_sum($byStatus), 'by_status' => $byStatus];
    }

    public function admissionReport(School $school, array $filters = []): array
    {
        $q = $this->admissionsQuery($school, $filters);
        $byStatus = (clone $q)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->all();

        return ['total' => array_sum($byStatus), 'by_status' => $byStatus];
    }

    public function enrollmentReport(School $school, array $filters = []): array
    {
        $q = $this->enrollmentsQuery($school, $filters);
        $byStatus = (clone $q)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->all();

        return ['total' => array_sum($byStatus), 'by_status' => $byStatus];
    }

    public function placementReport(School $school, array $filters = []): array
    {
        $sessionId = $filters['academic_session_id'] ?? null;

        return [
            'sections_with_capacity' => ClassSection::query()->where('school_id', $school->id)->where('capacity', '>', 0)->count(),
            'sections_near_capacity' => 0,
            'sections_full' => 0,
            'active_enrollments_unplaced' => Enrollment::query()
                ->where('school_id', $school->id)
                ->where('status', Enrollment::STATUS_ACTIVE)
                ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
                ->whereDoesntHave('student.sessionPlacements', function ($q) use ($sessionId) {
                    $q->where('is_current', true);
                    if ($sessionId) {
                        $q->where('academic_session_id', $sessionId);
                    }
                })
                ->count(),
            'current_placements' => $this->placementsQuery($school, $filters)->count(),
            'section_utilization' => [],
        ];
    }

    public function lifecycleFunnel(School $school, ?string $sessionId = null): array
    {
        $apps = StudentApplication::query()->where('school_id', $school->id);
        $adms = Admission::query()->where('school_id', $school->id);
        $enrs = Enrollment::query()->where('school_id', $school->id);
        if ($sessionId) {
            $apps->where('academic_session_id', $sessionId);
            $adms->where('academic_session_id', $sessionId);
            $enrs->where('academic_session_id', $sessionId);
        }

        return [
            'applications' => (clone $apps)->count(),
            'applications_approved' => (clone $apps)->where('status', StudentApplication::STATUS_APPROVED)->count(),
            'admissions' => (clone $adms)->count(),
            'admissions_accepted' => (clone $adms)->where('status', Admission::STATUS_ACCEPTED)->count(),
            'enrollments' => (clone $enrs)->count(),
            'enrollments_finalized' => (clone $enrs)->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED])->count(),
        ];
    }

    protected function deadlineSeverity($deadline): string
    {
        if (! $deadline) {
            return 'medium';
        }
        $hours = now()->diffInHours($deadline, false);
        if ($hours < 0) {
            return 'critical';
        }
        if ($hours <= 24) {
            return 'critical';
        }
        if ($hours <= 72) {
            return 'high';
        }

        return 'medium';
    }
}
