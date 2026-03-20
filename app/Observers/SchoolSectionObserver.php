<?php

namespace App\Observers;

use App\Models\SchoolSection;

/**
 * SchoolSectionObserver
 *
 * Handles automatic model-state concerns for SchoolSection lifecycle events.
 * Registered via the #[ObservedBy(SchoolSectionObserver::class)] attribute
 * on the SchoolSection model — no manual registration in AppServiceProvider needed.
 *
 * ── Responsibility Boundary ─────────────────────────────────────────────
 * This observer handles ONLY pure model-state concerns that must happen
 * synchronously as part of the save operation:
 *
 *   1. sort_order auto-assignment (creating hook)
 *   2. source mutation: template → custom (updating hook)
 *
 * Everything else is handled elsewhere:
 *   - Cache invalidation    → InvalidateSectionCache listener (queued)
 *   - ClassLevel cascade    → CascadeDeactivateClassLevels listener (queued)
 *   - Admin notifications   → NotifyAdminOnSectionDeleted listener (queued)
 *   - Laratrust sync        → SyncLaratrustTeamOnRestore listener (queued)
 *   - Domain event firing   → SchoolSectionService (explicit, controlled)
 *
 * ── Why Service Fires Events, Not Observer ──────────────────────────────
 * The observer's own source mutation triggers an additional save() call.
 * If the observer also fired events, that second save would re-trigger
 * the updated event causing double-firing or infinite loops. Keeping event
 * dispatch in the service layer gives full control over which user-initiated
 * actions warrant an event, and prevents seeders/factories/tests from
 * accidentally triggering side effects.
 *
 * ── Sort Order Auto-Assignment Logic ────────────────────────────────────
 * When sort_order is not explicitly provided (or is still the default 99),
 * the observer queries the school's current max sort_order and adds 10.
 * This creates natural spacing (10, 20, 30...) that leaves room for manual
 * reordering between existing sections without renumbering everything.
 * If no sections exist yet for the school, starts at 10.
 *
 * Example:
 *   School has sections at sort_order 10, 20, 30
 *   New section created without sort_order → assigned 40
 *   Admin wants it between 20 and 30 → manually set to 25
 *
 * ── Source Mutation Logic ────────────────────────────────────────────────
 * source = 'template' means the section was created from a predefined
 * template in config/school_section_templates.php and has not been
 * meaningfully customized. source = 'custom' means it has diverged.
 *
 * Only these fields flipping trigger the mutation:
 *   - name         (canonical identifier changed)
 *   - display_name (visible label changed — most user-facing field)
 *   - short_code   (report/badge code changed)
 *
 * These fields do NOT trigger mutation:
 *   - description  (annotation, not functional customization)
 *   - is_active    (operational toggle, not content change)
 *   - sort_order   (organizational preference, not content change)
 *
 * The mutation write (forceFill + saveQuietly) uses saveQuietly() to
 * prevent re-triggering the updating/updated observer hooks, which would
 * cause an infinite loop.
 *
 * @see App\Models\SchoolSection
 * @see App\Services\SchoolSectionService  (fires domain events)
 * @see App\Listeners\SchoolSection\InvalidateSectionCache
 */
class SchoolSectionObserver
{
    /**
     * Fields whose change on a template-sourced section should flip
     * source from 'template' to 'custom'.
     *
     * Kept as a class constant so it is easily auditable and can be
     * referenced in tests without duplicating the list.
     */
    private const SOURCE_FLIP_FIELDS = [
        'name',
        'display_name',
        'short_code',
    ];

    // ────────────────────────────────────────────────────────────────────
    // Creating Hook — sort_order auto-assignment
    // ────────────────────────────────────────────────────────────────────

    /**
     * Fires BEFORE the model is inserted into the database.
     * Auto-assigns sort_order if not explicitly provided.
     *
     * Uses the creating hook (not created) so the value is set
     * before the INSERT statement runs — no second query needed.
     */
    public function creating(SchoolSection $section): void
    {
        // Only auto-assign if sort_order was not explicitly set
        // (still sitting at the model's default value of 99)
        if ($section->sort_order === 99) {
            $section->sort_order = $this->resolveNextSortOrder($section);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // Updating Hook — source mutation
    // ────────────────────────────────────────────────────────────────────

    /**
     * Fires BEFORE the model update is written to the database.
     * Checks if a meaningful content field changed on a template section,
     * and if so, mutates source to 'custom' before the UPDATE runs.
     *
     * Uses saveQuietly() for the source mutation write to prevent
     * re-entering this hook and causing an infinite loop.
     *
     * Note: We check isDirty() on SOURCE_FLIP_FIELDS, not wasChanged(),
     * because wasChanged() is only available after the save completes.
     * isDirty() checks pending changes before the write — correct here.
     */
    public function updating(SchoolSection $section): void
    {
        // Only applies to template-sourced sections
        if ($section->source !== 'template') {
            return;
        }

        // Check if any source-flip field is dirty (has a pending change)
        $hasContentChange = collect(self::SOURCE_FLIP_FIELDS)
            ->contains(fn(string $field) => $section->isDirty($field));

        if ($hasContentChange) {
            // Use forceFill to bypass any guarding, then saveQuietly
            // to write ONLY the source change without re-triggering
            // this observer hook
            $section->forceFill(['source' => 'custom'])->saveQuietly();
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ────────────────────────────────────────────────────────────────────

    /**
     * Resolve the next sort_order value for a new section within its school.
     *
     * Queries the school's existing sections for the current maximum
     * sort_order value, then adds 10 to create natural spacing.
     *
     * The spacing of 10 (not 1) allows admins to insert sections between
     * existing ones by assigning intermediate values (e.g. 15 between 10 and 20)
     * without having to renumber every other section.
     *
     * Falls back to 10 if this is the school's first section.
     *
     * Note: This query is scoped to the school via BelongsToSchool global
     * scope on SchoolSection — no explicit school_id filter needed here.
     *
     * @param  SchoolSection  $section  The section being created
     * @return int
     */
    private function resolveNextSortOrder(SchoolSection $section): int
    {
        $currentMax = SchoolSection::withoutGlobalScope(\App\Models\Scopes\SchoolScope::class)
            ->where('school_id', $section->school_id)
            ->withTrashed()
            ->max('sort_order');

        // If no sections exist yet, start at 10
        // Otherwise, next position is max + 10
        return $currentMax === null ? 10 : (int) $currentMax + 10;
    }
}
