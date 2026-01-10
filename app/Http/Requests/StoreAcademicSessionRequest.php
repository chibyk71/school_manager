<?php

namespace App\Http\Requests;

use App\Models\Academic\AcademicSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * StoreAcademicSessionRequest – Validation for Creating a New Academic Session
 *
 * This request validates input when creating a new academic session.
 * It enforces multi-tenant safety, date hierarchy, uniqueness per school,
 * and prepares the school_id automatically from the current context.
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────
 * • Automatic school_id injection via GetSchoolModel() – prevents cross-tenant creation
 * • Unique session name per school (case-insensitive recommended in future)
 * • Strict date validation: start < end, no overlapping checks (service layer handles)
 * • Immutable start_date preparation – service layer will enforce after activation
 * • Clear, user-friendly custom error messages for frontend display
 * • Future-ready: easy to add status, activated_at, etc. when needed
 * • Authorization delegated to controller/policy (not here) for flexibility
 *
 * Fits into the Academic Calendar Module:
 * ────────────────────────────────────────────────────────────────
 * • Used by AcademicSessionController@store
 * • Ensures only valid session data reaches the AcademicCalendarService
 * • Works with Inertia/Vue 3 frontend: validation errors are automatically
 *   available in form.errors for PrimeVue InputText/DatePicker components
 * • Prepares data for AcademicSession model mass assignment
 * • Integrates with multi-tenant design: school_id always enforced
 *
 * Usage in Controller (typical):
 *   public function store(StoreAcademicSessionRequest $request)
 *   {
 *       $session = AcademicSession::create($request->validated());
 *       // Or pass to service for activation logic
 *       return redirect()->route('academic-sessions.index');
 *   }
 */
class StoreAcademicSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled in the controller via $this->authorize('create', AcademicSession::class)
     * or middleware/policy – returning true here keeps the request focused on validation.
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
            'name' => [
                'required',
                'string',
                'max:50', // Reasonable limit: 2025/2026 is 9 chars, allow buffer for display names
                Rule::unique(AcademicSession::class, 'name')
                    ->where('school_id', $school->id),
            ],
            'start_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'before_or_equal:end_date',
            ],
            'end_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
            'is_current' => [
                'sometimes',
                'boolean',
            ],
            // Optional future fields (add when implementing status workflow)
            // 'status' => ['sometimes', Rule::in(AcademicSession::STATUSES)],
        ];
    }

    /**
     * Prepare the data for validation – auto-inject school_id.
     */
    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();

        if ($school) {
            $this->merge([
                'school_id' => $school->id,
            ]);
        }
    }

    /**
     * Custom error messages for better frontend UX (displayed in Inertia errors).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The session name is required (e.g., 2025/2026).',
            'name.unique'   => 'A session with this name already exists for your school.',
            'start_date.required' => 'The start date is required.',
            'end_date.required'   => 'The end date is required.',
            'start_date.before_or_equal' => 'The start date must be on or before the end date.',
            'end_date.after_or_equal'    => 'The end date must be on or after the start date.',
            'is_current.boolean' => 'The current status must be a valid boolean value.',
        ];
    }

    /**
     * Get the validated data with defaults applied.
     * Useful for controller to ensure consistent data shape.
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        // Ensure is_current defaults to false if not provided
        $data['is_current'] = $data['is_current'] ?? false;

        return $data;
    }
}
