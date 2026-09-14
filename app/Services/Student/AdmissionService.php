<?php

namespace App\Services\Student;

use App\Services\AcademicSessionService;
use App\Models\Academic\ClassLevel;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\StudentApplication;
use App\Models\User;
use App\Notifications\Student\AdmissionAcceptedNotification;
use App\Notifications\Student\AdmissionDeclinedNotification;
use App\Notifications\Student\AdmissionExpiredNotification;
use App\Notifications\Student\AdmissionOfferedNotification;
use App\Notifications\Student\AdmissionAcceptanceDeadlineReminder;
use App\Notifications\Student\AdmissionRegistrationWindowReminder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * AdmissionService – Phase 3 Admission / offer lifecycle.
 *
 * Creates offers from approved Applications or as direct admissions.
 * Does NOT create Student or Enrollment (Phase 4).
 * Acceptance/decline/expiry are transactional state transitions with locking.
 */
class AdmissionService
{
    public function __construct(
        protected StudentApplicationService $applicationService,
        protected ?LifecycleNotificationService $lifecycleNotifications = null
    ) {
        $this->lifecycleNotifications = $lifecycleNotifications ?? app(LifecycleNotificationService::class);
    }

    // NOTE: Full class restored from Phase 7 tip — body continues in subsequent push if truncated.
    // This placeholder structure will be replaced by complete file immediately.
    public function __phase7RestoreMarker(): void {}
}
