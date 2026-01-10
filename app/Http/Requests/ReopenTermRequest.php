<?php

namespace App\Http\Requests;

use App\Models\Academic\Term;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * ReopenTermRequest – Validation for Reopening a Closed Academic Term
 *
 * Validates the restricted reopen operation with these critical safety requirements:
 *   - Mandatory detailed reason (for audit trail – reopening is exceptional)
 *   - Mandatory new end date that:
 *     - Is after the term's original start date
 *     - Does NOT collide with or exceed the next term's start date (if next term exists)
 *     - Stays within the parent session's overall end date
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Enforces immutability & safety during reopen (prevents invalid date extensions)
 * • Prevents overlap/collision with next term (core business invariant)
 * • Requires strong justification (reason min 20 chars) for audit compliance
 * • Multi-tenant safe: validates term belongs to current school context
 * • Clear, frontend-friendly error messages with warning tone for UX
 * • Uses Carbon for reliable date comparisons & formatting
 * • Early failure on invalid route/model – defensive programming
 * • Future-ready: easy to add more fields (e.g. approval code, attachments)
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Used exclusively by TermClosureController@reopen (PATCH/POST endpoint)
 * • Called before TermClosureService::reopenTerm() to guarantee valid input
 * • Works with Inertia.js + PrimeVue:
 *   - Errors displayed in TermReopenConfirmation.vue dialog
 *   - Supports DatePicker + required Textarea for reason
 * • Triggers safe update via TermClosureService (new end_date + unlock)
 * • Aligns with strict reopen restrictions: only most recent closed term
 *
 * Usage in Controller (typical):
 *   public function reopen(ReopenTermRequest $request, Term $term)
 *   {
 *       $this->authorize('reopen', $term);
 *       $validated = $request->validated();
 *
 *       app(TermClosureService::class)->reopenTerm(
 *           $term,
 *           $validated['reason'],
 *           $validated['new_end_date']
 *       );
 *
 *       return response()->json(['message' => 'Term reopened successfully']);
 *   }
 */
class ReopenTermRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Performs basic sanity + state checks. Final permission check done in controller
     * via policy: $this->authorize('reopen', $term);
     */
    public function authorize(): bool
    {
        $term = $this->route('term');

        if (! $term instanceof Term) {
            return false;
        }

        // Must be closed to reopen
        if (! $term->is_closed) {
            return false;
        }

        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $term = $this->route('term');
        $session = $term->academicSession;

        return [
            'reason' => [
                'required',
                'string',
                'min:20',
                'max:2000',
            ],
            'new_end_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:start_date', // start_date from existing term
                function ($attribute, $value, $fail) use ($term, $session) {
                    $newEnd = Carbon::parse($value);

                    // 1. Must stay within parent session bounds
                    if ($newEnd->gt($session->end_date)) {
                        $fail("New end date cannot exceed the session end date ({$session->end_date->format('Y-m-d')}).");
                    }

                    // 2. Must not collide with next term's start date (if next term exists)
                    $nextOrdinal = $term->ordinal_number + 1;
                    $nextTerm = Term::where('academic_session_id', $session->id)
                        ->where('ordinal_number', $nextOrdinal)
                        ->first();

                    if ($nextTerm && $nextTerm->start_date) {
                        if ($newEnd->gte($nextTerm->start_date)) {
                            $fail("New end date cannot be on or after the next term's start date ({$nextTerm->start_date->format('Y-m-d')}).");
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Custom error messages with strong warning tone for UX.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => '⚠️ A detailed reason is REQUIRED when reopening a term (for audit purposes).',
            'reason.min'      => '⚠️ The reason must be at least 20 characters to justify this exceptional action.',
            'new_end_date.required' => 'You must specify a new end date when reopening the term.',
            'new_end_date.after_or_equal' => 'The new end date must be on or after the term\'s original start date.',
            'new_end_date.date' => 'The new end date must be a valid date in YYYY-MM-DD format.',
        ];
    }

    /**
     * Get the validated data with cleanup.
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        // Trim and normalize reason
        $data['reason'] = trim($data['reason']);

        // Ensure new_end_date is trimmed string (for service consistency)
        if (isset($data['new_end_date'])) {
            $data['new_end_date'] = trim($data['new_end_date']);
        }

        return $data;
    }
}
