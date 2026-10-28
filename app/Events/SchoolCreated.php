<?php

namespace App\Events;

use App\Models\School;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SchoolCreated Event
 *
 * Dispatched after a new School has been committed (DB::afterCommit from SchoolService).
 * Listeners may be queued; SerializesModels re-fetches the School after the create
 * transaction has committed, so ModelNotFoundException should not occur on the happy path.
 */
class SchoolCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var School
     */
    public $school;

    /**
     * @var string|null
     */
    public $creatorId;

    public function __construct(School $school, ?string $creatorId = null)
    {
        $this->school = $school;
        $this->creatorId = $creatorId;
    }
}
