<?php

namespace App\Http\Requests;

use App\Models\SchoolSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * StoreSchoolSectionRequest — Production-Ready
 *
 * Validates all input for creating a new SchoolSection record.
 * Used by SchoolSectionController::store() and indirectly by
 * SchoolSectionService::createFromTemplates() for individual template rows.
 *
 * ── Authorization ────────────────────────────────────────────────────────
 * Uses permission-based authorization (not role-based). The permission
 * 'sections.create' is the stable contract — which roles carry this
 * permission is defined per-tenant and per-school, not hardcoded here.
 *
 * ── School Context ───────────────────────────────────────────────────────
 * school_id is intentionally NOT in the validation rules. It is always
 * injected server-side by SchoolSectionService from GetSchoolModel().
 * Accepting school_id from user input would be a cross-tenant injection
 * vulnerability — a user could create sections in another school's context.
 *
 * ── Soft-Delete Conflict Detection ──────────────────────────────────────
 * The unique rule on [school_id, name] in the migration does not exclude
 * soft-deleted rows. Without special handling, submitting a name that
 * matches a soft-deleted section would produce a confusing DB constraint
 * error instead of a helpful message.
 *
 * The after() hook detects this case before the DB constraint fires and
 * returns an actionable field-level error:
 *   "A section with this name was previously deleted. Restore it instead."
 *
 * This detection runs AFTER the standard unique check passes — meaning
 * it only fires when the name is NOT taken by an active section but IS
 * taken by a soft-deleted one. The standard unique rule (with whereNull)
 * handles the active conflict case first.
 *
 * ── Validation Order (bail on name) ─────────────────────────────────────
 * The name field uses 'bail' to stop on first failure. This prevents
 * the soft-delete check from running when the name is already invalid
 * (wrong format, too long, etc.) — avoiding unnecessary DB queries.
 *
 * ── short_code Format ────────────────────────────────────────────────────
 * Uses alpha_dash (letters, numbers, dashes, underscores) — same as name.
 * This allows codes like JSS, PRI, TC-1, SSS_TECH while preventing spaces
 * and special characters that would break report generation.
 *
 * ── sort_order ───────────────────────────────────────────────────────────
 * Optional on input — SchoolSectionObserver auto-assigns via max+10 logic
 * when not provided (or when still at default 99). Accepts 0–65535 to
 * match the unsignedSmallInteger column type in the migration.
 *
 * @see App\Models\SchoolSection
 * @see App\Services\SchoolSectionService
 * @see App\Observers\SchoolSectionObserver   (sort_order auto-assignment)
 * @see App\Http\Controllers\Settings\SchoolSectionController
 */
class StoreSchoolSectionRequest extends FormRequest
{
    /**
     * Authorize the request using permission-based check.
     *
     * Checks for 'sections.create' permission via Laratrust's hasPermission().
     * This permission is the stable contract — role names are tenant-specific
     * and are never checked directly in this system.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sections.create') ?? false;
    }

    /**
     * Validation rules for creating a new school section.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = GetSchoolModel()?->id;

        return [
            // ── name ──────────────────────────────────────────────────
            // Canonical slug identifier. Must be unique within the school
            // among non-deleted sections. The unique rule explicitly excludes
            // soft-deleted rows — the after() hook handles that conflict
            // separately with an actionable error message.
            // bail: stop on first name failure to avoid unnecessary DB queries
            // (particularly the soft-delete conflict check in after()).
            'name' => [
                'bail',
                'required',
                'string',
                'max:80',
                'alpha_dash',
                Rule::unique('school_sections', 'name')
                    ->where('school_id', $schoolId)
                    ->whereNull('deleted_at'),
            ],

            // ── display_name ──────────────────────────────────────────
            // Human-readable UI label. Shown in dropdowns, badges, reports.
            'display_name' => [
                'required',
                'string',
                'max:100',
            ],

            // ── short_code ────────────────────────────────────────────
            // Required (our migration decision). Used in badges, reports,
            // and dropdown labels throughout the app. alpha_dash allows
            // codes like JSS, PRI, TC-1, SSS_TECH.
            'short_code' => [
                'required',
                'string',
                'max:20',
                'alpha_dash',
            ],

            // ── description ───────────────────────────────────────────
            // Optional internal notes. Not shown in dropdowns or reports.
            // Does not trigger source mutation in SchoolSectionObserver.
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            // ── sort_order ────────────────────────────────────────────
            // Optional — SchoolSectionObserver auto-assigns via max+10
            // when not provided. Range matches unsignedSmallInteger (0–65535).
            // 0 is valid (place at top). Observer only auto-assigns when
            // value is still at the model default of 99.
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:65535',
            ],
        ];
    }

    /**
     * Add after-validation hooks.
     *
     * Runs after all standard rules pass. At this point we know:
     * - name is valid format
     * - name is NOT taken by an active section (unique rule passed)
     *
     * We then check: is it taken by a SOFT-DELETED section?
     * If yes → return actionable error pointing user to restore instead.
     *
     * This two-stage approach produces clear, specific error messages:
     *   Active conflict   → "already exists in this school"
     *   Deleted conflict  → "was previously deleted, restore it instead"
     *   No conflict       → proceed with creation
     *
     * @param  Validator  $validator
     * @return void
     */
    public function after(Validator $validator): void
    {
        // Only run if name passed all standard rules — no point checking
        // for a deleted conflict if the name format is already invalid
        if ($validator->errors()->has('name')) {
            return;
        }

        $this->checkForSoftDeletedConflict($validator);
    }

    /**
     * Check if a soft-deleted section exists with the same name
     * for the current school, and add a validation error if found.
     *
     * Uses withTrashed() to see deleted records, then explicitly checks
     * deleted_at IS NOT NULL to target only soft-deleted rows.
     *
     * @param  Validator  $validator
     * @return void
     */
    private function checkForSoftDeletedConflict(Validator $validator): void
    {
        $schoolId = GetSchoolModel()?->id;

        if ($schoolId === null) {
            return;
        }

        $softDeletedExists = SchoolSection::withTrashed()
            ->where('school_id', $schoolId)
            ->where('name', $this->input('name'))
            ->whereNotNull('deleted_at')
            ->exists();

        if ($softDeletedExists) {
            $validator->errors()->add(
                'name',
                'A section with this name was previously deleted for this school. '
                . 'Please restore the existing section instead of creating a new one.'
            );
        }
    }

    /**
     * Human-readable attribute names for validation error messages.
     * These replace the raw field names in default Laravel error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'         => 'section identifier',
            'display_name' => 'display name',
            'short_code'   => 'short code',
            'sort_order'   => 'display order',
        ];
    }

    /**
     * Custom validation messages for specific rule failures.
     * Only defined where the default Laravel message is unclear or generic.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A section with this identifier already exists in this school.',
            'name.alpha_dash' => 'The section identifier may only contain letters, numbers, dashes, and underscores.',
            'short_code.alpha_dash' => 'The short code may only contain letters, numbers, dashes, and underscores.',
            'short_code.required' => 'A short code is required and will be used in reports and badges throughout the system.',
        ];
    }
}
