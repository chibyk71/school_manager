<?php

namespace App\Traits;

use App\Models\Misc\AttendanceLedger;
use App\Models\Misc\AttendanceSession;
use App\Notifications\AttendanceLedgerAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Trait HasAttendance
 *
 * Provides methods to manage attendance records for models that support attendance (e.g., Student, Staff).
 * Integrates with AttendanceLedger and AttendanceSession for a multi-tenant SaaS environment.
 *
 * @package App\Traits
 */
trait HasAttendance
{
    /**
     * Define a polymorphic relationship for attendance ledgers.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function attendanceLedgers()
    {
        return $this->morphMany(AttendanceLedger::class, 'attendable');
    }

    /**
     * Mark attendance for the model with the specified status.
     *
     * @param AttendanceSession $session The attendance session to associate with the record.
     * @param string $status The attendance status (present, absent, late, leave, holiday).
     * @param string|null $remarks Optional remarks for the attendance record.
     * @return AttendanceLedger
     * @throws \Illuminate\Auth\Access\AuthorizationException If the user lacks permission.
     * @throws \Illuminate\Validation\ValidationException If the session is invalid.
     * @throws \Exception If creation fails.
     */
    protected function markAttendance(AttendanceSession $session, string $status, ?string $remarks = null): AttendanceLedger
    {
        // Authorize the action
        authorize('create', AttendanceLedger::class);

        try {
            // Validate the session belongs to the same school
            $school = GetSchoolModel();
            if (!$school || $session->school_id !== $school->id) {
                throw ValidationException::withMessages([
                    'attendance_session_id' => 'The selected attendance session is invalid or does not belong to this school.',
                ]);
            }

            // Validate the model belongs to the same school
            if (method_exists($this, 'school_id') && $this->school_id !== $school->id) {
                throw ValidationException::withMessages([
                    'attendable_id' => 'The selected entity does not belong to this school.',
                ]);
            }

            // Create the attendance ledger
            $attendanceLedger = $this->attendanceLedgers()->create([
                'school_id' => $school->id,
                'attendance_session_id' => $session->id,
                'status' => $status,
                'remarks' => $remarks,
            ]);

            // Log the activity
            activity()
                ->performedOn($attendanceLedger)
                ->causedBy(Auth::user())
                ->withProperties(['status' => $status, 'remarks' => $remarks])
                ->log("Marked {$status} attendance for " . class_basename($this) . " in session {$session->name}");

            // Notify relevant staff
            $users = \App\Models\Employee\Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new AttendanceLedgerAction($attendanceLedger, 'created'));

            return $attendanceLedger;
        } catch (\Exception $e) {
            Log::error("Failed to mark {$status} attendance for " . class_basename($this) . ": " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark attendance for multiple entities in a collection.
     *
     * @param Collection $entities The collection of models to mark attendance for.
     * @param AttendanceSession $session The attendance session.
     * @param string $status The attendance status (present, absent, late, leave, holiday).
     * @param string|null $remarks Optional remarks for the attendance records.
     * @return array Array of created AttendanceLedger instances.
     * @throws \Illuminate\Auth\Access\AuthorizationException If the user lacks permission.
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If creation fails for any entity.
     */
    public static function markBulkAttendance(Collection $entities, AttendanceSession $session, string $status, ?string $remarks = null): array
    {
        // Authorize the action
        authorize('create', AttendanceLedger::class);

        try {
            $school = GetSchoolModel();
            if (!$school || $session->school_id !== $school->id) {
                throw ValidationException::withMessages([
                    'attendance_session_id' => 'The selected attendance session is invalid or does not belong to this school.',
                ]);
            }

            $attendanceLedgers = [];
            foreach ($entities as $entity) {
                // Validate entity belongs to the same school
                if (method_exists($entity, 'school_id') && $entity->school_id !== $school->id) {
                    throw ValidationException::withMessages([
                        'attendable_id' => "The entity {$entity->id} does not belong to this school.",
                    ]);
                }

                // Mark attendance for the entity
                $attendanceLedgers[] = $entity->markAttendance($session, $status, $remarks);
            }

            return $attendanceLedgers;
        } catch (\Exception $e) {
            Log::error("Failed to mark bulk {$status} attendance: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark attendance as present.
     *
     * @param AttendanceSession $session The attendance session.
     * @param string|null $remarks Optional remarks.
     * @return AttendanceLedger
     */
    public function markPresent(AttendanceSession $session, ?string $remarks = null): AttendanceLedger
    {
        return $this->markAttendance($session, 'present', $remarks);
    }

    /**
     * Mark attendance as absent.
     *
     * @param AttendanceSession $session The attendance session.
     * @param string|null $remarks Optional remarks.
     * @return AttendanceLedger
     */
    public function markAbsent(AttendanceSession $session, ?string $remarks = null): AttendanceLedger
    {
        return $this->markAttendance($session, 'absent', $remarks);
    }

    /**
     * Mark attendance as late.
     *
     * @param AttendanceSession $session The attendance session.
     * @param string|null $remarks Optional remarks.
     * @return AttendanceLedger
     */
    public function markLate(AttendanceSession $session, ?string $remarks = null): AttendanceLedger
    {
        return $this->markAttendance($session, 'late', $remarks);
    }

    /**
     * Mark attendance as on leave.
     *
     * @param AttendanceSession $session The attendance session.
     * @param string|null $remarks Optional remarks.
     * @return AttendanceLedger
     */
    public function markLeave(AttendanceSession $session, ?string $remarks = null): AttendanceLedger
    {
        return $this->markAttendance($session, 'leave', $remarks);
    }

    /**
     * Mark attendance as holiday.
     *
     * @param AttendanceSession $session The attendance session.
     * @param string|null $remarks Optional remarks.
     * @return AttendanceLedger
     */
    public function markHoliday(AttendanceSession $session, ?string $remarks = null): AttendanceLedger
    {
        return $this->markAttendance($session, 'holiday', $remarks);
    }
}
