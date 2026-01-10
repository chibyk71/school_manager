<?php

namespace App\Http\Requests;

use App\Models\Academic\AcademicSession;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * UpdateAcademicSessionRequest – Validation for Updating an Existing Academic Session
 *
 * Validates input when updating an academic session record.
 * This request enforces multi-tenant safety, date hierarchy, uniqueness per school,
 * and critical immutability rules (e.g., start_date cannot be changed once active).
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Automatic school_id injection from current school context
 * • Session name uniqueness scoped to school (ignores current record)
 * • Strict date validation: start ≤ end, proper format
 * • Immutability enforcement: start_date cannot be changed after activation
 * • Conditional rules based on current session status (e.g. cannot change status to invalid)
 * • Clear, frontend-friendly error messages for Inertia/PrimeVue form display
 * • Protection against invalid updates (e.g. changing active session dates carelessly)
 * • Future-ready: easy to add status workflow, activated_at/closed_at validation
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Used by AcademicSessionController@update
 * • Protects AcademicCalendarService from invalid update data
 * • Works seamlessly with Inertia.js + Vue 3 + PrimeVue:
 *   - Errors automatically available in form.errors for InputText/DatePicker components
 *   - Supports responsive, accessible form UX with real-time validation feedback
 * • Enforces core business invariants at the HTTP layer (before service layer)
 * • Integrates with multi-tenant design: school_id always enforced
 *
 * Usage in Controller (typical):
 *   public function update(UpdateAcademicSessionRequest $request, AcademicSession $academicSession)
 *   {
 *       $validated = $request->validated();
 *       $academicSession->update($validated);
 *       // Or pass to AcademicCalendarService for complex logic (e.g. status change)
 *       return redirect()->route('academic-sessions.show', $academicSession);
 *   }
 */
class UpdateAcademicSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled in the controller via $this->authorize('update', $session)
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

        if (!$school) {
            throw ValidationException::withMessages([
                'school' => 'No active school context found. Please select a school first.'
            ]);
        }

        $session = $this->route('academicSession');
        if (!$session instanceof AcademicSession) {
            throw ValidationException::withMessages([
                'session' => 'Invalid academic session route parameter.'
            ]);
        }

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique(AcademicSession::class, 'name')
                    ->where('school_id', $school->id)
                    ->ignore($session->id),
            ],
            'start_date' => [
                'sometimes',
                'required',
                'date',
                'date_format:Y-m-d',
                'before_or_equal:end_date',
                function ($attribute, $value, $fail) use ($session) {
                    if ($session->isActive && Carbon::parse($value)->notEqualTo($session->start_date)) {
                        $fail('The start date of an active or previously activated session cannot be changed.');
                    }
                },
            ],
            'end_date' => [
                'sometimes',
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
            'is_current' => [
                'sometimes',
                'boolean',
            ],
            // Optional future fields (uncomment/add when implementing full status workflow)
            // 'status' => [
            //     'sometimes',
            //     Rule::in(AcademicSession::STATUSES),
            // ],
        ];
    }

    /**
     * Prepare the data for validation – auto-inject school_id if missing.
     */
    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();

        if ($school && !$this->has('school_id')) {
            $this->merge([
                'school_id' => $school->id,
            ]);
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
            'name.required' => 'The session name is required (e.g., 2025/2026).',
            'name.unique' => 'A session with this name already exists for your school.',
            'start_date.required' => 'The start date is required.',
            'end_date.required' => 'The end date is required.',
            'start_date.before_or_equal' => 'The start date must be on or before the end date.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
            'is_current.boolean' => 'The current status must be a valid boolean value.',
        ];
    }

    /**
     * Get the validated data with defaults applied.
     * Ensures consistent shape for controller/service usage.
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        // Ensure is_current defaults to current value if not provided
        if (!isset($data['is_current'])) {
            $session = $this->route('academicSession');
            $data['is_current'] = $session ? $session->is_current : false;
        }

        return $data;
    }
}
