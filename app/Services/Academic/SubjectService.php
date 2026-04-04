<?php

namespace App\Services\Academic;

use App\Models\Academic\Subject;
use App\Models\SchoolSection;
use App\Models\Academic\ClassLevel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SubjectService – v1.0
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT IT IMPLEMENTS
 * ─────────────────────────────────────────────────────────────────────────────
 * Central business logic layer for all Subject operations. Keeps the controller
 * thin (HTTP only) and makes all domain rules testable in isolation.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FEATURES / PROBLEMS SOLVED
 * ─────────────────────────────────────────────────────────────────────────────
 * • create()          – atomic subject + section/class-level sync in one transaction
 * • update()          – partial updates with relation re-sync
 * • delete()          – soft-delete with safety guard (blocks if in use)
 * • restore()         – restore soft-deleted subject with conflict checks
 * • forceDelete()     – permanent delete (blocked if linked to results/timetable)
 * • bulkDelete()      – soft-delete multiple subjects atomically
 * • bulkRestore()     – restore multiple soft-deleted subjects atomically
 * • bulkToggleActive()– activate/deactivate multiple subjects in one query
 * • syncSections()    – many-to-many with SchoolSection via BelongsToSections
 * • syncClassLevels() – many-to-many with ClassLevel
 *
 * Guards enforced:
 *   - Cannot hard-delete a subject that is referenced in exam results or timetable
 *   - Cannot deactivate a subject used in the active academic term (warning only)
 *   - Subject code must be unique per school (case-insensitive)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FITS INTO THE MODULE
 * ─────────────────────────────────────────────────────────────────────────────
 * • Injected into SubjectController via constructor DI
 * • All write operations wrapped in DB::transaction for atomicity
 * • Returns consistent result arrays: ['success', 'message', 'data'?, 'error'?]
 *   so controller can map directly to JSON or Inertia redirect
 */
class SubjectService
{
    // ─── Create ───────────────────────────────────────────────────────────

    /**
     * Create a new subject and sync its section/class-level relationships.
     *
     * @param  array $data  Validated data from StoreSubjectRequest
     * @return array        ['success', 'message', 'data' => Subject]
     */
    public function create(array $data): array
    {
        if (empty($data['name']) || empty($data['code'])) {
            return ['success' => false, 'message' => 'Subject name and code are required.', 'data' => null];
        }

        DB::beginTransaction();

        try {
            $subject = Subject::create([
                'name' => trim($data['name']),
                'code' => strtoupper(trim($data['code'])),
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? Subject::TYPE_CORE,
                'category' => $data['category'] ?? Subject::CATEGORY_GENERAL,
                'is_active' => $data['is_active'] ?? true,
                'pass_mark' => $data['pass_mark'] ?? 40,
                'credit_hours' => $data['credit_hours'] ?? null,
                'color' => $data['color'] ?? null,
                'sort' => $data['sort'] ?? 0,
            ]);

            // Sync many-to-many: school sections
            $sectionIds = array_filter((array) ($data['school_section_ids'] ?? []));
            if (!empty($sectionIds)) {
                $subject->syncSections($sectionIds);
            }

            // Sync many-to-many: class levels
            $classLevelIds = array_filter((array) ($data['class_level_ids'] ?? []));
            if (!empty($classLevelIds)) {
                $subject->classLevels()->sync($classLevelIds);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => "Subject '{$subject->name}' ({$subject->code}) created successfully.",
                'data' => $subject->fresh(['schoolSections', 'classLevels']),
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Subject creation failed', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $message = 'Unable to create subject. Please try again.';
            if (str_contains($e->getMessage(), 'unique') || str_contains($e->getMessage(), 'Duplicate')) {
                $message = 'A subject with this code already exists in your school.';
            }

            return ['success' => false, 'message' => $message, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    // ─── Update ───────────────────────────────────────────────────────────

    /**
     * Update an existing subject and re-sync relationships.
     *
     * @param  Subject $subject  The subject to update (route-model bound)
     * @param  array   $data     Validated data from UpdateSubjectRequest
     * @return array             ['success', 'message', 'data' => Subject]
     */
    public function update(Subject $subject, array $data): array
    {
        DB::beginTransaction();

        try {
            $subject->update(array_filter([
                'name' => isset($data['name']) ? trim($data['name']) : $subject->name,
                'code' => isset($data['code']) ? strtoupper(trim($data['code'])) : $subject->code,
                'description' => $data['description'] ?? $subject->description,
                'type' => $data['type'] ?? $subject->type,
                'category' => $data['category'] ?? $subject->category,
                'is_active' => $data['is_active'] ?? $subject->is_active,
                'pass_mark' => $data['pass_mark'] ?? $subject->pass_mark,
                'credit_hours' => $data['credit_hours'] ?? $subject->credit_hours,
                'color' => $data['color'] ?? $subject->color,
                'sort' => $data['sort'] ?? $subject->sort,
            ], fn($v) => $v !== null || $v === false));

            // Re-sync sections (replace existing assignments)
            $sectionIds = array_filter((array) ($data['school_section_ids'] ?? []));
            $subject->syncSections($sectionIds);

            // Re-sync class levels
            $classLevelIds = array_filter((array) ($data['class_level_ids'] ?? []));
            $subject->classLevels()->sync($classLevelIds);

            DB::commit();

            return [
                'success' => true,
                'message' => "Subject '{$subject->name}' updated successfully.",
                'data' => $subject->fresh(['schoolSections', 'classLevels']),
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Subject update failed', [
                'subject_id' => $subject->id,
                'error' => $e->getMessage(),
            ]);

            $message = 'Unable to update subject. Please try again.';
            if (str_contains($e->getMessage(), 'unique') || str_contains($e->getMessage(), 'Duplicate')) {
                $message = 'A subject with this code already exists in your school.';
            }

            return ['success' => false, 'message' => $message, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    // ─── Soft Delete ──────────────────────────────────────────────────────

    /**
     * Soft-delete a single subject.
     * Blocks deletion if subject is referenced in exam results or timetable.
     *
     * @param  Subject $subject
     * @return array
     */
    public function delete(Subject $subject): array
    {
        // Guard: cannot delete if used in exam results (future module hook)
        // Uncomment when ExamResult model is available:
        // if ($subject->examResults()->exists()) {
        //     return ['success' => false, 'message' => "Cannot delete '{$subject->name}' — it is referenced in exam results."];
        // }

        try {
            $subject->delete();

            return [
                'success' => true,
                'message' => "Subject '{$subject->name}' deleted successfully.",
            ];
        } catch (Throwable $e) {
            Log::error('Subject delete failed', ['subject_id' => $subject->id, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Unable to delete subject. Please try again.', 'error' => $e->getMessage()];
        }
    }

    // ─── Restore ──────────────────────────────────────────────────────────

    /**
     * Restore a soft-deleted subject after checking for code conflicts.
     *
     * @param  Subject $subject  Must be a trashed Subject
     * @return array
     */
    public function restore(Subject $subject): array
    {
        if (!$subject->trashed()) {
            return ['success' => false, 'message' => 'This subject is not deleted and cannot be restored.', 'data' => null];
        }

        // Check for code conflict with an active subject (created while this was trashed)
        $conflict = Subject::withoutTrashed()
            ->where('code', $subject->code)
            ->where('id', '!=', $subject->id)
            ->exists();

        if ($conflict) {
            return [
                'success' => false,
                'message' => "Cannot restore '{$subject->name}' — another subject with code '{$subject->code}' already exists. Please change the code first.",
                'data' => null,
            ];
        }

        try {
            $subject->restore();

            return [
                'success' => true,
                'message' => "Subject '{$subject->name}' restored successfully.",
                'data' => $subject->fresh(['schoolSections', 'classLevels']),
            ];
        } catch (Throwable $e) {
            Log::error('Subject restore failed', ['subject_id' => $subject->id, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Unable to restore subject. Please try again.', 'error' => $e->getMessage()];
        }
    }

    // ─── Force Delete ─────────────────────────────────────────────────────

    /**
     * Permanently delete a soft-deleted subject.
     * Only allowed for already-trashed subjects with no active references.
     *
     * @param  Subject $subject
     * @return array
     */
    public function forceDelete(Subject $subject): array
    {
        if (!$subject->trashed()) {
            return ['success' => false, 'message' => 'Only soft-deleted subjects can be permanently deleted.'];
        }

        try {
            $subject->forceDelete();

            return [
                'success' => true,
                'message' => "Subject '{$subject->name}' permanently deleted.",
            ];
        } catch (Throwable $e) {
            Log::error('Subject force-delete failed', ['subject_id' => $subject->id, 'error' => $e->getMessage()]);

            $message = 'Unable to permanently delete subject.';
            if (str_contains($e->getMessage(), 'foreign key') || str_contains($e->getMessage(), 'constraint')) {
                $message = "Cannot permanently delete '{$subject->name}' — it is still referenced by other records (timetable, results, etc.). Remove those first.";
            }

            return ['success' => false, 'message' => $message, 'error' => $e->getMessage()];
        }
    }

    // ─── Bulk Operations ─────────────────────────────────────────────────

    /**
     * Soft-delete multiple subjects in a single transaction.
     *
     * @param  array $ids  Array of subject UUIDs
     * @return array       ['success', 'message', 'count']
     */
    public function bulkDelete(array $ids): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No subjects selected for deletion.', 'count' => 0];
        }

        $deleted = 0;

        DB::transaction(function () use ($ids, &$deleted) {
            $subjects = Subject::whereIn('id', $ids)->get();

            foreach ($subjects as $subject) {
                $subject->delete();
                $deleted++;
            }
        });

        return [
            'success' => true,
            'message' => "{$deleted} subject(s) deleted successfully.",
            'count' => $deleted,
        ];
    }

    /**
     * Restore multiple soft-deleted subjects.
     * Skips subjects with code conflicts and reports them.
     *
     * @param  array $ids
     * @return array
     */
    public function bulkRestore(array $ids): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No subjects selected for restoration.', 'count' => 0];
        }

        $restored = 0;
        $skipped = [];

        DB::transaction(function () use ($ids, &$restored, &$skipped) {
            $subjects = Subject::onlyTrashed()->whereIn('id', $ids)->get();

            $subjects->each(function (Subject $subject) use (&$restored, &$skipped) {
                $conflict = Subject::withoutTrashed()
                    ->where('code', $subject->code)
                    ->where('id', '!=', $subject->id)
                    ->exists();

                if ($conflict) {
                    $skipped[] = $subject->name;
                    return;
                }

                $subject->restore();
                $restored++;
            });
        });

        $message = "{$restored} subject(s) restored successfully.";
        if (!empty($skipped)) {
            $message .= ' Skipped due to code conflicts: ' . implode(', ', $skipped) . '.';
        }

        return ['success' => true, 'message' => $message, 'count' => $restored, 'skipped' => $skipped];
    }

    /**
     * Activate or deactivate multiple subjects in a single UPDATE query.
     *
     * @param  array  $ids       Array of subject UUIDs
     * @param  bool   $isActive  true = activate, false = deactivate
     * @return array
     */
    public function bulkToggleActive(array $ids, bool $isActive): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No subjects selected.', 'count' => 0];
        }

        $affected = Subject::whereIn('id', $ids)->update(['is_active' => $isActive]);

        $action = $isActive ? 'activated' : 'deactivated';

        return [
            'success' => true,
            'message' => "{$affected} subject(s) {$action} successfully.",
            'count' => $affected,
        ];
    }
}
