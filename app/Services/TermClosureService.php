<?php

namespace App\Services;

use App\Models\Academic\Term;
use Illuminate\Validation\ValidationException;

/**
 * @deprecated Phase 3: Term lifecycle is owned by TermLifecycleService.
 * This class remains only as a removed-API guard so accidental callers fail loudly.
 */
class TermClosureService
{
    public function closeTerm(Term $term, ?string $reason = null): void
    {
        app(\App\Services\Academic\TermLifecycleService::class)->close($term);
    }

    public function reopenTerm(Term $term, string $reason = '', string $newEndDate = ''): void
    {
        throw ValidationException::withMessages([
            'state' => 'Term reopen is not supported. CLOSED terms cannot become ACTIVE.',
        ]);
    }
}
