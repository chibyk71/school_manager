<?php

namespace App\Contracts\BulkActions;

/**
 * Marker for handlers that genuinely support per-record partial execution.
 *
 * A single SQL delete()/update() over a set is NOT partial merely because the
 * outer service skipped DB::transaction(). Implement this interface only when
 * the handler can succeed/fail/skip individual records and report them in
 * BulkActionResult (succeeded / failed / skipped / errors).
 */
interface PartialBulkAction
{
    // Marker interface — no methods required.
}
