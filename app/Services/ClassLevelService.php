<?php

/**
 * ClassLevelService
 *
 * Central business logic for all ClassLevel operations.
 *
 * Responsibilities:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Create, update, soft-delete, and restore class levels
 * - Enforce business rules that belong in the service layer (not requests/models):
 *     • Cannot deactivate a level that has enrolled students
 *     • Cannot delete a level that has class sections (streams) attached
 *     • Sequence conflict resolution on update (shift surrounding levels)
 * - Bulk generate levels from a preset (onboarding flow)
 * - Reorder sequences after a bulk-generate or manual reorder
 *
 * What this service does NOT do:
 * ─────────────────────────────────────────────────────────────────────────────
 * - HTTP concerns (handled in controller)
 * - Validation (handled in FormRequests)
 * - Response shaping (handled in ClassLevelResource)
 *
 * Fits into the module:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Called exclusively by ClassLevelController
 * - Uses ClassLevelPresets for bulk generation data
 * - Dispatches activity log events via the model's LogsActivity trait (automatic)
 * - All write operations are wrapped in DB transactions for atomicity
 */

namespace App\Services;

use App\Models\Academic\ClassLevel;
use App\Models\SchoolSection;
use App\Support\ClassLevelPresets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClassLevelService
{
    // ─── Create ───────────────────────────────────────────────────────────────

    /**
     * Create a new class level within a section.
     *
     * The sequence uniqueness has already been validated by StoreClassLevelRequest.
     * This method focuses on creation and post-creation consistency checks.
     *
     * @param  SchoolSection $section  The owning section (from route model binding)
     * @param  array         $data     Validated data from StoreClassLevelRequest
     * @return ClassLevel
     * @throws Throwable
     */
    public function create(SchoolSection $section, array $data): ClassLevel
    {
        return DB::transaction(function () use ($section, $data) {
            $classLevel = $section->classLevels()->create([
                'name' => $data['name'],
                'display_name' => $data['display_name'] ?? null,
                'alias' => $data['alias'] ?? null,
                'description' => $data['description'] ?? null,
                'sequence' => $data['sequence'],
                'max_arms' => $data['max_arms'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            Log::info('ClassLevel created', [
                'id' => $classLevel->id,
                'name' => $classLevel->name,
                'section_id' => $section->id,
                'sequence' => $classLevel->sequence,
            ]);

            return $classLevel;
        });
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    /**
     * Update an existing class level.
     *
     * Business rules enforced here (not in the request):
     * - Cannot deactivate a level that currently has enrolled students.
     *   The request allows is_active = false to pass through, but the service
     *   rejects it if students are assigned. This keeps the request lean and
     *   lets the service be the single authority on this rule.
     *
     * - Sequence change: if the new sequence conflicts with an existing level
     *   (which the unique DB constraint would catch), the request already
     *   rejects it. But if admin is doing a bulk reorder via the reorder
     *   endpoint, use reorderSequences() instead.
     *
     * @param  ClassLevel $classLevel  The level to update
     * @param  array      $data        Validated data from UpdateClassLevelRequest
     * @return ClassLevel
     * @throws ValidationException
     * @throws Throwable
     */
    public function update(ClassLevel $classLevel, array $data): ClassLevel
    {
        // Guard: cannot deactivate if students are currently enrolled
        if (isset($data['is_active']) && $data['is_active'] === false) {
            $this->guardAgainstActiveStudents($classLevel, 'deactivate');
        }

        return DB::transaction(function () use ($classLevel, $data) {
            $classLevel->update(array_filter([
                'name' => $data['name'] ?? $classLevel->name,
                'display_name' => array_key_exists('display_name', $data)
                    ? $data['display_name']
                    : $classLevel->display_name,
                'alias' => array_key_exists('alias', $data)
                    ? $data['alias']
                    : $classLevel->alias,
                'description' => array_key_exists('description', $data)
                    ? $data['description']
                    : $classLevel->description,
                'sequence' => $data['sequence'] ?? $classLevel->sequence,
                'max_arms' => array_key_exists('max_arms', $data)
                    ? $data['max_arms']
                    : $classLevel->max_arms,
                'is_active' => $data['is_active'] ?? $classLevel->is_active,
            ], fn($value) => $value !== null || $value === false));

            return $classLevel->fresh();
        });
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    /**
     * Soft-delete one or more class levels.
     *
     * Business rules:
     * - Cannot delete a level that has class sections (streams) attached.
     *   Admin must first delete or reassign those streams.
     * - Cannot delete a level that has students directly assigned.
     * - Bulk delete stops at the first violation and reports which level failed.
     *
     * @param  array $ids  Array of ClassLevel UUIDs to delete
     * @return int          Number of levels successfully deleted
     * @throws ValidationException
     */
    public function delete(array $ids): int
    {
        $levels = ClassLevel::whereIn('id', $ids)->get();
        $deleted = 0;

        DB::transaction(function () use ($levels, &$deleted) {
            foreach ($levels as $level) {
                // Guard: cannot delete if streams exist under this level
                $this->guardAgainstClassSections($level);

                // Guard: cannot delete if students are enrolled
                $this->guardAgainstActiveStudents($level, 'delete');

                $level->delete();
                $deleted++;

                Log::info('ClassLevel soft-deleted', [
                    'id' => $level->id,
                    'name' => $level->name,
                ]);
            }
        });

        return $deleted;
    }

    /**
     * Restore one or more soft-deleted class levels.
     *
     * Checks that the name and sequence are still available in the section
     * before restoring — another level may have been created with the same
     * name/sequence while this one was in the trash.
     *
     * @param  array $ids  Array of ClassLevel UUIDs to restore
     * @return int          Number of levels restored
     * @throws ValidationException
     */
    public function restore(array $ids): int
    {
        $levels = ClassLevel::onlyTrashed()->whereIn('id', $ids)->get();
        $restored = 0;

        DB::transaction(function () use ($levels, &$restored) {
            $levels->each(function (ClassLevel $level) use (&$restored) {
                // Check name is still available in the section
                $nameConflict = ClassLevel::where('school_section_id', $level->school_section_id)
                    ->where('name', $level->name)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($nameConflict) {
                    throw ValidationException::withMessages([
                        'name' => "Cannot restore \"{$level->name}\" — another level with this name already exists in this section.",
                    ]);
                }

                // Check sequence is still available
                $sequenceConflict = ClassLevel::where('school_section_id', $level->school_section_id)
                    ->where('sequence', $level->sequence)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($sequenceConflict) {
                    throw ValidationException::withMessages([
                        'sequence' => "Cannot restore \"{$level->name}\" — sequence position {$level->sequence} is already occupied in this section.",
                    ]);
                }

                $level->restore();
                $restored++;

                Log::info('ClassLevel restored', [
                    'id' => $level->id,
                    'name' => $level->name,
                ]);
            });
        });

        return $restored;
    }

    // Add this method to ClassLevelService

    /**
     * Permanently delete a collection of soft-deleted class levels.
     *
     * Only operates on already-trashed records — the controller passes
     * a collection retrieved via onlyTrashed() so this is guaranteed.
     *
     * Still enforces the same guards as soft-delete: cannot force-delete
     * a level that has class sections or students attached, because orphaned
     * records would break timetables, results, and promotion history.
     *
     * @param  \Illuminate\Database\Eloquent\Collection $levels
     * @return int  Number of records permanently deleted
     * @throws ValidationException
     */
    public function forceDelete(\Illuminate\Database\Eloquent\Collection $levels): int
    {
        $deleted = 0;

        DB::transaction(function () use ($levels, &$deleted) {
            foreach ($levels as $level) {
                // Same guards as soft-delete — orphaned relations break other modules
                $this->guardAgainstClassSections($level);
                $this->guardAgainstActiveStudents($level, 'permanently delete');

                $level->forceDelete();
                $deleted++;

                Log::info('ClassLevel permanently deleted', [
                    'id' => $level->id,
                    'name' => $level->name,
                    'section_id' => $level->school_section_id,
                ]);
            }
        });

        return $deleted;
    }

    // ─── Bulk Generate ────────────────────────────────────────────────────────

    /**
     * Generate class levels from a preset variant.
     *
     * Called during onboarding after admin selects a preset from the cascade
     * select in BulkGenerateModal.vue. Also available any time from the
     * section's class levels tab.
     *
     * Behaviour:
     * - Skips levels whose name already exists in the section (idempotent).
     * - Assigns sequences starting from max(existing sequence) + 1 so new
     *   levels append after any manually created ones.
     * - Returns a summary so the frontend can show "5 created, 2 skipped".
     *
     * @param  SchoolSection $section     The owning section
     * @param  string        $presetKey   e.g. 'nigerian.primary.p1-6'
     * @return array{created: int, skipped: int, levels: Collection}
     * @throws ValidationException
     */
    public function bulkGenerate(SchoolSection $section, string $presetKey): array
    {
        // Resolve preset data
        $preset = ClassLevelPresets::resolve($presetKey);

        if (!$preset) {
            throw ValidationException::withMessages([
                'preset' => "Unknown preset key: \"{$presetKey}\".",
            ]);
        }

        // Get existing names and highest sequence in this section
        $existing = ClassLevel::where('school_section_id', $section->id)
            ->withTrashed() // include soft-deleted so we don't reuse their names
            ->pluck('name')
            ->map(fn($n) => strtolower(trim($n)))
            ->toArray();

        $maxSequence = ClassLevel::where('school_section_id', $section->id)
            ->withTrashed()
            ->max('sequence') ?? 0;

        $created = 0;
        $skipped = 0;
        $newLevels = new Collection();

        DB::transaction(function () use ($section, $preset, $existing, $maxSequence, &$created, &$skipped, &$newLevels) {
            foreach ($preset['levels'] as $index => $levelName) {
                // Skip if name already exists (case-insensitive)
                if (in_array(strtolower(trim($levelName)), $existing, true)) {
                    $skipped++;
                    continue;
                }

                $sequence = $maxSequence + $index + 1;

                $level = $section->classLevels()->create([
                    'name' => $levelName,
                    'sequence' => $sequence,
                    'is_active' => true,
                ]);

                $newLevels->push($level);
                $created++;
            }
        });

        Log::info('ClassLevel bulk generate completed', [
            'section_id' => $section->id,
            'preset' => $presetKey,
            'created' => $created,
            'skipped' => $skipped,
        ]);

        return [
            'created' => $created,
            'skipped' => $skipped,
            'levels' => $newLevels,
        ];
    }

    // ─── Reorder ──────────────────────────────────────────────────────────────

    /**
     * Bulk-update sequences for all levels in a section.
     *
     * Called when admin drags to reorder or manually edits sequences.
     * Accepts an ordered array of IDs — sequences are assigned 1, 2, 3...
     * in the order the IDs appear in the array.
     *
     * All IDs must belong to the same section — the controller validates this
     * before calling the service.
     *
     * @param  SchoolSection $section     The owning section
     * @param  array         $orderedIds  Class level IDs in desired sequence order
     * @return void
     * @throws ValidationException
     */
    public function reorderSequences(SchoolSection $section, array $orderedIds): void
    {
        // Verify all IDs belong to this section
        $count = ClassLevel::where('school_section_id', $section->id)
            ->whereIn('id', $orderedIds)
            ->count();

        if ($count !== count($orderedIds)) {
            throw ValidationException::withMessages([
                'ids' => 'One or more class levels do not belong to this section.',
            ]);
        }

        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $sequence => $id) {
                ClassLevel::where('id', $id)->update([
                    'sequence' => $sequence + 1, // 1-based
                ]);
            }
        });

        Log::info('ClassLevel sequences reordered', [
            'section_id' => $section->id,
            'order' => $orderedIds,
        ]);
    }

    // ─── Private guards ───────────────────────────────────────────────────────

    /**
     * Throw a validation exception if the level has class sections (streams) attached.
     * Streams must be removed before the level can be deleted.
     *
     * @throws ValidationException
     */
    private function guardAgainstClassSections(ClassLevel $level): void
    {
        $count = $level->classSections()->count();

        if ($count > 0) {
            throw ValidationException::withMessages([
                'class_level' => "Cannot delete \"{$level->name}\" — it has {$count} classroom(s) attached. Remove them first.",
            ]);
        }
    }

    /**
     * Throw a validation exception if the level has students currently enrolled.
     * Used to guard both deletion and deactivation.
     *
     * @param  string $action  'delete' or 'deactivate' — used in the error message
     * @throws ValidationException
     */
    private function guardAgainstActiveStudents(ClassLevel $level, string $action): void
    {
        // Students are linked via ClassSection → Student, not directly to ClassLevel.
        // Until ClassSection module exists, we check via classSections relationship.
        // When the student module is built, replace this with a direct count.
        $studentCount = $level->classSections()
            ->withCount('students')
            ->get()
            ->sum('students_count');

        if ($studentCount > 0) {
            throw ValidationException::withMessages([
                'class_level' => "Cannot {$action} \"{$level->name}\" — {$studentCount} student(s) are currently enrolled.",
            ]);
        }
    }
}
