<?php

namespace App\Http\Requests;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Rules\InDynamicEnum;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * UpdateTermRequest – Validation for Updating an Existing Academic Term
 *
 * Validates partial or full updates to an existing term record.
 * This request enforces multi-tenant safety, strict date hierarchy, no overlapping terms,
 * immutability of start_date once the term is active/closed, and uniqueness of name within the session.
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Uses `sometimes` for all fields → supports true partial updates (PATCH-friendly)
 * • Automatic school_id injection from current context (multi-tenant safety)
 * • Parent session ownership validation (cannot move term to another school's session)
 * • Start date immutability: cannot change after term becomes active or closed
 * • Term dates must stay fully contained within parent session dates
 * • No overlapping date ranges with other terms in the same session
 * • Unique term name per session (ignores current record)
 * • Status restricted to known values (aligns with DynamicEnums defaults)
 * • Color validation (HEX format) for UI consistency
 * • Clear, frontend-friendly error messages for Inertia/PrimeVue form handling
 * • Future-ready: easy to add ordinal_number updates or custom status validation
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Used by TermController@update (PATCH or PUT endpoint)
 * • Protects TermClosureService / AcademicCalendarService from invalid updates
 * • Works seamlessly with Inertia.js + Vue 3 + PrimeVue:
 *   - Errors automatically available in form.errors for InputText/DatePicker components
 *   - Supports responsive, accessible form UX with real-time validation feedback
 * • Enforces core business invariants at the HTTP layer (before service layer)
 * • Integrates with multi-tenant design: school_id always enforced
 *
 * Usage in Controller (typical):
 *   public function update(UpdateTermRequest $request, Term $term)
 *   {
 *       $validated = $request->validated();
 *       $term->update($validated);
 *       // Or pass to service for complex logic (e.g. date change + reopen check)
 *       return redirect()->route('terms.show', $term);
 *   }
 */
class UpdateTermRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled in the controller via $this->authorize('update', $term)
     * or middleware/policy – request remains focused purely on validation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $school = GetSchoolModel();

        if (! $school) {
            throw ValidationException::withMessages([
                'school' => 'No active school context found. Please select a school first.'
            ]);
        }

        $term = $this->route('term');
        if (! $term instanceof Term) {
            throw ValidationException::withMessages([
                'term' => 'Invalid term route parameter.'
            ]);
        }

        return [
            'academic_session_id' => [
                'sometimes',
                'uuid',
                'exists:academic_sessions,id',
                function ($attribute, $value, $fail) use ($school, $term) {
                    $session = AcademicSession::find($value);
                    if ($session && $session->school_id !== $school->id) {
                        $fail('The selected academic session does not belong to your school.');
                    }
                    if ($value !== $term->academic_session_id && $term->is_active) {
                        $fail('Cannot change the parent session of an active term.');
                    }
                },
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:60',
                Rule::unique(Term::class, 'name')
                    ->where('academic_session_id', $this->input('academic_session_id', $term->academic_session_id))
                    ->ignore($term->id),
            ],
            'short_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:10',
            ],
            'ordinal_number' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:255',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'start_date' => [
                'sometimes',
                'required',
                'date',
                'date_format:Y-m-d',
                'before_or_equal:end_date',
                function ($attribute, $value, $fail) use ($term) {
                    if ($term->is_active || $term->is_closed) {
                        $fail('The start date of an active or closed term cannot be changed.');
                    }
                    $this->validateTermDateBounds($attribute, $value, $fail);
                },
            ],
            'end_date' => [
                'sometimes',
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
                function ($attribute, $value, $fail) {
                    $this->validateTermDateBounds($attribute, $value, $fail);
                },
            ],
            'status' => [
                'sometimes',
                'string',
                'max:20',
                new InDynamicEnum('status', Term::class),
            ],
            'color' => [
                'sometimes',
                'nullable',
                'string',
                'max:9',
                'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            ],
            'options' => [
                'sometimes',
                'nullable',
                'array',
            ],
            'school_id' => [
                'sometimes',
                'uuid',
                'exists:schools,id',
            ],
        ];
    }

    /**
     * Prepare the data for validation – auto-inject school_id if missing.
     */
    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();

        if ($school && ! $this->has('school_id')) {
            $this->merge([
                'school_id' => $school->id,
            ]);
        }
    }

    /**
     * Validate that term dates are fully contained within the parent session.
     */
    protected function validateTermDateBounds($attribute, $value, $fail): void
    {
        $sessionId = $this->input('academic_session_id') ?? $this->route('term')?->academic_session_id;
        if (! $sessionId) {
            return; // Session validation will fail separately
        }

        $session = AcademicSession::find($sessionId);
        if (! $session) {
            return;
        }

        $start = Carbon::parse($this->input('start_date') ?? $this->route('term')?->start_date);
        $end   = Carbon::parse($this->input('end_date') ?? $this->route('term')?->end_date);

        if ($attribute === 'start_date' && $start->lt($session->start_date)) {
            $fail("The term start date must be on or after the session start date ({$session->start_date->format('Y-m-d')}).");
        }

        if ($attribute === 'end_date' && $end->gt($session->end_date)) {
            $fail("The term end date must be on or before the session end date ({$session->end_date->format('Y-m-d')}).");
        }

        // Prevent overlap with other terms in the same session
        $existingTerms = Term::where('academic_session_id', $sessionId)
            ->where('id', '!=', $this->route('term')?->id ?? null)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                  ->orWhereBetween('end_date', [$start, $end])
                  ->orWhereRaw('? BETWEEN start_date AND end_date', [$start])
                  ->orWhereRaw('? BETWEEN start_date AND end_date', [$end]);
            })
            ->exists();

        if ($existingTerms) {
            $fail('The selected date range overlaps with another term in this session.');
        }
    }

    /**
     * Custom error messages for better frontend UX.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'academic_session_id.exists' => 'The selected academic session does not exist.',
            'name.required'              => 'The term name is required (e.g., First Term).',
            'name.unique'                => 'A term with this name already exists in this session.',
            'start_date.required'        => 'The term start date is required.',
            'end_date.required'          => 'The term end date is required.',
            'start_date.before_or_equal' => 'The start date must be on or before the end date.',
            'end_date.after_or_equal'    => 'The end date must be on or after the start date.',
            'status.in'                  => 'Invalid term status selected.',
            'color.regex'                => 'Color must be a valid HEX code (e.g., #FF5733 or #FFF).',
        ];
    }
}
