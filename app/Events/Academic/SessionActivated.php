<?php

namespace App\Events\Academic;

use App\Models\Academic\AcademicSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: SessionActivated
 *
 * Dispatched when an academic session is successfully activated.
 * Useful for triggering downstream actions (notifications, audit, integrations).
 */
class SessionActivated
{
    use Dispatchable, SerializesModels;

    public AcademicSession $session;

    public function __construct(AcademicSession $session)
    {
        $this->session = $session;
    }
}
