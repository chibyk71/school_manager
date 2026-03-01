<?php

namespace App\Http\Requests;

use App\Models\Academic\Grade;
use App\Rules\NonOverlappingGradeRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateGradeRequest – Validation & data preparation for updating an existing Grade
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Validates array of school_section_ids (many-to-many via BelongsToSections trait)
 * • Ensures code uniqueness within the school (ignores current grade record)
 * • Prevents score range overlaps in any of the assigned sections (or school-wide)
 * • Injects current school_id if missing
 * • Trims input strings (name, code) for consistency
 * • Provides clear, context-specific error messages optimized for modal UX
 * • Uses bail on critical chains to improve form feedback speed
 * • Backward compatibility handling for legacy single school_section_id
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Injected into GradeController::update()
 * • Supplies validated & sanitized data → GradeService::update()
 * • Works with frontend edit modal that sends school_section_ids[] array
 * • Ensures no invalid state is saved (overlaps, duplicate codes, invalid sections)
 * • Pairs with StoreGradeRequest (very similar rules, but ignores current grade)
 * • Prevents data corruption before pivot sync or model update
 *
 * Best Practices Applied:
 * • Multi-tenant safety via GetSchoolModel() + scoped existence checks
 * • Custom overlap rule receives current $grade instance (for exclusion)
 * • Unique rule ignores current record + soft-deleted entries
 * • Helpful messages tailored for teachers/admins using PrimeVue/Inertia
 * • No authorization logic – handled in controller via permitted()
 */
class UpdateGradeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Authorization handled in controller via permitted()
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
        $grade  = $this->route('grade'); // Route model binding – current Grade instance

        if (! $grade) {
            // Fallback if route binding fails (rare)
            $grade = Grade::find($this->input('id'));
        }

        return [
            // Many-to-many: array of section IDs (nullable = school-wide grade)
            'school_section_ids' => [
                'nullable',
                'array',
            ],
            'school_section_ids.*' => [
                'exists:school_sections,id',
                Rule::exists('school_sections', 'id')->where('school_id', $school->id ?? 0),
            ],

            // Grade identification
            'name' => [
                'required',
                'string',
                'max:255',
                'bail',
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                'bail',
                Rule::unique('grades', 'code')
                    ->ignore($grade?->id)
                    ->where('school_id', $school->id ?? 0)
                    ->whereNull('deleted_at'),
            ],

            // Score range
            'min_score' => [
                'required',
                'integer',
                'min:0',
                'bail',
                new NonOverlappingGradeRange($grade), // Pass current grade to exclude self
            ],

            'max_score' => [
                'required',
                'integer',
                'gt:min_score',
                'lte:100',
                'bail',
                new NonOverlappingGradeRange($grade),
            ],

            // Optional remark
            'remark' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Custom validation messages – friendly & actionable for modal users
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'             => 'Please provide a grade name (e.g., Excellent, A, Distinction).',
            'code.required'             => 'A short grade code is required (e.g., A, B+, 7).',
            'code.unique'               => 'This grade code is already in use by another grade in this school.',
            'school_section_ids.array'  => 'School sections must be provided as an array.',
            'school_section_ids.*.exists' => 'One or more selected sections do not exist.',
            'school_section_ids.*.exists_in_school' => 'Selected section does not belong to your current school.',
            'min_score.min'             => 'Minimum score cannot be negative.',
            'max_score.gt'              => 'Maximum score must be higher than the minimum score.',
            'max_score.lte'             => 'Maximum score cannot exceed 100.',
        ];
    }

    /**
     * Prepare data before validation:
     *  - Inject current school_id if missing
     *  - Trim whitespace from name & code
     *  - Convert legacy single school_section_id → array (smooth migration)
     */
    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();

        $data = [];

        if ($school && ! $this->has('school_id')) {
            $data['school_id'] = $school->id;
        }

        // Trim strings
        if ($this->filled('name')) {
            $data['name'] = trim($this->input('name'));
        }
        if ($this->filled('code')) {
            $data['code'] = trim($this->input('code'));
        }

        // Handle legacy single ID field (if frontend still uses old format)
        if ($this->has('school_section_id') && ! $this->has('school_section_ids')) {
            $sectionId = $this->input('school_section_id');
            $data['school_section_ids'] = $sectionId ? [$sectionId] : [];
        }

        $this->merge($data);
    }
}
