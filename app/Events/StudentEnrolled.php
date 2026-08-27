<?php

namespace App\Events;


use App\Models\Academic\Student;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * StudentEnrolled Event
 *
 * This event is fired every time a new student enrollment is successfully created
 * via the StudentEnrollmentService.
 *
 * Features / Problems Solved:
 * - Decouples enrollment creation from downstream actions (notifications, logging, integrations)
 * - Allows multiple listeners to react independently (welcome email, parent SMS, audit log, etc.)
 * - Passes critical context: the new Student model and whether a login account was created
 * - Queueable & serializable → safe for async processing (e.g., email sending)
 * - Lightweight: no heavy data attached (just Student ID or model)
 * - Production-ready: uses Laravel's standard event pattern
 *
 * Fits into the User Management & Student Module:
 * - Triggered exclusively by StudentEnrollmentService::enroll()
 * - Primary use cases:
 *   - Send welcome email to student (if login created) or parents
 *   - Notify guardians (via primary guardian)
 *   - Create audit log entry
 *   - Trigger integrations (e.g., push to external SIS, SMS gateway)
 *   - Update dashboard counters or stats
 * - Listeners can be registered in EventServiceProvider
 * - No direct UI impact — purely backend event bus
 *
 * Usage Example (in StudentEnrollmentService):
 *   event(new StudentEnrolled($student, $createLogin));
 *
 * Listener Registration (in EventServiceProvider.php):
 *   protected $listen = [
 *       StudentEnrolled::class => [
 *           SendStudentWelcomeEmail::class,
 *           NotifyGuardians::class,
 *           LogStudentEnrollment::class,
 *       ],
 *   ];
 */

class StudentEnrolled
{
    use Dispatchable, SerializesModels;

    /**
     * The newly enrolled student
     *
     * @var Student
     */
    public $student;

    /**
     * Whether a login account was created for this student
     *
     * @var bool
     */
    public $loginCreated;

    /**
     * Create a new event instance.
     */
    public function __construct(Student $student, bool $loginCreated)
    {
        $this->student = $student;
        $this->loginCreated = $loginCreated;
    }
}
