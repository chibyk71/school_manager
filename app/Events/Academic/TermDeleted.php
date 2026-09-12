<?php

namespace App\Events\Academic;

use App\Models\Academic\Term;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a Term is successfully soft-deleted and committed.
 */
class TermDeleted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Term $term)
    {
    }
}
