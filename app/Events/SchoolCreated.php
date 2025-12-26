<?php

namespace App\Events;

use App\Models\School;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SchoolCreated Event
 *
 * Purpose & Context:
 * ------------------
 * This event is dispatched immediately after a new School (tenant) has been successfully created
 * in the multi-tenant school management SaaS application.
 *
 * It serves as the central, decoupled trigger for all post-creation asynchronous tasks,
 * allowing the HTTP request (onboarding) to return quickly while background processes handle
 * heavier or non-critical setup work.
 *
 * Key Responsibilities:
 * ---------------------
 * - Carries the newly created School instance
 * - Optionally carries the ID of the user who initiated the creation (may be null for public onboarding)
 * - Enables listeners to perform actions such as:
 *   • Initializing default school settings (SMS, GDPR, application config, etc.)
 *   • Creating default academic sessions, class levels, or fee structures
 *   • Sending welcome emails or notifications to the school admin
 *   • Triggering billing/subscription provisioning
 *   • Logging audit events or analytics
 *
 * Design Decisions:
 * -----------------
 * - Uses Dispatchable, InteractsWithSockets, and SerializesModels traits for full Laravel event capabilities
 * - Does NOT implement ShouldBroadcast — real-time broadcasting is not required for post-creation setup
 *   (keeps it lightweight and suitable for queued listeners)
 * - Public properties for easy access in listeners (no need for accessors)
 * - Creator ID is nullable to support public onboarding flows where no authenticated user exists
 *
 * Queueing:
 * ---------
 * Listeners attached to this event should implement ShouldQueue where appropriate
 * to ensure fast onboarding response times.
 *
 * Usage Example:
 * --------------
 * Dispatched in SchoolService::createSchool():
 *   event(new SchoolCreated($school, auth()->id()));
 */
class SchoolCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The newly created school instance.
     *
     * @var School
     */
    public $school;

    /**
     * The ID of the user who triggered the school creation.
     * May be null for public/unauthenticated onboarding flows.
     *
     * @var int|null
     */
    public $creatorId;

    /**
     * Create a new event instance.
     *
     * @param  School  $school
     * @param  int|null  $creatorId
     * @return void
     */
    public function __construct(School $school, ?int $creatorId = null)
    {
        $this->school = $school;
        $this->creatorId = $creatorId;
    }
}
