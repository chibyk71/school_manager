<?php

namespace App\Http\Requests;

use App\Models\Academic\Term;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * CloseTermRequest – Validation & Authorization Preparation for Closing an Academic Term
 *
 * This lightweight request validates that a term can be closed and prepares any optional data.
 * It is intentionally minimal since closure is a state-changing action with very little input.
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Ensures the term exists and belongs to the current school (multi-tenant safety)
 * • Optional 'reason' field for audit trail (why was this term closed early/late?)
 * • Simple structure – no heavy validation needed beyond existence & state
 * • Clear, user-friendly error messages for Inertia frontend
 * • Authorization delegated to TermPolicy ('close' permission) – keeps request clean
 * • Prepares for future extensions: e.g. require confirmation code, attachments
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Used exclusively by TermClosureController@close (PATCH or POST endpoint)
 * • Called before TermClosureService::closeTerm() to ensure valid input
 * • Works with Inertia.js + PrimeVue:
 *   - Displays errors in confirmation dialog (e.g. TermCloseDialog.vue)
 *   - Supports optional reason textarea in UI
 * • Triggers downstream effects via TermClosureService (locking, notifications, events)
 * • Aligns with restricted reopen logic: closure is irreversible without special reopen
 *
 * Usage in Controller (typical):
 *   public function close(CloseTermRequest $request, Term $term)
 *   {
 *       $this->authorize('close', $term);
 *       app(TermClosureService::class)->closeTerm($term);
 *       return response()->json(['message' => 'Term closed successfully']);
 *   }
 *
 * Recommended Frontend Pattern (TermCloseDialog.vue):
 *   - Show confirmation dialog with optional textarea for reason
 *   - Submit PATCH to /terms/{id}/close with { reason: string|null }
 *   - Display server validation errors if any
 */
class CloseTermRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Final authorization check is performed in controller via policy:
     *   $this->authorize('close', $term);
     *
     * Here we only do a basic sanity check to avoid unnecessary processing.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Very minimal – closure is primarily a state action.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $term = $this->route('term');

        if (! $term instanceof Term) {
            throw ValidationException::withMessages([
                'term' => 'Invalid term identifier.'
            ]);
        }

        return [
            'reason' => [
                'nullable',
                'string',
                'max:1000',
                'min:10', // Encourage meaningful reason for audit
            ],
        ];
    }

    /**
     * Custom error messages for better frontend UX.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.min' => 'If providing a reason, please enter at least 10 characters for audit purposes.',
            'reason.max' => 'The reason cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get the validated data with defaults.
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        // Ensure reason is null if empty string (clean data)
        if (isset($data['reason']) && trim($data['reason']) === '') {
            $data['reason'] = null;
        }

        return $data;
    }
}
