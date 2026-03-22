<?php

namespace App\Services;

use App\Events\SchoolSection\SchoolSectionCreated;
use App\Events\SchoolSection\SchoolSectionDeactivated;
use App\Events\SchoolSection\SchoolSectionDeleted;
use App\Events\SchoolSection\SchoolSectionRestored;
use App\Events\SchoolSection\SchoolSectionUpdated;
use App\Models\SchoolSection;
use App\Support\SectionCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * SchoolSectionService — Production-Ready
 *
 * Central business logic layer for all SchoolSection operations.
 * The controller is a thin HTTP adapter — every domain concern lives here.
 *
 * ── Responsibilities ─────────────────────────────────────────────────────
 * This service handles:
 *   - createOne()            Single section creation from validated form data
 *   - createFromTemplates()  Bulk creation from config-defined templates
 *   - update()               Single section update with source tracking
 *   - bulkDelete()           Soft-delete one or more sections
 *   - bulkRestore()          Restore one or more soft-deleted sections
 *   - bulkForceDelete()      Permanently delete one or more sections
 *   - bulkToggleStatus()     Activate or deactivate one or more sections
 *   - reorder()              Reassign sort_order from a new positional array
 *
 * ── Authorization Boundary ───────────────────────────────────────────────
 * This service does NOT check permissions. Permission checks happen in:
 *   - Form Requests (authorize() method)
 *   - Controller ($this->authorize() via Policy)
 * The service receives already-authorized data and executes business logic.
 *
 * ── Transaction Strategy ─────────────────────────────────────────────────
 * Every method is wrapped in DB::transaction(). This includes single-row
 * operations because:
 *   1. Observers may write additional data during create/update hooks
 *   2. Events fired after the operation should only reach listeners if
 *      the DB write fully committed — events are dispatched inside the
 *      transaction closure but after the primary write, so any listener
 *      failure can still roll back the whole unit of work
 *   3. Consistency: "always transactional" is simpler to reason about
 *      than "transactional only for bulk"
 *
 * ── Event Firing ─────────────────────────────────────────────────────────
 * Domain events are fired inside each transaction closure AFTER the
 * primary write succeeds. This ensures listeners only see committed state.
 * Events fired:
 *   SchoolSectionCreated   → after createOne() or each row in createFromTemplates()
 *   SchoolSectionUpdated   → after update()
 *   SchoolSectionDeactivated → after bulkToggleStatus() when is_active = false
 *   SchoolSectionDeleted   → after bulkDelete() (once, with all deleted models)
 *   SchoolSectionRestored  → after bulkRestore() (once, with all restored models)
 *
 * ── Cache Invalidation ───────────────────────────────────────────────────
 * SectionCache::forget() is called after every mutating operation.
 * SectionCache encapsulates the cache key logic so the service never
 * knows or cares about cache key formats.
 *
 * ── Return Values ────────────────────────────────────────────────────────
 * Mutation methods return the affected model or Eloquent Collection.
 * Bulk state-change methods (delete, restore, forceDelete, toggle, reorder)
 * return int (affected row count) for use in success messages.
 *
 * ── Business Rule Failures ───────────────────────────────────────────────
 * When a domain rule prevents an operation (e.g. deleting a section that
 * has active students), the service throws ValidationException with a
 * user-friendly message. This integrates automatically with Laravel's
 * exception handler (422 response) and matches the error shape the
 * frontend already handles from Form Request validation.
 *
 * ── Multi-Tenant Safety ──────────────────────────────────────────────────
 * SchoolSection uses the BelongsToSchool trait, which applies a global
 * scope filtering all queries to the current school. This service never
 * passes school_id explicitly — the scope handles it. Bulk operations
 * using whereIn() only match records within the current school.
 *
 * @see App\Models\SchoolSection
 * @see App\Observers\SchoolSectionObserver    (sort_order + source tracking)
 * @see App\Support\SectionCache               (cache key management)
 * @see App\Http\Controllers\Settings\SchoolSectionController
 */
class SchoolSectionService
{
    // ──────────────────────────────────────────────────────────────────────
    // Single-Record Operations
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Create a single SchoolSection from validated form data.
     *
     * The Observer handles:
     *   - Auto-assigning sort_order when not provided
     *   - Source is always 'custom' for manually created sections
     *     (Observer does not flip on create — source default in migration
     *     is 'custom', which is correct for manual creation)
     *
     * school_id is injected by the BelongsToSchool boot hook (creating event)
     * and must NOT be in the $data array — Form Request prohibits it.
     *
     * @param  array<string, mixed>  $data  Validated data from StoreSchoolSectionRequest
     * @return SchoolSection
     *
     * @throws \Throwable
     */
    public function createOne(array $data): SchoolSection
    {
        return DB::transaction(function () use ($data): SchoolSection {
            $section = SchoolSection::create($data);

            SectionCache::forget();

            event(new SchoolSectionCreated($section));

            return $section;
        });
    }

    /**
     * Create multiple SchoolSections from config-defined template data.
     *
     * Receives the resolved templates array from StoreFromTemplatesRequest
     * (already filtered to submitted keys, canonical data from config).
     * Conflict detection was handled in the Form Request — by the time
     * this method is called, all keys are safe to create.
     *
     * Each template is created individually via SchoolSection::create()
     * rather than a bulk insert. Reasons:
     *   1. Observer hooks (sort_order auto-assign) must fire per record
     *   2. Each creation fires SchoolSectionCreated independently
     *   3. Listeners (cache invalidation, Laratrust team sync) receive
     *      the correct model instance per section
     *
     * Performance note: this is called for at most 8 templates (total
     * available). 8 individual inserts inside a transaction is negligible.
     * If this were called for hundreds of records, bulk insert would be
     * warranted — but the 8-template cap makes it unnecessary here.
     *
     * @param  array<string, array<string, mixed>>  $templates
     *         Resolved templates: ['primary' => ['name' => ..., ...], ...]
     * @return Collection<int, SchoolSection>
     *
     * @throws \Throwable
     */
    public function createFromTemplates(array $templates): Collection
    {
        return DB::transaction(function () use ($templates): Collection {
            $created = collect();

            foreach ($templates as $key => $templateData) {
                // Merge template key as the source-tracking hint.
                // Source is set to 'template' for config-originated sections.
                // The Observer will flip it to 'custom' if name/display_name/
                // short_code are later modified via update().
                $section = SchoolSection::create(array_merge($templateData, [
                    'source' => 'template',
                ]));

                $created->push($section);

                event(new SchoolSectionCreated($section));
            }

            SectionCache::forget();

            return $created;
        });
    }

    /**
     * Update a single SchoolSection with validated data.
     *
     * The Observer tracks source mutation: if the section was created from
     * a template (source = 'template') and name, display_name, or short_code
     * is changed, the Observer flips source to 'custom' automatically.
     * This service does not need to handle that logic.
     *
     * @param  SchoolSection         $section
     * @param  array<string, mixed>  $data     Validated data from UpdateSchoolSectionRequest
     * @return SchoolSection
     *
     * @throws \Throwable
     */
    public function update(SchoolSection $section, array $data): SchoolSection
    {
        return DB::transaction(function () use ($section, $data): SchoolSection {
            $section->update($data);

            SectionCache::forget();

            event(new SchoolSectionUpdated($section));

            return $section->refresh();
        });
    }

    // ──────────────────────────────────────────────────────────────────────
    // Bulk State-Change Operations
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Soft-delete one or more sections by ID.
     *
     * Business rule: a section with active class levels or enrolled students
     * cannot be deleted. This protects referential integrity beyond what the
     * DB restrictOnDelete constraint covers — the DB prevents school deletion
     * with sections, but this service prevents section deletion with active
     * children at the application layer.
     *
     * Bulk soft-delete uses each() + delete() rather than a mass whereIn
     * delete to ensure:
     *   1. Model events (deleting, deleted) fire on each instance
     *   2. SoftDeletes trait sets deleted_at correctly per model
     *   3. Each deletion can be individually rolled back if one fails
     *
     * BelongsToSchool scope on the whereIn query ensures IDs from other
     * schools are silently ignored — cross-tenant safety at query level.
     *
     * @param  array<int, string>  $ids  UUID strings
     * @return int  Number of sections actually deleted
     *
     * @throws ValidationException  If any section has active dependencies
     * @throws \Throwable
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $sections = SchoolSection::whereIn('id', $ids)->get();

            // Business rule: block deletion of sections with active children
            $this->assertNoActiveDependencies($sections, 'delete');

            $deleted = 0;

            foreach ($sections as $section) {
                $section->delete();
                $deleted++;
            }

            if ($deleted > 0) {
                SectionCache::forget();
                event(new SchoolSectionDeleted($sections));
            }

            return $deleted;
        });
    }

    /**
     * Restore one or more soft-deleted sections by ID.
     *
     * Uses onlyTrashed() to ensure only deleted records are targeted.
     * Non-deleted IDs in the submitted array are silently ignored —
     * the count returned reflects only actually-restored records.
     *
     * Each model is restored individually to ensure model events fire.
     *
     * @param  array<int, string>  $ids  UUID strings
     * @return int  Number of sections actually restored
     *
     * @throws \Throwable
     */
    public function bulkRestore(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $sections = SchoolSection::onlyTrashed()
                ->whereIn('id', $ids)
                ->get();

            $restored = 0;

            $sections->each(function (SchoolSection $section) use (&$restored) {
                $section->restore();
                $restored++;
            });

            if ($restored > 0) {
                SectionCache::forget();
                event(new SchoolSectionRestored($sections));
            }

            return $restored;
        });
    }

    /**
     * Permanently delete one or more soft-deleted sections.
     *
     * Force-delete is restricted to already-soft-deleted records only.
     * Attempting to force-delete an active section is blocked — the correct
     * flow is soft-delete first, then force-delete. This two-step process
     * prevents accidental permanent deletion.
     *
     * Business rule: all enrolled students and class levels must have been
     * reassigned before force-delete is possible. The DB restrictOnDelete
     * constraint on school_id will throw a QueryException if children
     * exist — we catch this and surface a user-friendly ValidationException.
     *
     * @param  array<int, string>  $ids  UUID strings
     * @return int  Number of sections permanently deleted
     *
     * @throws ValidationException  If section has children or is not soft-deleted
     * @throws \Throwable
     */
    public function bulkForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $sections = SchoolSection::onlyTrashed()
                ->whereIn('id', $ids)
                ->get();

            if ($sections->isEmpty()) {
                throw ValidationException::withMessages([
                    'ids' => 'No soft-deleted sections were found for the provided IDs. '
                           . 'Only soft-deleted sections can be permanently deleted.',
                ]);
            }

            $deleted = 0;

            $sections->each(function (SchoolSection $section) use (&$deleted) {
                try {
                    $section->forceDelete();
                    $deleted++;
                } catch (\Illuminate\Database\QueryException $e) {
                    // DB constraint violation — section still has child records
                    Log::warning('Force delete blocked by DB constraint', [
                        'section_id'   => $section->id,
                        'section_name' => $section->name,
                        'error'        => $e->getMessage(),
                    ]);

                    throw ValidationException::withMessages([
                        'ids' => "The \"{$section->name}\" section cannot be permanently deleted "
                               . 'because it still has associated records (class levels, students, or staff). '
                               . 'Reassign or remove those records first.',
                    ]);
                }
            });

            if ($deleted > 0) {
                SectionCache::forget();
            }

            return $deleted;
        });
    }

    /**
     * Activate or deactivate one or more sections.
     *
     * Business rule: a section cannot be deactivated while it has enrolled
     * students with no alternative active section. This check is intentionally
     * pragmatic — it warns when deactivating sections that have any enrolled
     * students, leaving the admin to confirm via the UI before proceeding.
     *
     * Only fires SchoolSectionDeactivated event when is_active = false,
     * as activation is a routine operation that does not require cascading
     * logic in listeners (e.g. notifying admin, suspending enrollments).
     *
     * Uses Eloquent's update() on a Collection query for a single UPDATE
     * statement rather than per-model updates — appropriate here because
     * we don't need model events per row, only the aggregate event.
     *
     * @param  array<int, string>  $ids       UUID strings
     * @param  bool                $isActive  true = activate, false = deactivate
     * @return int  Number of sections actually updated
     *
     * @throws ValidationException  If deactivating sections with enrolled students
     * @throws \Throwable
     */
    public function bulkToggleStatus(array $ids, bool $isActive): int
    {
        return DB::transaction(function () use ($ids, $isActive): int {
            $sections = SchoolSection::whereIn('id', $ids)->get();

            // Business rule: block deactivation when students are enrolled
            if (! $isActive) {
                $this->assertNoEnrolledStudents($sections, 'deactivate');
            }

            $affected = SchoolSection::whereIn('id', $sections->pluck('id'))
                ->update(['is_active' => $isActive]);

            if ($affected > 0) {
                SectionCache::forget();

                if (! $isActive) {
                    event(new SchoolSectionDeactivated($sections));
                }
            }

            return $affected;
        });
    }

    /**
     * Reorder sections by assigning new sort_order values.
     *
     * The frontend submits an ordered array of IDs reflecting the desired
     * display order. This method assigns sort_order = (position + 1) * 10
     * to each ID, giving a clean 10-gap sequence (10, 20, 30...).
     *
     * The 10-gap convention matches the Observer's auto-assign strategy
     * (max + 10) and leaves room for future insertions between positions
     * without requiring a full reorder.
     *
     * Uses individual updates per model rather than a CASE WHEN bulk
     * update for clarity and Observer compatibility. With a max of 8
     * sections per school, the performance difference is irrelevant.
     *
     * Observer source-flip logic does NOT trigger on sort_order changes —
     * confirmed in Observer spec (sort_order is not in SOURCE_FLIP_FIELDS).
     *
     * @param  array<int, string>  $orderedIds  UUIDs in desired display order
     * @return int  Number of sections updated
     *
     * @throws \Throwable
     */
    public function reorder(array $orderedIds): int
    {
        return DB::transaction(function () use ($orderedIds): int {
            $updated = 0;

            foreach ($orderedIds as $position => $id) {
                $newSortOrder = ($position + 1) * 10;

                $rows = SchoolSection::where('id', $id)
                    ->update(['sort_order' => $newSortOrder]);

                $updated += $rows;
            }

            if ($updated > 0) {
                SectionCache::forget();
            }

            return $updated;
        });
    }

    // ──────────────────────────────────────────────────────────────────────
    // Business Rule Guards (private)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Assert that none of the given sections have active dependencies
     * that would make the operation unsafe.
     *
     * Currently checks for:
     *   - Active class levels linked to the section
     *   - Enrolled (active) students
     *
     * If any section has dependencies, throws ValidationException listing
     * the section names that blocked the operation. The error is keyed to
     * 'ids' to surface cleanly in the frontend's bulk action error handler.
     *
     * @param  Collection<int, SchoolSection>  $sections
     * @param  string                          $operation  For error message context
     *
     * @throws ValidationException
     */
    private function assertNoActiveDependencies(Collection $sections, string $operation): void
    {
        $blocked = $sections->filter(function (SchoolSection $section) {
            return $section->classLevels()->exists()
                || $section->students()->exists();
        });

        if ($blocked->isEmpty()) {
            return;
        }

        $names = $blocked->pluck('name')->join(', ', ' and ');

        throw ValidationException::withMessages([
            'ids' => "The following sections cannot be {$operation}d because they have "
                   . "associated class levels or enrolled students: {$names}. "
                   . 'Reassign or remove those records first.',
        ]);
    }

    /**
     * Assert that none of the given sections have enrolled students.
     *
     * Used specifically for deactivation — softer check than full dependency
     * check used for deletion. Deactivation with class levels is allowed
     * (class levels can exist in inactive sections), but deactivation with
     * enrolled students risks orphaning active enrollments.
     *
     * @param  Collection<int, SchoolSection>  $sections
     * @param  string                          $operation
     *
     * @throws ValidationException
     */
    private function assertNoEnrolledStudents(Collection $sections, string $operation): void
    {
        $blocked = $sections->filter(
            fn(SchoolSection $section) => $section->students()->exists()
        );

        if ($blocked->isEmpty()) {
            return;
        }

        $names = $blocked->pluck('name')->join(', ', ' and ');

        throw ValidationException::withMessages([
            'ids' => "The following sections cannot be {$operation}d because they have "
                   . "enrolled students: {$names}. "
                   . 'Reassign those students to another section first.',
        ]);
    }
}
