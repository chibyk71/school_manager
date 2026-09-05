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

    /**
     * Unified Needs Attention feed.
     *
     * @return array{items: list<array>, total: int}
     */
    public function needsAttention(School $school, int $limit = 50): array
    {
        $schoolId = $school->id;
        $items = collect();

        $appsBase = StudentApplication::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [
                StudentApplication::STATUS_SUBMITTED,
                StudentApplication::STATUS_UNDER_REVIEW,
                StudentApplication::STATUS_PENDING,
            ]);
        $offersBase = Admission::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [Admission::STATUS_OFFERED, Admission::STATUS_PENDING]);
        $acceptedBase = Admission::query()
            ->where('school_id', $schoolId)
            ->where('status', Admission::STATUS_ACCEPTED)
            ->whereDoesntHave('enrollment');
        $enrBase = Enrollment::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [Enrollment::STATUS_DRAFT, Enrollment::STATUS_IN_PROGRESS]);

        $totalMatching = (clone $appsBase)->count()
            + (clone $offersBase)->count()
            + (clone $acceptedBase)->count()
            + (clone $enrBase)->count();

        (clone $appsBase)
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
            'returned_count' => $sorted->count(),
            'total' => $totalMatching,
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

        $totalMatching = $items->count();
        $sorted = $items->sortBy('deadline')->values()->take($limit);

        return [
            'items' => $sorted->all(),
            'returned_count' => $sorted->count(),
            'total' => $totalMatching,
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

        $totalMatching = $items->count();
        $sorted = $items->sortByDesc('at')->values()->take($limit);

        return [
            'items' => $sorted->all(),
            'returned_count' => $sorted->count(),
            'total' => $totalMatching,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */

    /**
     * Filtered application query for reports/exports (school-scoped).
     *
     * @param  array<string, mixed>  $filters
     */
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

    /**
     * @param  array<string, mixed>  $filters
     */
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

    /**
     * @param  array<string, mixed>  $filters
     */
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

        $bySource = (clone $q)
            ->selectRaw('source, count(*) as aggregate')
            ->groupBy('source')
            ->pluck('aggregate', 'source')
            ->all();

        $approved = (int) ($byStatus[StudentApplication::STATUS_APPROVED] ?? 0);
        $total = array_sum($byStatus);
        $conversion = $total > 0 ? round($approved / $total, 4) : null;

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'by_class_level' => $byClassLevel,
            'by_source' => $bySource,
            'approved' => $approved,
            'approval_rate' => $conversion,
            'filters_applied' => array_keys(array_filter($filters)),
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

        $byStatus = (clone $q)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $issued = array_sum($byStatus);
        $accepted = (int) ($byStatus[Admission::STATUS_ACCEPTED] ?? 0);
        $declined = (int) ($byStatus[Admission::STATUS_DECLINED] ?? 0);
        $expired = (int) ($byStatus[Admission::STATUS_EXPIRED] ?? 0);
        $cancelled = (int) ($byStatus[Admission::STATUS_CANCELLED] ?? 0);

        // Explicit business rule: acceptance rate among terminal offer outcomes
        // (accepted + declined + expired + cancelled). Active offers excluded.
        $terminal = $accepted + $declined + $expired + $cancelled;
        $acceptanceRate = $terminal > 0 ? round($accepted / $terminal, 4) : null;

        $fromApplication = (clone $q)->whereNotNull('application_id')->count();
        $direct = (clone $q)->whereNull('application_id')->count();

        return [
            'total' => $issued,
            'by_status' => $byStatus,
            'issued' => $issued,
            'accepted' => $accepted,
            'declined' => $declined,
            'expired' => $expired,
            'cancelled' => $cancelled,
            'terminal_outcomes' => $terminal,
            'acceptance_rate' => $acceptanceRate,
            'acceptance_rate_definition' => 'accepted / (accepted + declined + expired + cancelled); active offers excluded',
            'from_application' => $fromApplication,
            'direct' => $direct,
            'filters_applied' => array_keys(array_filter($filters)),
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
            'filters_applied' => array_keys(array_filter($filters, fn ($v) => $v !== null && $v !== '')),
        ];
    }

    /**
     * Lifecycle funnel using authoritative records only.
     *
     * @return array<string, int>
     */
    /**
     * Placement / capacity snapshot (Phase 5/6).
     *
     * @return array<string, mixed>
     */
    /**
     * Placement rows query for exports (session-scoped current placements).
     *
     * @param  array<string, mixed>  $filters
     */
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
        if (! empty($filters['status'])) {
            // placement itself is current; filter by enrollment status when provided
            $q->whereHas('enrollment', fn ($e) => $e->whereIn('status', (array) $filters['status']));
        }

        return $q->orderByDesc('enrolled_at');
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

        // Per-section utilization sample (capped for payload size)
        $utilization = ClassSection::query()
            ->where('school_id', $schoolId)
            ->whereIn('id', $sectionIds)
            ->where('capacity', '>', 0)
            ->orderBy('name')
            ->limit(50)
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
