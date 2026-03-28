<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\ClassSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreClassSectionRequest — validates manual single class section creation.
 *
 * ── Context ───────────────────────────────────────────────────────────────────
 * Used by ClassSectionController::store() when an admin manually creates
 * one section (e.g., adding a new arm mid-session, not bulk generation).
 *
 * The $classLevel is injected via route model binding on the controller method.
 * This request validates the fields that go into THAT specific class level.
 *
 * ── Uniqueness Scope ──────────────────────────────────────────────────────────
 * name must be unique within: school_id + class_level_id.
 * The DB also enforces this via a composite unique constraint.
 * We validate here first to surface a readable error instead of a DB exception.
 *
 * ── Authorization ─────────────────────────────────────────────────────────────
 * Policy check: ClassSectionPolicy::create()
 * The controller calls $this->authorize() — NOT this request's authorize()
 * method — because the policy requires the ClassLevel model from route binding
 * which is not available here without manual resolution. This is consistent
 * with how ClassLevelController handles it.
 */
class StoreClassSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $this->authorize()
    }

    public function rules(): array
    {
        // class_level_id comes from the route parameter — resolved by controller
        // and passed to rules() via $this->route('classLevel') or
        // $this->classLevelId (set by controller before validation runs).
        $classLevelId = $this->route('classLevel')?->id
            ?? $this->route('class_level_id');

        $schoolId = GetSchoolModel()?->id;

        return [
            // ── Required core fields ──────────────────────────────────────
            'name' => [
                'required',
                'string',
                'max:50',
                // Unique within this school + class level (case-insensitive)
                Rule::unique('class_sections', 'name')
                    ->where('school_id', $schoolId)
                    ->where('class_level_id', $classLevelId)
                    ->whereNull('deleted_at'),
            ],

            // ── Optional fields ───────────────────────────────────────────

            // Admin can override the auto-computed display name
            'display_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            // Physical room identifier (e.g., "Block A Room 3")
            'room' => [
                'nullable',
                'string',
                'max:100',
                // Room must be unique per school (not globally)
                Rule::unique('class_sections', 'room')
                    ->where('school_id', $schoolId)
                    ->whereNull('deleted_at'),
            ],

            // 0 = uncapped; positive integer = max students allowed
            'capacity' => [
                'nullable',
                'integer',
                'min:0',
                'max:1000',
            ],

            // Staff UUID — must exist and belong to current school
            'form_teacher_id' => [
                'nullable',
                'uuid',
                Rule::exists('staff', 'id')->where('school_id', $schoolId),
            ],

            // Sort order — auto-assigned if not provided (max + 10)
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],

            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => 'The section name (arm label) is required.',
            'name.max'              => 'The section name must not exceed 50 characters.',
            'name.unique'           => 'A section with this name already exists in this class level.',
            'room.unique'           => 'This room is already assigned to another section in your school.',
            'capacity.min'          => 'Capacity must be 0 (uncapped) or a positive number.',
            'capacity.max'          => 'Capacity cannot exceed 1,000 students.',
            'form_teacher_id.exists'=> 'The selected form teacher was not found.',
            'status.in'             => 'Status must be either active or inactive.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'            => 'section name',
            'display_name'    => 'display name',
            'room'            => 'room',
            'capacity'        => 'capacity',
            'form_teacher_id' => 'form teacher',
            'sort_order'      => 'sort order',
            'status'          => 'status',
        ];
    }
}
