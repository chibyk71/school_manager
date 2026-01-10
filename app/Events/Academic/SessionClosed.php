<?php

namespace App\Events\Academic;

use App\Models\Academic\AcademicSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: SessionClosed
 *
 * Dispatched when an academic session is successfully closed.
 * Primary integration point for:
 * - Promotion & repetition engine
 * - Annual report generation
 * - Transcript finalization
 * - Analytics & historical archiving
 */
class SessionClosed
{
    use Dispatchable, SerializesModels;

    public AcademicSession $session;

    public function __construct(AcademicSession $session)
    {
        $this->session = $session;
    }
}
