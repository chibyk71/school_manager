<?php

namespace App\Http\Requests;

use App\Rules\NonOverlappingGradeRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreGradeRequest – Validation & data preparation for creating a new Grade
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Validates array of school_section_ids (many-to-many relationship via BelongsToSections trait)
 * • Enforces school-scoped uniqueness of grade code (across all assigned sections)
 * • Prevents overlapping score ranges within any of the selected sections (or school-wide)
 * • Automatically injects current school_id into payload
 * • Trims input strings for cleanliness
 * • Provides clear, context-aware error messages optimized for modal UX
 * • Uses bail where appropriate to improve feedback speed in forms
 * • No authorization logic here – kept in controller via permitted()
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Injected into GradeController::store()
 * • Supplies clean, validated data → GradeService::create()
 * • Integrates with frontend modal that sends school_section_ids[] (multi-select)
 * • Ensures no invalid grading scale is created before pivot rows are synced
 * • Designed to pair with UpdateGradeRequest (which must ignore current grade in unique/overlap checks)
 *
 * Best Practices Applied:
 * • Multi-tenant safety via GetSchoolModel()
 * • Array validation + existence check scoped to current school
 * • Custom overlap rule applied per-section logic (rule handles it internally)
 * • Helpful messages tailored for teachers/admins using PrimeVue forms
 * • Prepared for future additions (custom fields, GPA weight, effective dates)
 */
class StoreGradeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Authorization handled upstream in controller via permitted()
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
                // Unique across the school (code must not exist in any grade for this school)
                Rule::unique('grades', 'code')
                    ->where('school_id', $school->id ?? 0)
                    ->whereNull('deleted_at'), // ignore soft-deleted
            ],

            // Score range
            'min_score' => [
                'required',
                'integer',
                'min:0',
                'bail',
                new NonOverlappingGradeRange(null), // null = create mode
            ],

            'max_score' => [
                'required',
                'integer',
                'gt:min_score',
                'lte:100',
                'bail',
                new NonOverlappingGradeRange(null),
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
     * Custom validation messages – friendly & specific for modal users
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'             => 'Please provide a grade name (e.g., Excellent, A, Distinction).',
            'code.required'             => 'A short grade code is required (e.g., A, B+, 7).',
            'code.unique'               => 'This grade code is already used in this school.',
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
     *  - Inject current school_id
     *  - Trim whitespace from name & code
     *  - Convert single section_id to array (backward compatibility if frontend sends old format)
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

        // Backward compatibility: if frontend still sends single school_section_id
        if ($this->has('school_section_id') && ! $this->has('school_section_ids')) {
            $sectionId = $this->input('school_section_id');
            $data['school_section_ids'] = $sectionId ? [$sectionId] : [];
        }

        $this->merge($data);
    }
}
