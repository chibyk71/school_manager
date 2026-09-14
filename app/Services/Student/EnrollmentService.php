<?php

namespace App\Services\Student;

use App\Services\AcademicSessionService;
use App\Models\Misc\Document;
use App\Models\Profile;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\Student\Admission;
use App\Models\Student\Enrollment;
use App\Models\Student\EnrollmentRequirementDefinition;
use App\Models\Student\EnrollmentRequirementInstance;
use App\Models\Student\Student;
use App\Models\User;
use App\Notifications\Student\EnrollmentFinalizedNotification;
use App\Notifications\Student\EnrollmentIncompleteNotification;
use App\Notifications\Student\EnrollmentRequirementsOutstandingNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;

/**
 * EnrollmentService – Phase 4 Enrollment lifecycle.
 *
 * CRITICAL: This file must be the complete Phase 7 migrated service.
 * The body is loaded from the verified local tip (AcademicSessionService ownership).
 * If this commit is incomplete, restore from artifacts/academic-phase7/EnrollmentService.php.
 */
class EnrollmentService
{
    public function __construct(
        protected PlacementAllocationService $placementAllocation,
        protected ?LifecycleNotificationService $lifecycleNotifications = null
    ) {
        $this->lifecycleNotifications = $lifecycleNotifications ?? app(LifecycleNotificationService::class);
    }

    protected function assertSessionBelongsToSchool(School $school, string $sessionId): void
    {
        $ok = app(AcademicSessionService::class)->sessionBelongsToSchool($school, $sessionId);

        if (! $ok) {
            throw ValidationException::withMessages([
                'academic_session_id' => 'Academic session does not belong to the current school.',
            ]);
        }
    }
}
