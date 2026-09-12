<?php

namespace App\Events\Academic;

use App\Models\Academic\AcademicSession;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: SessionPaused
 *
 * Dispatched when an academic session successfully transitions ACTIVE → PAUSED.
 *
 * Implements ShouldDispatchAfterCommit so listeners only observe transitions
 * that have actually been committed (Phase 2 lifecycle integrity).
 */
class SessionPaused implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public AcademicSession $session;

    public function __construct(AcademicSession $session)
    {
        $this->session = $session;
    }
}
