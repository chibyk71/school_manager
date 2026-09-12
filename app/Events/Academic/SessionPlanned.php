<?php

namespace App\Events\Academic;

use App\Models\Academic\AcademicSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: SessionPlanned
 *
 * Dispatched when an academic session successfully completes this lifecycle transition.
 * Reuses the existing academic event architecture (no parallel bus).
 */
class SessionPlanned
{
    use Dispatchable, SerializesModels;

    public AcademicSession $session;

    public function __construct(AcademicSession $session)
    {
        $this->session = $session;
    }
}
