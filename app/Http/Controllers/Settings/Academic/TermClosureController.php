<?php

namespace App\Http\Controllers\Settings\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseTermRequest;
use App\Models\Academic\Term;
use App\Services\Academic\TermLifecycleService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Term lifecycle transitions: close (activate via TermController).
 * Phase 3: reopen is removed.
 */
class TermClosureController extends Controller
{
    public function __construct(protected TermLifecycleService $lifecycle)
    {
    }

    public function close(CloseTermRequest $request, Term $term)
    {
        Gate::authorize('close', $term);

        try {
            $this->lifecycle->close($term);

            return redirect()
                ->route('terms.index', ['academicSession' => $term->academic_session_id])
                ->with('success', "Term '{$term->display_name}' has been closed successfully.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to close term', [
                'error' => $e->getMessage(),
                'term_id' => $term->id,
                'user_id' => auth()->id() ?? 'system',
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to close term: ' . ($e->getMessage() ?? 'An unexpected error occurred.'));
        }
    }
}
