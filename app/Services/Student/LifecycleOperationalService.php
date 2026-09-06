<?php
namespace App\Services\Student;

use App\Models\Academic\AcademicSession;
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
     * Normalize date-only report filters to inclusive day bounds.
     * date_from / deadline_from → start of day; date_to / deadline_to → end of day.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeReportFilters(array $filters): array
    {
        foreach (['date_from', 'deadline_from'] as $key) {
            if (! empty($filters[$key]) && is_string($filters[$key])) {
                $filters[$key] = Carbon::parse($filters[$key])->startOfDay()->toDateTimeString();
            }
        }
        foreach (['date_to', 'deadline_to'] as $key) {
            if (! empty($filters[$key]) && is_string($filters[$key])) {
                $filters[$key] = Carbon::parse($filters[$key])->endOfDay()->toDateTimeString();
            }
        }

        return $filters;
    }

    /**
     * Actionable counts for dashboard cards.
     *
     * Session resolution is owned by AcademicCalendarService (or an explicit
     * $session passed by the caller for tests). This method never queries
     * academic_sessions and never treats a missing session as "all sessions".
     *
     * When $session is null, all session-dependent metrics are zero.
     *
     * @return array<string, int>
     */
    public function dashboardCounts(School $school, ?AcademicSession $session = null): array
    {
        $empty = [
            'applications_awaiting_review' => 0,
            'offers_awaiting_acceptance' => 0,
            'offers_expiring_soon' => 0,
            'accepted_awaiting_registration' => 0,
            'enrollments_in_progress' => 0,
            'ready_for_finalization' => 0,
            'awaiting_placement' => 0,
            'sections_near_capacity' => 0,
        ];

        // Lifecycle boundary: no session ⇒ no operational metrics (not historical aggregate).
        if ($session === null) {
            return $empty;
        }

        // Guard school isolation — never accept another school's session.
        if ((string) $session->school_id !== (string) $school->id) {
            return $empty;
        }

        $schoolId = $school->id;
        $sessionId = $session->id;

        $applicationsAwaitingReview = StudentApplication::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->whereIn('status', [
                StudentApplication::STATUS_SUBMITTED,
                StudentApplication::STATUS_UNDER_REVIEW,
                StudentApplication::STATUS_PENDING,
            ])
            ->count();

        $offersAwaitingAcceptance = Admission::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->whereIn('status', [Admission::STATUS_OFFERED, Admission::STATUS_PENDING])
            ->count();

        $offersExpiringSoon = Admission::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->whereIn('status', [Admission::STATUS_OFFERED, Admission::STATUS_PENDING])
            ->whereNotNull('acceptance_deadline')
            ->whereBetween('acceptance_deadline', [now(), now()->addDays(7)])
            ->count();

        $acceptedAwaitingRegistration = Admission::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('status', Admission::STATUS_ACCEPTED)
            ->whereDoesntHave('enrollment')
            ->count();

        $enrollmentsInProgress = Enrollment::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->whereIn('status', [Enrollment::STATUS_DRAFT, Enrollment::STATUS_IN_PROGRESS])
            ->count();

        $readyForFinalization = Enrollment::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->whereIn('status', [Enrollment::STATUS_DRAFT, Enrollment::STATUS_IN_PROGRESS])
            ->whereDoesntHave('requirementInstances', function ($q) {
                $q->where('status', \App\Models\Student\EnrollmentRequirementInstance::STATUS_PENDING)
                    ->whereHas('definition', fn ($d) => $d->where('is_required', true));
            })
            ->count();

        // Placement is session-specific: prior-session is_current placement does not satisfy this session.
        $awaitingPlacement = Enrollment::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->whereDoesntHave('student.sessionPlacements', function ($q) use ($sessionId) {
                $q->where('is_current', true)
                    ->where('academic_session_id', $sessionId);
            })
            ->count();

        $sectionsNearCapacity = ClassSection::query()
            ->where('school_id', $schoolId)
            ->where('capacity', '>', 0)
            ->whereRaw(
                "(capacity - (SELECT COUNT(*) FROM student_session_placements ssp WHERE ssp.class_section_id = class_sections.id AND ssp.is_current = 1 AND ssp.academic_session_id = ?)) <= GREATEST(2, FLOOR(capacity * 0.15))",
                [$sessionId]
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

    // NOTE: remainder of class restored from session-boundary artifact — full file follows in second write if truncated
}
