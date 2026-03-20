<?php

namespace App\Http\Requests;

use App\Models\SchoolSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateSchoolSectionRequest — Production-Ready
 *
 * Validates all input for updating an existing SchoolSection record.
 * Used by SchoolSectionController::update() after the Policy has already
 * confirmed the user has 'sections.update' permission and school ownership.
 *
 * ── Authorization ────────────────────────────────────────────────────────
 * Returns true — authorization is fully handled by the Controller calling
 * $this->authorize('update', $section) which invokes SchoolSectionPolicy.
 * The Policy checks:
 *   1. User has 'sections.update' permission
 *   2. Section belongs to the user's currently active school
 * Duplicating those checks here would create maintenance burden and
 * potential inconsistency if the Policy logic ever changes.
 *
 * ── Partial Updates ──────────────────────────────────────────────────────
 * All content fields use 'sometimes' — only fields present in the request
 * are validated and updated. This supports PATCH semantics where the
 * frontend sends only the changed fields, not the full object.
 *
 * ── Immutable Fields ─────────────────────────────────────────────────────
 * school_id → prohibited: sections cannot be transferred between schools.
 *             school_id is set at creation and never changes.
 *
 * source    → prohibited: source mutation (template → custom) is handled
 *             exclusively by SchoolSectionObserver when a content field
 *             changes. User input must never override this — it would allow
 *             resetting a custom section back to 'template' artificially,
 *             which would break the template tracking logic.
 *
 * ── Unique Rule — Soft-Delete Awareness ──────────────────────────────────
 * The unique rule explicitly excludes soft-deleted rows via whereNull.
 * This allows renaming a section to a name that was previously used by
 * a now-deleted section — a valid operation unlike the store case.
 * The ignore($section->id) ensures the current section's own name is
 * excluded from the uniqueness check (standard update pattern).
 *
 * ── No Soft-Delete Conflict Check ────────────────────────────────────────
 * Unlike StoreSchoolSectionRequest, no after() hook is needed here.
 * On update, renaming to a name held by a soft-deleted section is allowed
 * — the unique rule (with whereNull) will pass correctly, and no DB
 * constraint conflict occurs. The soft-deleted record remains restorable
 * under a different name if needed.
 *
 * ── source vs Observer ───────────────────────────────────────────────────
 * source is prohibited in this request. SchoolSectionObserver watches
 * the updating hook and automatically changes source from 'template' to
 * 'custom' when name, display_name, or short_code are dirty. These two
 * mechanisms are completely independent — the Observer fires after this
 * validation passes, on the Eloquent save() call in the controller.
 *
 * ── Route Parameter ──────────────────────────────────────────────────────
 * Uses 'schoolSection' as the route parameter name (not 'section') to
 * avoid ambiguity with ClassSection and other section-type models in
 * the routes file.
 *
 * @see App\Models\SchoolSection
 * @see App\Policies\SchoolSectionPolicy    (authorization)
 * @see App\Observers\SchoolSectionObserver (source mutation)
 * @see App\Services\SchoolSectionService   (business logic)
 * @see App\Http\Controllers\Settings\SchoolSectionController
 */
class UpdateSchoolSectionRequest extends FormRequest
{
    /**
     * Authorization is handled entirely by the Controller + Policy.
     * SchoolSectionController calls $this->authorize('update', $section)
     * before this request's rules() are evaluated.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for updating a school section.
     * All content fields are 'sometimes' to support partial updates.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Route model binding — 'schoolSection' matches the route parameter
        // name defined in routes/settings.php to distinguish from other
        // section-type models (ClassSection, etc.)
        $section = $this->route('schoolSection');
        $schoolId = $section->school_id;

        return [
            // ── name ──────────────────────────────────────────────────
            // bail first: stop on first failure to avoid unnecessary
            // DB queries when the name format is already invalid.
            // whereNull: allows renaming to a previously-deleted name.
            // ignore: current section excluded from uniqueness check.
            'name' => [
                'bail',
                'sometimes',
                'required',
                'string',
                'max:80',
                'alpha_dash',
                Rule::unique('school_sections', 'name')
                    ->where('school_id', $schoolId)
                    ->whereNull('deleted_at')
                    ->ignore($section->id),
            ],

            // ── display_name ──────────────────────────────────────────
            // Changing display_name triggers source mutation in Observer.
            'display_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            // ── short_code ────────────────────────────────────────────
            // Required on create; on update it is 'sometimes|required'
            // meaning: if sent, it cannot be empty or null.
            // Changing short_code triggers source mutation in Observer.
            // alpha_dash: allows JSS, PRI, TC-1, SSS_TECH.
            'short_code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'alpha_dash',
            ],

            // ── description ───────────────────────────────────────────
            // Optional notes. Does NOT trigger source mutation in Observer.
            // Can be set to null to clear an existing description.
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],

            // ── is_active ─────────────────────────────────────────────
            // Soft toggle for hiding sections from pickers and dropdowns.
            // Deactivating triggers SchoolSectionDeactivated event (fired
            // by service, not here). Does NOT trigger source mutation.
            'is_active' => [
                'sometimes',
                'boolean',
            ],

            // ── sort_order ────────────────────────────────────────────
            // Explicit reordering. Range matches unsignedSmallInteger.
            // Does NOT trigger source mutation in Observer.
            'sort_order' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:65535',
            ],

            // ── IMMUTABLE FIELDS ──────────────────────────────────────

            // school_id: sections cannot be transferred between schools.
            // Set at creation, never changed. Any attempt to send this
            // field is rejected immediately.
            'school_id' => [
                'prohibited',
            ],

            // source: controlled exclusively by SchoolSectionObserver.
            // Changes from 'template' to 'custom' automatically when
            // name, display_name, or short_code are updated.
            // User input must never override this mechanism.
            'source' => [
                'prohibited',
            ],
        ];
    }

    /**
     * Human-readable attribute names for validation error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'section identifier',
            'display_name' => 'display name',
            'short_code' => 'short code',
            'sort_order' => 'display order',
            'is_active' => 'active status',
        ];
    }

    /**
     * Custom messages for specific rule failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' =>
                'Another section in this school already uses this identifier.',

            'name.alpha_dash' =>
                'The section identifier may only contain letters, numbers, dashes, and underscores.',

            'short_code.alpha_dash' =>
                'The short code may only contain letters, numbers, dashes, and underscores.',

            'short_code.required' =>
                'Short code cannot be removed — it is required for reports and badges.',

            'school_id.prohibited' =>
                'The owning school cannot be changed after a section is created.',

            'source.prohibited' =>
                'Section origin tracking is managed automatically and cannot be modified directly.',
        ];
    }
}
