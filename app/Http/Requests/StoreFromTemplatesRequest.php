<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\SchoolSection;

/**
 * StoreFromTemplatesRequest
 *
 * Validates a request to create one or more SchoolSection records from
 * predefined templates defined in config/school_section_templates.php.
 *
 * ── Purpose ──────────────────────────────────────────────────────────────
 * This request powers the "Add from Templates" onboarding modal — a
 * quick-start tool that lets a new school populate their sections from
 * a curated list of Nigerian K-12 section types without manually entering
 * names, short codes, or sort orders.
 *
 * ── What Gets Submitted ──────────────────────────────────────────────────
 * The frontend submits only an array of template key strings:
 *   { "keys": ["primary", "junior_secondary", "senior_secondary"] }
 *
 * The backend owns all canonical template data (names, short codes,
 * sort orders, descriptions). The frontend never submits template content,
 * which prevents tampering and keeps the config as the single source of truth.
 *
 * ── Valid Keys ───────────────────────────────────────────────────────────
 * Keys are validated against config/school_section_templates.php at
 * request time. Unknown keys are rejected. This keeps validation in sync
 * with the config automatically — no hardcoded list to maintain.
 *
 * ── Conflict Detection (Upfront, All-or-Nothing) ─────────────────────────
 * All submitted keys are checked for conflicts before any records are
 * created. If ANY conflict exists, the entire request is rejected with
 * field-level errors. This "validate all, create nothing" approach avoids
 * partial success states that are hard for the frontend to reason about.
 *
 * Two distinct conflict types are detected and reported separately:
 *
 * 1. Active conflict — section already exists and is not deleted:
 *    "A section named 'Primary School' already exists for this school."
 *
 * 2. Soft-deleted conflict — section exists but was previously deleted:
 *    "A section named 'Junior Secondary' was previously deleted.
 *     Restore it from the trashed sections view instead of re-creating it."
 *
 * Both conflict types are detected in a SINGLE query using withTrashed(),
 * then split by deleted_at in PHP — no N+1 regardless of how many keys
 * are submitted.
 *
 * ── Duplicate Keys in Payload ────────────────────────────────────────────
 * Submitting the same key twice (e.g. ["primary", "primary"]) is caught
 * by the 'distinct' rule on keys.* before the after() hook runs.
 *
 * ── Authorization ────────────────────────────────────────────────────────
 * Requires the sections.create permission. Permission-based, not role-based.
 * Which roles carry this permission is defined per-tenant.
 *
 * ── Source Field ─────────────────────────────────────────────────────────
 * Records created from this request have source = 'template'.
 * The SchoolSectionObserver flips source to 'custom' if name, display_name,
 * or short_code is later changed. This happens automatically on update —
 * nothing needs to be set here.
 *
 * ── Service Layer ────────────────────────────────────────────────────────
 * This request only validates. The actual creation loop (pulling config
 * data per key, setting sort_order, firing events) is handled by
 * SchoolSectionService::createFromTemplates(array $keys): Collection
 *
 * @see config/school_section_templates.php
 * @see App\Services\SchoolSectionService::createFromTemplates()
 * @see App\Observers\SchoolSectionObserver  (source flip on edit)
 * @see App\Http\Controllers\Settings\SchoolSectionController
 */
class StoreFromTemplatesRequest extends FormRequest
{
    /**
     * The resolved template config, cached after first access.
     * Avoids repeated config() calls across rules(), attributes(), after().
     *
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $templates = null;

    /**
     * Resolve and cache the template config.
     *
     * @return array<string, array<string, mixed>>
     */
    private function templates(): array
    {
        return $this->templates ??= config('school_section_templates', []);
    }

    /**
     * Authorize the request.
     *
     * Requires sections.create permission. The same permission that guards
     * manual single-section creation — creating from a template is still
     * creating a section.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->hasPermission('sections.create');
    }

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $validKeys = array_keys($this->templates());

        return [
            // ── keys array ────────────────────────────────────────────
            // Must be a non-empty array. Upper bound is the total number
            // of available templates — you cannot submit more keys than
            // templates that exist.
            'keys' => [
                'required',
                'array',
                'min:1',
                'max:' . count($validKeys),
            ],

            // ── each key ──────────────────────────────────────────────
            // Must be a string matching a known template key from config.
            // 'distinct' catches duplicate keys in the same submission
            // (e.g. ["primary", "primary"]) before the after() hook runs.
            'keys.*' => [
                'required',
                'string',
                'distinct',
                \Illuminate\Validation\Rule::in($validKeys),
            ],
        ];
    }

    /**
     * Additional validation after the main rules pass.
     *
     * Runs a single DB query to detect both active conflicts and
     * soft-deleted conflicts for all submitted keys at once.
     *
     * Active conflicts → error telling the section already exists.
     * Soft-deleted conflicts → error telling the user to restore instead.
     *
     * The error key is 'keys' (not 'keys.0', 'keys.1', etc.) so the
     * frontend can display the full conflict list as a group rather than
     * per-index. This matches how the frontend modal will present the
     * conflict summary to the user.
     *
     * @param  Validator  $validator
     * @return void
     */
    public function after(Validator $validator): void
    {
        // Only run conflict checks if the main rules passed.
        // If keys.* is already invalid (unknown key, wrong type, etc.)
        // there is nothing useful to check here.
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $submittedKeys = $this->input('keys', []);
        $school = GetSchoolModel();

        if ($school === null) {
            $validator->errors()->add(
                'keys',
                'No active school context found. Please refresh and try again.'
            );
            return;
        }

        // ── Resolve the section names for submitted keys ───────────────
        // We need the canonical name from config to match against the DB.
        // SchoolSection unique constraint is on [school_id, name].
        $templates = $this->templates();
        $namesByKey = [];

        foreach ($submittedKeys as $key) {
            if (isset($templates[$key]['name'])) {
                $namesByKey[$key] = $templates[$key]['name'];
            }
        }

        if (empty($namesByKey)) {
            // All submitted keys resolved to nothing — unusual but guard it.
            return;
        }

        // ── Single query: fetch both active and soft-deleted conflicts ──
        // withTrashed() returns rows regardless of deleted_at.
        // We split active vs soft-deleted in PHP — no extra query needed.
        $existing = SchoolSection::withTrashed()
            ->where('school_id', $school->id)
            ->whereIn('name', array_values($namesByKey))
            ->get(['name', 'deleted_at'])
            ->keyBy('name');

        if ($existing->isEmpty()) {
            // No conflicts at all — all keys are safe to create.
            return;
        }

        // ── Split conflicts into two buckets ───────────────────────────
        $activeConflicts = [];   // exists and NOT soft-deleted
        $trashedConflicts = [];   // exists but IS soft-deleted

        foreach ($namesByKey as $key => $name) {
            $match = $existing->get($name);

            if ($match === null) {
                continue; // No conflict for this key.
            }

            if ($match->deleted_at === null) {
                $activeConflicts[$key] = $name;
            } else {
                $trashedConflicts[$key] = $name;
            }
        }

        // ── Report active conflicts ────────────────────────────────────
        foreach ($activeConflicts as $key => $name) {
            $validator->errors()->add(
                'keys',
                "A section named \"{$name}\" already exists for this school."
            );
        }

        // ── Report soft-deleted conflicts ──────────────────────────────
        foreach ($trashedConflicts as $key => $name) {
            $validator->errors()->add(
                'keys',
                "A section named \"{$name}\" was previously deleted. "
                . 'Restore it from the trashed sections view instead of re-creating it.'
            );
        }
    }

    /**
     * Human-readable attribute names for error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'keys' => 'selected templates',
            'keys.*' => 'template',
        ];
    }

    /**
     * Custom messages for specific rule failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxTemplates = count($this->templates());

        return [
            'keys.required' =>
                'Please select at least one template.',

            'keys.min' =>
                'Please select at least one template.',

            'keys.max' =>
                "You cannot select more than {$maxTemplates} templates at once.",

            'keys.*.required' =>
                'Each selected template key must be present.',

            'keys.*.string' =>
                'Each template key must be a string.',

            'keys.*.distinct' =>
                'Duplicate template keys were submitted. Please select each template only once.',

            'keys.*.in' =>
                'One or more selected templates are not recognised. '
                . 'Please refresh the page and try again.',
        ];
    }

    /**
     * Expose the validated keys as a typed array for the controller.
     *
     * Convenience method so the controller can call:
     *   $request->validatedKeys()
     * instead of:
     *   $request->validated()['keys']
     *
     * @return array<int, string>
     */
    public function validatedKeys(): array
    {
        return $this->validated()['keys'];
    }
}
