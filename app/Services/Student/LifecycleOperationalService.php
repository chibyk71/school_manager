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
    /**
     * Actionable counts for dashboard cards.
     *
     * @return array<string, int>
     */
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
            ->whereRaw('capacity > 0')
            ->get()
            ->filter(function (ClassSection $section) {
                $remaining = $section->getRemainingCapacity();
                if ($remaining === null) {
                    return false;
                }
                $capacity = (int) $section->capacity;
                // Approaching = less than 15% remaining or ≤ 2 seats.
                return $remaining <= max(2, (int) floor($capacity * 0.15));
            })
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

    /**
     * Unified Needs Attention feed.
     *
     * @return array{items: list<array>, total: int}
     */
    public function needsAttention(School $school, int $limit = 50): array
    {
        $schoolId = $school->id;
        $items = collect();

        StudentApplication::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [
                StudentApplication::STATUS_SUBMITTED,
                StudentApplication::STATUS_UNDER_REVIEW,
                StudentApplication::STATUS_PENDING,
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->each(function (StudentApplication $app) use ($items) {
                $items->push([
                    'type' => 'application_awaiting_review',
                    'severity' => 'medium',
                    'id' => $app->id,
                    'label' => $app->application_number
                        ?? trim(($app->first_name ?? '').' '.($app->last_name ?? ''))
                        ?: 'Application',
                    'status' => $app->status,
                    'at' => optional($app->submitted_at ?? $app->updated_at)?->toIso8601String(),
                    'route' => 'applications.show',
                    'route_params' => ['application' => $app->id],
                ]);
            });

        Admission::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [Admission::STATUS_OFFERED, Admission::STATUS_PENDING])
            ->orderBy('acceptance_deadline')
            ->limit($limit)
            ->get()
            ->each(function (Admission $adm) use ($items) {
                $items->push([
                    'type' => 'offer_awaiting_acceptance',
                    'severity' => $this->deadlineSeverity($adm->acceptance_deadline),
                    'id' => $adm->id,
                    'label' => $adm->admission_number ?? 'Admission offer',
                    'status' => $adm->status,
                    'deadline' => optional($adm->acceptance_deadline)?->toIso8601String(),
                    'at' => optional($adm->offered_at ?? $adm->created_at)?->toIso8601String(),
                    'route' => 'admissions.lifecycle.show',
                    'route_params' => ['admission' => $adm->id],
                ]);
            });

        Admission::query()
            ->where('school_id', $schoolId)
            ->where('status', Admission::STATUS_ACCEPTED)
            ->whereDoesntHave('enrollment')
            ->orderByDesc('accepted_at')
            ->limit($limit)
            ->get()
            ->each(function (Admission $adm) use ($items) {
                $items->push([
                    'type' => 'accepted_awaiting_registration',
                    'severity' => 'high',
                    'id' => $adm->id,
                    'label' => $adm->admission_number ?? 'Accepted admission',
                    'status' => $adm->status,
                    'at' => optional($adm->accepted_at)?->toIso8601String(),
                    'route' => 'admissions.lifecycle.show',
                    'route_params' => ['admission' => $adm->id],
                ]);
            });

        Enrollment::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [Enrollment::STATUS_DRAFT, Enrollment::STATUS_IN_PROGRESS])
            ->withCount([
                'requirementInstances as outstanding_requirements_count' => function ($q) {
                    $q->where('status', \App\Models\Student\EnrollmentRequirementInstance::STATUS_PENDING)
                        ->whereHas('definition', fn ($d) => $d->where('is_required', true));
                },
            ])
            ->orderBy('updated_at')
            ->limit($limit)
            ->get()
            ->each(function (Enrollment $enr) use ($items) {
                $items->push([
                    'type' => $enr->outstanding_requirements_count > 0
                        ? 'enrollment_requirements_outstanding'
                        : 'enrollment_incomplete',
                    'severity' => $enr->outstanding_requirements_count > 0 ? 'high' : 'medium',
                    'id' => $enr->id,
                    'label' => 'Enrollment '.$enr->id,
                    'status' => $enr->status,
                    'outstanding_requirements' => (int) $enr->outstanding_requirements_count,
                    'at' => optional($enr->updated_at)?->toIso8601String(),
                    'route' => 'enrollments.show',
                    'route_params' => ['enrollment' => $enr->id],
                ]);
            });

        $sorted = $items
            ->sortBy(function (array $item) {
                $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

                return ($severityOrder[$item['severity'] ?? 'low'] ?? 9).'|'.($item['at'] ?? '');
            })
            ->values()
            ->take($limit);

        return [
            'items' => $sorted->all(),
            'total' => $sorted->count(),
        ];
    }

    /**
     * @return array{items: list<array>, total: int}
     */
    public function upcomingDeadlines(School $school, int $withinDays = 14, int $limit = 50): array
    {
        $schoolId = $school->id;
        $until = now()->addDays($withinDays);
        $items = collect();

        Admission::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [Admission::STATUS_OFFERED, Admission::STATUS_PENDING])
            ->whereNotNull('acceptance_deadline')
            ->whereBetween('acceptance_deadline', [now(), $until])
            ->orderBy('acceptance_deadline')
            ->limit($limit)
            ->get()
            ->each(function (Admission $adm) use ($items) {
                $items->push([
                    'type' => 'acceptance_deadline',
                    'id' => $adm->id,
                    'label' => $adm->admission_number ?? 'Admission offer',
                    'deadline' => $adm->acceptance_deadline->toIso8601String(),
                    'severity' => $this->deadlineSeverity($adm->acceptance_deadline),
                    'route' => 'admissions.lifecycle.show',
                    'route_params' => ['admission' => $adm->id],
                ]);
            });

        Admission::query()
            ->where('school_id', $schoolId)
            ->where('status', Admission::STATUS_ACCEPTED)
            ->whereNotNull('registration_ends_at')
            ->whereBetween('registration_ends_at', [now(), $until])
            ->orderBy('registration_ends_at')
            ->limit($limit)
            ->get()
            ->each(function (Admission $adm) use ($items) {
                $items->push([
                    'type' => 'registration_window_end',
                    'id' => $adm->id,
                    'label' => $adm->admission_number ?? 'Registration window',
                    'deadline' => $adm->registration_ends_at->toIso8601String(),
                    'severity' => $this->deadlineSeverity($adm->registration_ends_at),
                    'route' => 'admissions.lifecycle.show',
                    'route_params' => ['admission' => $adm->id],
                ]);
            });

        $sorted = $items->sortBy('deadline')->values()->take($limit);

        return [
            'items' => $sorted->all(),
            'total' => $sorted->count(),
        ];
    }

    /**
     * @return array{items: list<array>, total: int}
     */
    public function recentlyCompleted(School $school, int $withinDays = 14, int $limit = 50): array
    {
        $schoolId = $school->id;
        $since = now()->subDays($withinDays);
        $items = collect();

        StudentApplication::query()
            ->where('school_id', $schoolId)
            ->where('status', StudentApplication::STATUS_APPROVED)
            ->where('updated_at', '>=', $since)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->each(function (StudentApplication $app) use ($items) {
                $items->push([
                    'type' => 'application_approved',
                    'id' => $app->id,
                    'label' => $app->application_number ?? 'Application approved',
                    'at' => optional($app->updated_at)?->toIso8601String(),
                    'route' => 'applications.show',
                    'route_params' => ['application' => $app->id],
                ]);
            });

        Admission::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [Admission::STATUS_OFFERED, Admission::STATUS_ACCEPTED])
            ->where(function ($q) use ($since) {
                $q->where('offered_at', '>=', $since)
                    ->orWhere('accepted_at', '>=', $since);
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->each(function (Admission $adm) use ($items) {
                $items->push([
                    'type' => $adm->status === Admission::STATUS_ACCEPTED
                        ? 'offer_accepted'
                        : 'offer_issued',
                    'id' => $adm->id,
                    'label' => $adm->admission_number ?? 'Admission',
                    'at' => optional(
                        $adm->accepted_at ?? $adm->offered_at ?? $adm->updated_at
                    )?->toIso8601String(),
                    'route' => 'admissions.lifecycle.show',
                    'route_params' => ['admission' => $adm->id],
                ]);
            });

        Enrollment::query()
            ->where('school_id', $schoolId)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->where('activated_at', '>=', $since)
            ->orderByDesc('activated_at')
            ->limit($limit)
            ->get()
            ->each(function (Enrollment $enr) use ($items) {
                $items->push([
                    'type' => 'enrollment_finalized',
                    'id' => $enr->id,
                    'label' => data_get($enr->meta, 'registration_number')
                        ?? data_get($enr->meta, 'admission_number')
                        ?? 'Enrollment finalized',
                    'at' => optional($enr->activated_at)?->toIso8601String(),
                    'route' => 'enrollments.show',
                    'route_params' => ['enrollment' => $enr->id],
                ]);
            });

        $sorted = $items->sortByDesc('at')->values()->take($limit);

        return [
            'items' => $sorted->all(),
            'total' => $sorted->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function applicationReport(School $school, array $filters = []): array
    {
        $q = StudentApplication::query()->where('school_id', $school->id);
        if (! empty($filters['academic_session_id'])) {
            $q->where('academic_session_id', $filters['academic_session_id']);
        }

        $byStatus = (clone $q)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $byClassLevel = (clone $q)
            ->selectRaw('class_level_id, count(*) as aggregate')
            ->groupBy('class_level_id')
            ->pluck('aggregate', 'class_level_id')
            ->all();

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'by_class_level' => $byClassLevel,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function admissionReport(School $school, array $filters = []): array
    {
        $q = Admission::query()->where('school_id', $school->id);
        if (! empty($filters['academic_session_id'])) {
            $q->where('academic_session_id', $filters['academic_session_id']);
        }

        $byStatus = (clone $q)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $issued = (int) ($byStatus[Admission::STATUS_OFFERED] ?? 0)
            + (int) ($byStatus[Admission::STATUS_ACCEPTED] ?? 0)
            + (int) ($byStatus[Admission::STATUS_DECLINED] ?? 0)
            + (int) ($byStatus[Admission::STATUS_EXPIRED] ?? 0)
            + (int) ($byStatus[Admission::STATUS_CANCELLED] ?? 0)
            + (int) ($byStatus[Admission::STATUS_PENDING] ?? 0);

        $accepted = (int) ($byStatus[Admission::STATUS_ACCEPTED] ?? 0);
        $declined = (int) ($byStatus[Admission::STATUS_DECLINED] ?? 0);
        $expired = (int) ($byStatus[Admission::STATUS_EXPIRED] ?? 0);

        $decided = $accepted + $declined + $expired;
        $acceptanceRate = $decided > 0 ? round($accepted / $decided, 4) : null;

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'issued' => $issued,
            'accepted' => $accepted,
            'declined' => $declined,
            'expired' => $expired,
            'acceptance_rate' => $acceptanceRate,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function enrollmentReport(School $school, array $filters = []): array
    {
        $q = Enrollment::query()->where('school_id', $school->id);
        if (! empty($filters['academic_session_id'])) {
            $q->where('academic_session_id', $filters['academic_session_id']);
        }

        $byStatus = (clone $q)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $finalized = (int) ($byStatus[Enrollment::STATUS_ACTIVE] ?? 0)
            + (int) ($byStatus[Enrollment::STATUS_COMPLETED] ?? 0);
        $incomplete = (int) ($byStatus[Enrollment::STATUS_DRAFT] ?? 0)
            + (int) ($byStatus[Enrollment::STATUS_IN_PROGRESS] ?? 0);

        $admissionOrigin = (clone $q)->whereNotNull('admission_id')->count();
        $direct = (clone $q)->whereNull('admission_id')->count();

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'finalized' => $finalized,
            'incomplete' => $incomplete,
            'admission_origin' => $admissionOrigin,
            'direct' => $direct,
        ];
    }

    /**
     * Lifecycle funnel using authoritative records only.
     *
     * @return array<string, int>
     */
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
