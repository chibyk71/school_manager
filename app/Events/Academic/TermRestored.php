<?php

namespace App\Events\Academic;

use App\Models\Academic\Term;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a Term is successfully restored and committed.
 * Restore is record management, not a lifecycle transition.
 */
class TermRestored implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Term $term)
    {
    }
}
