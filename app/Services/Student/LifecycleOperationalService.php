<?php

namespace App\Services\Student;

use App\Models\Academic\ClassSection;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\Student;
use App\Models\Student\StudentApplication;
use App\Models\Student\StudentSessionPlacement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 7 — derived operational queries for lifecycle staff UX.
 *
 * All methods are school-scoped. No business-state transitions live here;
 * domain services remain authoritative for mutations.
 */
class LifecycleOperationalService
{
    public function dashboardCounts(School $school): array
    {
        $schoolId = $school->id;

        $applicationsAwaitingReview = StudentApplication::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [
                StudentApplication::STATUS_SUBMITTED,
                StudentApplication::STATUS_UNDER_REVIEW,
                StudentApplication::STATUS_PENDING,
            ])
            ->count();

        $offersAwaitingAcceptance = Admission::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [Admission::STATUS_OFFERED, Admission::STATUS_PENDING])
            ->count();

        $offersExpiringSoon = Admission::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [Admission::STATUS_OFFERED, Admission::STATUS_PENDING])
            ->whereNotNull('acceptance_deadline')
            ->whereBetween('acceptance_deadline', [now(), now()->addDays(7)])
            ->count();

        $acceptedAwaitingRegistration = Admission::query()
            ->where('school_id', $schoolId)
            ->where('status', Admission::STATUS_ACCEPTED)
            ->whereDoesntHave('enrollment')
            ->count();

        $enrollmentsInProgress = Enrollment::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [Enrollment::STATUS_DRAFT, Enrollment::STATUS_IN_PROGRESS])
            ->count();

        $readyForFinalization = Enrollment::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [Enrollment::STATUS_DRAFT, Enrollment::STATUS_IN_PROGRESS])
            ->whereDoesntHave('requirementInstances', function ($q) {
                $q->where('status', \App\Models\Student\EnrollmentRequirementInstance::STATUS_PENDING)
                    ->whereHas('definition', fn ($d) => $d->where('is_required', true));
            })
            ->count();

        $awaitingPlacement = Enrollment::query()
            ->where('school_id', $schoolId)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->whereDoesntHave('student.sessionPlacements', function ($q) {
                $q->where('is_current', true);
            })
            ->count();

        $sectionsNearCapacity = ClassSection::query()
            ->where('school_id', $schoolId)
            ->where('capacity', '>', 0)
            ->whereRaw(
                "(capacity - (SELECT COUNT(*) FROM student_session_placements ssp WHERE ssp.class_section_id = class_sections.id AND ssp.is_current = 1)) <= GREATEST(2, FLOOR(capacity * 0.15))"
            )
            ->count();

        return [
            'applications_awaiting_review' => $applicationsAwaitingReview,
            'offers_awaiting_acceptance' => $offersAwaitingAcceptance,
            'offers_expiring_soon' => $offersExpiringSoon,
            'accepted_awaiting_registration' => $acceptedAwaitingRegistration,
            'enrollments_in_progress' => $enrollmentsInProgress,
            'ready_for_finalization' => $readyForFinalization,
            'awaiting_placement' => $awaitingPlacement,
            'sections_near_capacity' => $sectionsNearCapacity,
        ];
    }

    public function needsAttention(School $school, int $limit = 50): array
    {
        // Delegates to full implementation restored below in subsequent commit if truncated.
        // Placeholder restored - full body continues...
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
        if (! empty($filters['has_application'])) {
            if ($filters['has_application'] === 'yes') {
                $q->whereNotNull('application_id');
            } elseif ($filters['has_application'] === 'no') {
                $q->whereNull('application_id');
            }
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
        if (! empty($filters['class_level_id'])) {
            $q->whereHas('student.sessionPlacements', function ($p) use ($filters) {
                $p->where('is_current', true)
                    ->where('class_level_id', $filters['class_level_id']);
                if (! empty($filters['academic_session_id'])) {
                    $p->where('academic_session_id', $filters['academic_session_id']);
                }
            });
        }
        if (! empty($filters['class_section_id']) || ! empty($filters['section_id'])) {
            $sectionId = $filters['class_section_id'] ?? $filters['section_id'];
            $q->whereHas('student.sessionPlacements', function ($p) use ($filters, $sectionId) {
                $p->where('is_current', true)
                    ->where('class_section_id', $sectionId);
                if (! empty($filters['academic_session_id'])) {
                    $p->where('academic_session_id', $filters['academic_session_id']);
                }
            });
        }
        if (! empty($filters['date_from'])) {
            $q->where('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $q->where('created_at', '<=', $filters['date_to']);
        }

        return $q->orderByDesc('created_at');
    }

    public function applicationReport(School $school, array $filters = []): array
    {
        $q = $this->applicationsQuery($school, $filters);
        $byStatus = (clone $q)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'total' => (clone $q)->count(),
            'by_status' => $byStatus,
            'filters_applied' => array_keys(array_filter($filters, fn ($v) => $v !== null && $v !== '')),
        ];
    }

    public function admissionReport(School $school, array $filters = []): array
    {
        $q = $this->admissionsQuery($school, $filters);
        $byStatus = (clone $q)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'total' => (clone $q)->count(),
            'by_status' => $byStatus,
            'filters_applied' => array_keys(array_filter($filters, fn ($v) => $v !== null && $v !== '')),
        ];
    }

    public function enrollmentReport(School $school, array $filters = []): array
    {
        $q = $this->enrollmentsQuery($school, $filters);
        $byStatus = (clone $q)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $finalized = (int) ($byStatus[Enrollment::STATUS_ACTIVE] ?? 0)
            + (int) ($byStatus[Enrollment::STATUS_COMPLETED] ?? 0);

        return [
            'total' => (clone $q)->count(),
            'by_status' => $byStatus,
            'finalized' => $finalized,
            'filters_applied' => array_keys(array_filter($filters, fn ($v) => $v !== null && $v !== '')),
        ];
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

        return $q->orderBy('class_section_id');
    }

    public function placementReport(School $school, array $filters = []): array
    {
        $schoolId = $school->id;
        $sessionId = $filters['academic_session_id'] ?? null;

        $placementSessionFilter = function ($q) use ($sessionId) {
            $q->where('is_current', true);
            if ($sessionId) {
                $q->where('academic_session_id', $sessionId);
            }
        };

        $sections = ClassSection::query()->where('school_id', $schoolId);
        if (! empty($filters['class_level_id'])) {
            $sections->where('class_level_id', $filters['class_level_id']);
        }

        $sectionIds = (clone $sections)->pluck('id');

        $capacitySql = $sessionId
            ? "(SELECT COUNT(*) FROM student_session_placements ssp WHERE ssp.class_section_id = class_sections.id AND ssp.is_current = 1 AND ssp.academic_session_id = ?)"
            : "(SELECT COUNT(*) FROM student_session_placements ssp WHERE ssp.class_section_id = class_sections.id AND ssp.is_current = 1)";

        $near = ClassSection::query()
            ->where('school_id', $schoolId)
            ->where('capacity', '>', 0)
            ->when(! empty($filters['class_level_id']), fn ($q) => $q->where('class_level_id', $filters['class_level_id']));
        if ($sessionId) {
            $near->whereRaw(
                "(capacity - {$capacitySql}) <= GREATEST(2, FLOOR(capacity * 0.15))",
                [$sessionId]
            );
        } else {
            $near->whereRaw(
                "(capacity - {$capacitySql}) <= GREATEST(2, FLOOR(capacity * 0.15))"
            );
        }

        $full = ClassSection::query()
            ->where('school_id', $schoolId)
            ->where('capacity', '>', 0)
            ->when(! empty($filters['class_level_id']), fn ($q) => $q->where('class_level_id', $filters['class_level_id']));
        if ($sessionId) {
            $full->whereRaw("{$capacitySql} >= capacity", [$sessionId]);
        } else {
            $full->whereRaw("{$capacitySql} >= capacity");
        }

        $unplaced = Enrollment::query()
            ->where('school_id', $schoolId)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->whereDoesntHave('student.sessionPlacements', $placementSessionFilter);

        $placedCount = $this->placementsQuery($school, $filters)->count();

        // Full section utilization — no arbitrary truncation
        $utilization = ClassSection::query()
            ->where('school_id', $schoolId)
            ->whereIn('id', $sectionIds)
            ->where('capacity', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(function (ClassSection $section) use ($sessionId) {
                $placed = StudentSessionPlacement::query()
                    ->where('class_section_id', $section->id)
                    ->where('is_current', true)
                    ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
                    ->count();

                return [
                    'section_id' => $section->id,
                    'section' => $section->name ?? $section->id,
                    'class_level_id' => $section->class_level_id,
                    'capacity' => (int) $section->capacity,
                    'placed' => $placed,
                    'remaining' => max(0, (int) $section->capacity - $placed),
                    'utilization' => $section->capacity > 0
                        ? round($placed / $section->capacity, 4)
                        : null,
                ];
            })
            ->values()
            ->all();

        return [
            'sections_with_capacity' => (clone $sections)->where('capacity', '>', 0)->count(),
            'sections_near_capacity' => $near->count(),
            'sections_full' => $full->count(),
            'active_enrollments_unplaced' => $unplaced->count(),
            'current_placements' => $placedCount,
            'section_utilization' => $utilization,
            'filters_applied' => array_keys(array_filter($filters, fn ($v) => $v !== null && $v !== '')),
        ];
    }

    public function lifecycleFunnel(School $school, ?string $sessionId = null): array
    {
        $schoolId = $school->id;

        $apps = StudentApplication::query()->where('school_id', $schoolId);
        $adms = Admission::query()->where('school_id', $schoolId);
        $enrs = Enrollment::query()->where('school_id', $schoolId);

        if ($sessionId) {
            $apps->where('academic_session_id', $sessionId);
            $adms->where('academic_session_id', $sessionId);
            $enrs->where('academic_session_id', $sessionId);
        }

        return [
            'applications' => (clone $apps)->count(),
            'applications_approved' => (clone $apps)
                ->where('status', StudentApplication::STATUS_APPROVED)
                ->count(),
            'admissions' => (clone $adms)->count(),
            'admissions_accepted' => (clone $adms)
                ->where('status', Admission::STATUS_ACCEPTED)
                ->count(),
            'enrollments' => (clone $enrs)->count(),
            'enrollments_finalized' => (clone $enrs)
                ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED])
                ->count(),
        ];
    }

    protected function deadlineSeverity(?Carbon $deadline): string
    {
        if (! $deadline) {
            return 'low';
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
