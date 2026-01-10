<?php

namespace App\Http\Requests;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Rules\InDynamicEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * StoreTermRequest – Validation for Creating a New Academic Term
 *
 * Validates input when creating a new term inside an existing academic session.
 * Ensures multi-tenant safety, strict date hierarchy, no overlapping terms,
 * and uniqueness of term name within the session.
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Automatic school_id injection from current school context
 * • Strict parent session existence & ownership validation
 * • Term dates must be fully contained within parent session dates
 * • No overlapping dates with other terms in the same session
 * • Unique term name per session (prevents duplicates like two "First Term")
 * • Status restricted to known values (aligns with DynamicEnums defaults)
 * • Color validation (HEX format) for UI consistency
 * • Clear, frontend-friendly error messages for Inertia/PrimeVue
 * • Future-ready: easy to add ordinal_number auto-generation or custom status
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Used by TermController@store
 * • Protects AcademicCalendarService from invalid data
 * • Works seamlessly with Inertia.js + Vue 3 + PrimeVue:
 *   - Errors automatically available in form.errors for InputText/DatePicker
 *   - Supports responsive, accessible form UX
 * • Enforces core business invariants at the HTTP layer (before service layer)
 * • Integrates with multi-tenant design: school_id always enforced
 *
 * Usage in Controller (typical):
 *   public function store(StoreTermRequest $request)
 *   {
 *       $validated = $request->validated();
 *       // Optional: auto-set ordinal_number based on existing terms
 *       $validated['ordinal_number'] = Term::where('academic_session_id', $validated['academic_session_id'])
 *           ->max('ordinal_number') + 1 ?? 1;
 *
 *       $term = Term::create($validated);
 *       return redirect()->route('academic-sessions.show', $term->academic_session_id);
 *   }
 */
class StoreTermRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled in the controller via $this->authorize('create', Term::class)
     * or middleware/policy – we keep the request focused purely on validation.
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

        return [
            'academic_session_id' => [
                'required',
                'uuid',
                'exists:academic_sessions,id',
                function ($attribute, $value, $fail) use ($school) {
                    $session = AcademicSession::find($value);
                    if ($session && $session->school_id !== $school->id) {
                        $fail('The selected academic session does not belong to your school.');
                    }
                },
            ],
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique(Term::class, 'name')
                    ->where('academic_session_id', $this->input('academic_session_id')),
            ],
            'short_name' => [
                'nullable',
                'string',
                'max:10',
            ],
            'ordinal_number' => [
                'nullable',
                'integer',
                'min:1',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'start_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'before_or_equal:end_date',
                function ($attribute, $value, $fail) {
                    $this->validateTermDateBounds($attribute, $value, $fail);
                },
            ],
            'end_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
                function ($attribute, $value, $fail) {
                    $this->validateTermDateBounds($attribute, $value, $fail);
                },
            ],
            'status' => [
                'required',
                'string',
                'max:20',
                new InDynamicEnum("status", Term::class),
            ],
            'color' => [
                'nullable',
                'string',
                'max:9',
                'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            ],
            'options' => [
                'nullable',
                'array',
            ],
            'school_id' => [
                'required',
                'uuid',
                'exists:schools,id',
            ],
        ];
    }

    /**
     * Prepare the data for validation – auto-inject school_id.
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
        $sessionId = $this->input('academic_session_id');
        if (! $sessionId) {
            return; // Session validation will fail separately
        }

        $session = AcademicSession::find($sessionId);
        if (! $session) {
            return;
        }

        $start = Carbon::parse($this->input('start_date'));
        $end   = Carbon::parse($this->input('end_date'));

        if ($attribute === 'start_date' && $start->lt($session->start_date)) {
            $fail("The term start date must be on or after the session start date ({$session->start_date->format('Y-m-d')}).");
        }

        if ($attribute === 'end_date' && $end->gt($session->end_date)) {
            $fail("The term end date must be on or before the session end date ({$session->end_date->format('Y-m-d')}).");
        }

        // Optional: Prevent overlap with other terms in the same session
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
            'academic_session_id.required' => 'Please select a valid academic session.',
            'academic_session_id.exists'   => 'The selected academic session does not exist.',
            'name.required'                => 'The term name is required (e.g., First Term).',
            'name.unique'                  => 'A term with this name already exists in this session.',
            'start_date.required'          => 'The term start date is required.',
            'end_date.required'            => 'The term end date is required.',
            'start_date.before_or_equal'   => 'The start date must be on or before the end date.',
            'end_date.after_or_equal'      => 'The end date must be on or after the start date.',
            'status.in'                    => 'Invalid term status selected.',
            'color.regex'                  => 'Color must be a valid HEX code (e.g., #FF5733 or #FFF).',
        ];
    }
}
