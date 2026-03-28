<?php

namespace App\Http\Requests\Academic;

use App\Support\ClassSectionNamePresets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * BulkGenerateClassSectionRequest — validates arm generation across class levels.
 *
 * ── What Gets Validated ───────────────────────────────────────────────────────
 * 1. class_level_ids   — one or more class levels to generate arms for
 * 2. naming_style      — preset key ('alphabetic', 'precious', 'custom', etc.)
 * 3. arm_count         — how many arms to create (used for preset styles)
 * 4. custom_arms       — explicit arm labels (used when naming_style = 'custom')
 * 5. defaults          — optional capacity/status to apply to all new sections
 *
 * ── Naming Style vs Custom Arms ───────────────────────────────────────────────
 * When naming_style is NOT 'custom':
 *   arm_count is required (1 to preset max).
 *   The controller resolves arm_count → arm labels via ClassSectionNamePresets::resolve().
 *
 * When naming_style IS 'custom':
 *   custom_arms is required — admin types their own arm labels.
 *   arm_count is ignored.
 *   Each custom arm label must be unique within the request payload
 *   (DB uniqueness per class level is checked by the service).
 *
 * ── Authorization ─────────────────────────────────────────────────────────────
 * Handled in controller: $this->authorize('bulkGenerate', ClassSection::class)
 */
class BulkGenerateClassSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId      = GetSchoolModel()?->id;
        $isCustomStyle = $this->input('naming_style') === 'custom';
        $namingStyle   = $this->input('naming_style');

        // Get the max count for the selected preset (null for custom)
        $maxCount = ($namingStyle && $namingStyle !== 'custom')
            ? ClassSectionNamePresets::maxCount($namingStyle)
            : null;

        return [
            // ── Class levels to generate into ─────────────────────────────
            'class_level_ids' => [
                'required',
                'array',
                'min:1',
                'max:50', // More than enough class levels per school
            ],
            'class_level_ids.*' => [
                'required',
                'uuid',
                // Each class level must exist and belong to the current school
                Rule::exists('class_levels', 'id')->where('school_id', $schoolId),
            ],

            // ── Naming style ──────────────────────────────────────────────
            'naming_style' => [
                'required',
                'string',
                Rule::in([...ClassSectionNamePresets::allKeys(), 'custom']),
            ],

            // ── Arm count (preset styles only) ────────────────────────────
            'arm_count' => [
                $isCustomStyle ? 'nullable' : 'required',
                'integer',
                'min:1',
                $maxCount ? "max:{$maxCount}" : 'max:10',
            ],

            // ── Custom arm labels (custom style only) ─────────────────────
            'custom_arms' => [
                $isCustomStyle ? 'required' : 'nullable',
                'array',
                'min:1',
                'max:10',
            ],
            'custom_arms.*' => [
                'required',
                'string',
                'max:50',
                // Each custom arm label must be distinct within the request
                'distinct:ignore_case',
            ],

            // ── Defaults applied to all generated sections ─────────────────
            'defaults'              => ['nullable', 'array'],
            'defaults.capacity'     => ['nullable', 'integer', 'min:0', 'max:1000'],
            'defaults.status'       => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'class_level_ids.required'    => 'At least one class level must be selected.',
            'class_level_ids.*.exists'    => 'One or more selected class levels were not found.',
            'naming_style.required'       => 'A naming style must be selected.',
            'naming_style.in'             => 'Invalid naming style selected.',
            'arm_count.required'          => 'Number of arms to generate is required for preset naming styles.',
            'arm_count.min'               => 'At least 1 arm must be generated.',
            'arm_count.max'               => 'The selected naming style does not support that many arms.',
            'custom_arms.required'        => 'Custom arm names are required when using the custom naming style.',
            'custom_arms.min'             => 'At least one custom arm name must be provided.',
            'custom_arms.*.required'      => 'Each arm name is required.',
            'custom_arms.*.max'           => 'Each arm name must not exceed 50 characters.',
            'custom_arms.*.distinct'      => 'Arm names must be unique within your selection.',
            'defaults.capacity.min'       => 'Capacity must be 0 (uncapped) or a positive number.',
            'defaults.capacity.max'       => 'Capacity cannot exceed 1,000 students.',
        ];
    }

    public function attributes(): array
    {
        return [
            'class_level_ids'   => 'class levels',
            'naming_style'      => 'naming style',
            'arm_count'         => 'number of arms',
            'custom_arms'       => 'custom arm names',
            'defaults.capacity' => 'default capacity',
            'defaults.status'   => 'default status',
        ];
    }

    /**
     * Resolve the final arm labels from the validated request data.
     *
     * Called by the controller before passing to the service — keeps the
     * controller thin and this logic co-located with its validation.
     *
     * @return array<string>  e.g. ['A', 'B', 'C'] or ['Diamond', 'Gold', 'Ruby']
     */
    public function resolveArms(): array
    {
        $validated = $this->validated();

        if ($validated['naming_style'] === 'custom') {
            return array_map('trim', $validated['custom_arms']);
        }

        return ClassSectionNamePresets::resolve(
            $validated['naming_style'],
            $validated['arm_count']
        );
    }
}
