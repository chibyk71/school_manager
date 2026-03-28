<?php

namespace App\Services;

use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\Academic\TeacherClassSectionSubject;
use App\Models\Employee\Staff;
use App\Models\Subject;
use App\Support\ClassSectionNamePresets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * ClassSectionService — Central business logic for the ClassSection module.
 *
 * ── Responsibilities ──────────────────────────────────────────────────────────
 * This service owns ALL write operations for class sections:
 *
 *   createOne()                  Create a single section (manual)
 *   update()                     Update name, room, capacity, status
 *   bulkDelete()                 Soft-delete one or more sections
 *   bulkRestore()                Restore soft-deleted sections
 *   bulkForceDelete()            Permanently delete trashed sections
 *   bulkToggleStatus()           Activate or deactivate sections
 *   reorder()                    Reassign sort_order from positional array
 *
 *   bulkGenerate()               Generate arms across one OR multiple class levels
 *                                (the combined Option A + B workflow)
 *
 *   assignFormTeacher()          Set or clear the form teacher for a section
 *   assignSubject()              Assign a teacher to a subject in a section
 *   removeSubjectAssignment()    Remove a teacher-subject assignment
 *   updateSubjectAssignment()    Change role on an existing assignment
 *
 * ── What This Service Does NOT Do ────────────────────────────────────────────
 * - HTTP concerns (controller handles request/response)
 * - Input validation (FormRequests handle that)
 * - Student enrollment (separate module — EnrollmentService writes the pivot)
 * - Response shaping (ClassSectionResource handles that)
 *
 * ── Authorization Boundary ───────────────────────────────────────────────────
 * Permission checks happen in FormRequests (authorize()) and controllers
 * ($this->authorize()). By the time this service is called, the action is
 * already authorized. The service enforces BUSINESS RULES only.
 *
 * ── Transaction Strategy ─────────────────────────────────────────────────────
 * Every write method is wrapped in DB::transaction(). Single-row operations
 * are also transactional because:
 *   1. Observers may write additional data (sort_order auto-assign, activity log)
 *   2. Events fired post-write should only reach listeners on committed state
 *   3. "Always transactional" is simpler to reason about than selective wrapping
 *
 * ── Return Values ─────────────────────────────────────────────────────────────
 * - Create/update methods return the affected model (fresh with relations)
 * - Bulk state-change methods return int (affected row count)
 * - bulkGenerate() returns a structured summary array
 *
 * ── Business Rule Failures ────────────────────────────────────────────────────
 * Domain rule violations throw ValidationException with user-friendly messages.
 * This integrates automatically with Laravel's exception handler (422 response)
 * and the frontend's existing error handling pattern.
 *
 * ── Naming Logic ──────────────────────────────────────────────────────────────
 * `name`         — The arm label: "A", "B", "Diamond"
 * `display_name` — Computed once at creation: "JSS 1A", "Primary 2 Diamond"
 *                  Formula: classLevel.name + " " + armLabel
 *                  Stored in DB for performance; overridable by admin.
 */
class ClassSectionService
{
    // ──────────────────────────────────────────────────────────────────────────
    // Single-Record CRUD
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create a single class section manually.
     *
     * Automatically computes and stores display_name from the parent class level
     * unless admin provides one explicitly. Assigns sort_order as
     * (current max + 10) within the class level for consistent 10-gap ordering.
     *
     * @param  ClassLevel  $classLevel  The parent class level (from route model binding)
     * @param  array       $data        Validated from StoreClassSectionRequest
     * @return ClassSection
     * @throws Throwable
     */
    public function createOne(ClassLevel $classLevel, array $data): ClassSection
    {
        return DB::transaction(function () use ($classLevel, $data): ClassSection {
            // Auto-assign sort_order if not provided
            $sortOrder = $data['sort_order'] ?? $this->nextSortOrder($classLevel->id);

            // Compute display_name if not provided
            $displayName = $data['display_name'] ?? $this->buildDisplayName(
                $classLevel->name,
                $data['name']
            );

            $section = ClassSection::create([
                'class_level_id' => $classLevel->id,
                'name'           => $data['name'],
                'display_name'   => $displayName,
                'room'           => $data['room'] ?? null,
                'capacity'       => $data['capacity'] ?? 0,
                'form_teacher_id'=> $data['form_teacher_id'] ?? null,
                'sort_order'     => $sortOrder,
                'status'         => $data['status'] ?? 'active',
            ]);

            Log::info('ClassSection created', [
                'id'             => $section->id,
                'display_name'   => $section->display_name,
                'class_level_id' => $classLevel->id,
            ]);

            return $section->load('classLevel', 'formTeacher');
        });
    }

    /**
     * Update a class section.
     *
     * Business rules enforced here:
     * - Cannot deactivate a section that has currently enrolled students.
     *   (Deactivation would prevent new attendance/result entries but existing
     *    enrollments would be left in a broken state.)
     * - If name changes, display_name is recomputed UNLESS admin has
     *   explicitly set a custom display_name (detected by comparing current
     *   display_name to the auto-computed value before the change).
     *
     * @param  ClassSection  $section  The section to update
     * @param  array         $data     Validated from UpdateClassSectionRequest
     * @return ClassSection
     * @throws ValidationException
     * @throws Throwable
     */
    public function update(ClassSection $section, array $data): ClassSection
    {
        // Guard: cannot deactivate a section with enrolled students
        if (isset($data['status']) && $data['status'] === 'inactive') {
            $this->guardAgainstEnrolledStudents($section, 'deactivate');
        }

        return DB::transaction(function () use ($section, $data): ClassSection {
            // If name changed, recompute display_name — but only if admin
            // hasn't provided their own custom display_name in this update
            // AND the current display_name looks auto-generated (matches the formula)
            $nameChanged = isset($data['name']) && $data['name'] !== $section->name;
            $adminOverridesDisplayName = isset($data['display_name']);

            if ($nameChanged && !$adminOverridesDisplayName) {
                $section->load('classLevel');
                $currentAutoComputed = $this->buildDisplayName(
                    $section->classLevel->name,
                    $section->name
                );

                // Only auto-update display_name if it currently matches the formula
                // (i.e., admin hasn't manually customised it before)
                $currentIsAutoComputed = $section->display_name === $currentAutoComputed
                    || $section->display_name === null;

                if ($currentIsAutoComputed) {
                    $data['display_name'] = $this->buildDisplayName(
                        $section->classLevel->name,
                        $data['name']
                    );
                }
            }

            $section->update(array_filter(
                $data,
                fn ($v) => $v !== null || $v === 0 || $v === '' || $v === false
            ));

            Log::info('ClassSection updated', [
                'id'           => $section->id,
                'display_name' => $section->display_name,
                'changes'      => array_keys($data),
            ]);

            return $section->fresh(['classLevel', 'formTeacher']);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Bulk State-Change Operations
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Soft-delete one or more sections.
     *
     * Business rules:
     * - Cannot delete a section that has currently enrolled students.
     *   Admin must transfer or withdraw students first.
     * - Cannot delete a section referenced by active timetable entries.
     *   (Timetable check is a placeholder — uncomment when timetable module exists.)
     *
     * Uses individual model deletes (not mass delete) so that:
     * - Model events fire on each instance (cascade to observers/listeners)
     * - SoftDeletes sets deleted_at correctly per model
     * - Activity log records each deletion individually
     *
     * @param  array  $ids  UUID strings
     * @return int    Number of sections actually deleted
     * @throws ValidationException
     * @throws Throwable
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $sections = ClassSection::whereIn('id', $ids)->get();

            foreach ($sections as $section) {
                $this->guardAgainstEnrolledStudents($section, 'delete');
                // TODO: $this->guardAgainstActiveTimetableEntries($section);
            }

            $deleted = 0;
            foreach ($sections as $section) {
                $section->delete();
                $deleted++;
            }

            return $deleted;
        });
    }

    /**
     * Restore one or more soft-deleted sections.
     *
     * Checks that the name is still available within the class level before
     * restoring — another section may have been created with the same name
     * while this one was in the trash.
     *
     * @param  array  $ids  UUID strings
     * @return int    Number of sections restored
     * @throws ValidationException
     * @throws Throwable
     */
    public function bulkRestore(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $sections = ClassSection::onlyTrashed()->whereIn('id', $ids)->get();

            $sections->each(function (ClassSection $section) {
                $conflict = ClassSection::where('class_level_id', $section->class_level_id)
                    ->where('name', $section->name)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'ids' => "Cannot restore \"{$section->display_name_computed}\" — another section " .
                                 "with the name \"{$section->name}\" already exists in this class level.",
                    ]);
                }
            });

            $restored = 0;
            $sections->each(function (ClassSection $section) use (&$restored) {
                $section->restore();
                $restored++;
            });

            return $restored;
        });
    }

    /**
     * Permanently delete one or more soft-deleted sections.
     *
     * Only operates on already-trashed records. Enforces the same student guard
     * as soft-delete — orphaned enrollment records would break results/attendance.
     *
     * @param  array  $ids  UUID strings
     * @return int    Number permanently deleted
     * @throws ValidationException
     * @throws Throwable
     */
    public function bulkForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $sections = ClassSection::onlyTrashed()->whereIn('id', $ids)->get();

            if ($sections->isEmpty()) {
                throw ValidationException::withMessages([
                    'ids' => 'No trashed sections found for the provided IDs. ' .
                             'Only soft-deleted sections can be permanently deleted.',
                ]);
            }

            $deleted = 0;
            $sections->each(function (ClassSection $section) use (&$deleted) {
                // Even for force-delete, guard against enrolled students
                // (historical pivot rows are fine; is_current = true rows are not)
                $this->guardAgainstEnrolledStudents($section, 'permanently delete');

                try {
                    $section->forceDelete();
                    $deleted++;
                } catch (\Illuminate\Database\QueryException $e) {
                    Log::warning('ClassSection force delete blocked by DB constraint', [
                        'section_id'   => $section->id,
                        'display_name' => $section->display_name_computed,
                        'error'        => $e->getMessage(),
                    ]);

                    throw ValidationException::withMessages([
                        'ids' => "Cannot permanently delete \"{$section->display_name_computed}\" — " .
                                 "it still has associated records. Remove them first.",
                    ]);
                }
            });

            return $deleted;
        });
    }

    /**
     * Activate or deactivate one or more sections.
     *
     * Deactivation guard: cannot deactivate sections with currently enrolled students.
     * Uses a single UPDATE statement for the status change (no model events needed
     * for bulk toggle — only the aggregate count matters here).
     *
     * @param  array  $ids       UUID strings
     * @param  bool   $isActive  true = activate, false = deactivate
     * @return int    Number of sections updated
     * @throws ValidationException
     * @throws Throwable
     */
    public function bulkToggleStatus(array $ids, bool $isActive): int
    {
        return DB::transaction(function () use ($ids, $isActive): int {
            $sections = ClassSection::whereIn('id', $ids)->get();

            if (!$isActive) {
                foreach ($sections as $section) {
                    $this->guardAgainstEnrolledStudents($section, 'deactivate');
                }
            }

            $affected = ClassSection::whereIn('id', $sections->pluck('id'))
                ->update(['status' => $isActive ? 'active' : 'inactive']);

            return $affected;
        });
    }

    /**
     * Reorder sections by assigning new sort_order values.
     *
     * Accepts an ordered array of IDs — sort_order is assigned as
     * (position + 1) * 10 so the sequence is 10, 20, 30...
     * This 10-gap convention matches the pattern used across ClassLevel,
     * SchoolSection, Grade, and DynamicEnum in this codebase.
     *
     * All IDs must belong to the same school (enforced by BelongsToSchool scope).
     * They can span multiple class levels — admin may reorder globally.
     *
     * @param  array  $orderedIds  Section UUIDs in desired display order
     * @return int    Number of sections updated
     * @throws Throwable
     */
    public function reorder(array $orderedIds): int
    {
        return DB::transaction(function () use ($orderedIds): int {
            $updated = 0;

            foreach ($orderedIds as $position => $id) {
                $rows = ClassSection::where('id', $id)
                    ->update(['sort_order' => ($position + 1) * 10]);

                $updated += $rows;
            }

            return $updated;
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Bulk Generate — Combined Option A + B
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Generate arm sections across one or multiple class levels.
     *
     * This is the combined "Option A + B" workflow:
     *   Option A (single level):   $classLevelIds = ['uuid-of-jss1']
     *   Option B (multiple levels): $classLevelIds = ['uuid-jss1', 'uuid-jss2', 'uuid-jss3']
     *
     * ── How It Works ──────────────────────────────────────────────────────────
     * Given:
     *   classLevelIds = [JSS 1, JSS 2, JSS 3]
     *   arms = ['A', 'B', 'C']
     *
     * Creates:
     *   JSS 1A, JSS 1B, JSS 1C
     *   JSS 2A, JSS 2B, JSS 2C
     *   JSS 3A, JSS 3B, JSS 3C
     *
     * Skips any arm that already exists in a given level (idempotent).
     *
     * ── Arms Input ────────────────────────────────────────────────────────────
     * $arms is the resolved array of arm labels.
     * The controller resolves $namingStyle + $count (or $customNames) into
     * this flat array using ClassSectionNamePresets before calling this service.
     *
     * Examples:
     *   alphabetic, count=3  → ['A', 'B', 'C']
     *   precious, count=3    → ['Diamond', 'Gold', 'Ruby']
     *   custom               → ['Excellence', 'Honour', 'Grace']
     *
     * ── Return Value ──────────────────────────────────────────────────────────
     * Returns a structured summary per class level:
     * [
     *   'total_created' => 9,
     *   'total_skipped' => 0,
     *   'per_level' => [
     *     ['level' => 'JSS 1', 'created' => 3, 'skipped' => 0, 'sections' => [...]],
     *     ...
     *   ]
     * ]
     *
     * @param  array   $classLevelIds  One or more ClassLevel UUIDs
     * @param  array   $arms           Arm labels: ['A', 'B', 'C'] or ['Diamond', 'Gold']
     * @param  array   $defaults       Optional per-section defaults (capacity, status)
     * @return array   Summary of what was created and skipped
     * @throws ValidationException
     * @throws Throwable
     */
    public function bulkGenerate(array $classLevelIds, array $arms, array $defaults = []): array
    {
        if (empty($classLevelIds)) {
            throw ValidationException::withMessages([
                'class_level_ids' => 'At least one class level must be selected.',
            ]);
        }

        if (empty($arms)) {
            throw ValidationException::withMessages([
                'arms' => 'At least one arm name must be provided.',
            ]);
        }

        // Validate all class levels belong to the current school
        $classLevels = ClassLevel::whereIn('id', $classLevelIds)
            ->with('schoolSection') // for display in summary
            ->get();

        if ($classLevels->count() !== count($classLevelIds)) {
            throw ValidationException::withMessages([
                'class_level_ids' => 'One or more selected class levels were not found.',
            ]);
        }

        return DB::transaction(function () use ($classLevels, $arms, $defaults): array {
            $totalCreated = 0;
            $totalSkipped = 0;
            $perLevel = [];

            $classLevels->each(function (ClassLevel $classLevel) use (&$totalCreated, &$totalSkipped, &$perLevel, $arms, $defaults) {
                $result = $this->generateArmsForLevel($classLevel, $arms, $defaults);

                $totalCreated += $result['created'];
                $totalSkipped += $result['skipped'];
                $perLevel[] = $result;
            });

            Log::info('ClassSection bulk generate completed', [
                'level_count'   => $classLevels->count(),
                'arm_count'     => count($arms),
                'total_created' => $totalCreated,
                'total_skipped' => $totalSkipped,
            ]);

            return [
                'total_created' => $totalCreated,
                'total_skipped' => $totalSkipped,
                'per_level'     => $perLevel,
            ];
        });
    }

    /**
     * Generate arms for a single class level.
     * Used internally by bulkGenerate() — also callable directly from the
     * "Add Arms" button on an individual class level row (Option A workflow).
     *
     * Skips arm labels whose name already exists in the level (case-insensitive).
     * Assigns sort_order sequentially starting from the current max + 10.
     *
     * @param  ClassLevel  $classLevel  The parent level
     * @param  array       $arms        Arm labels: ['A', 'B', 'C']
     * @param  array       $defaults    Optional: capacity, status defaults
     * @return array       ['level', 'created', 'skipped', 'sections']
     */
    public function generateArmsForLevel(ClassLevel $classLevel, array $arms, array $defaults = []): array
    {
        // Get existing arm names in this level (including soft-deleted to avoid
        // name conflicts that would confuse admins on restore)
        $existingNames = ClassSection::withTrashed()
            ->where('class_level_id', $classLevel->id)
            ->pluck('name')
            ->map(fn ($n) => strtolower(trim($n)))
            ->all();

        // Starting sort_order: continue from the highest existing value
        $maxSortOrder = ClassSection::withTrashed()
            ->where('class_level_id', $classLevel->id)
            ->max('sort_order') ?? 0;

        $created = 0;
        $skipped = 0;
        $newSections = [];
        $nextSortOrder = $maxSortOrder + 10;

        foreach ($arms as $armLabel) {
            $armLabel = trim($armLabel);

            if (in_array(strtolower($armLabel), $existingNames, true)) {
                $skipped++;
                continue;
            }

            $displayName = $this->buildDisplayName($classLevel->name, $armLabel);

            $section = ClassSection::create([
                'class_level_id' => $classLevel->id,
                'name'           => $armLabel,
                'display_name'   => $displayName,
                'capacity'       => $defaults['capacity'] ?? 0,
                'status'         => $defaults['status'] ?? 'active',
                'sort_order'     => $nextSortOrder,
            ]);

            $newSections[] = $section;
            $existingNames[] = strtolower($armLabel); // prevent duplicates within same call
            $nextSortOrder += 10;
            $created++;
        }

        return [
            'level'    => $classLevel->name,
            'created'  => $created,
            'skipped'  => $skipped,
            'sections' => $newSections,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Form Teacher Assignment
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Assign or change the form teacher for a class section.
     *
     * Business rules:
     * - Teacher must belong to the same school (enforced by BelongsToSchool scope)
     * - A teacher CAN be form teacher of multiple sections (no restriction)
     *   — some schools have one teacher responsible for multiple small groups
     * - Passing null as $staffId clears the form teacher assignment
     *
     * @param  ClassSection  $section  The section to update
     * @param  string|null   $staffId  UUID of the staff member, or null to clear
     * @return ClassSection
     * @throws ValidationException
     * @throws Throwable
     */
    public function assignFormTeacher(ClassSection $section, ?string $staffId): ClassSection
    {
        if ($staffId !== null) {
            // Validate the staff member exists and belongs to this school
            $staffExists = Staff::where('id', $staffId)->exists();

            if (!$staffExists) {
                throw ValidationException::withMessages([
                    'form_teacher_id' => 'The selected staff member was not found.',
                ]);
            }
        }

        return DB::transaction(function () use ($section, $staffId): ClassSection {
            $section->update(['form_teacher_id' => $staffId]);

            Log::info('Form teacher assignment updated', [
                'section_id'       => $section->id,
                'display_name'     => $section->display_name_computed,
                'form_teacher_id'  => $staffId,
            ]);

            return $section->fresh(['classLevel', 'formTeacher']);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Subject Teacher Assignments
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Assign a teacher to teach a subject in a class section.
     *
     * Business rules:
     * - Teacher must belong to the same school
     * - Subject must belong to the same school
     * - The (teacher, section, subject) combination must not already exist
     *   (enforced by DB unique constraint, but we surface a friendly message)
     * - Multiple teachers CAN teach the same subject in the same section
     *   (they get separate rows with different roles)
     *
     * @param  ClassSection  $section   The class section
     * @param  array         $data      Validated: teacher_id, subject_id, role (nullable)
     * @return TeacherClassSectionSubject
     * @throws ValidationException
     * @throws Throwable
     */
    public function assignSubject(ClassSection $section, array $data): TeacherClassSectionSubject
    {
        // Validate teacher exists in this school
        if (!Staff::where('id', $data['teacher_id'])->exists()) {
            throw ValidationException::withMessages([
                'teacher_id' => 'The selected teacher was not found.',
            ]);
        }

        // Validate subject exists in this school
        if (!\App\Models\Academic\Subject::where('id', $data['subject_id'])->exists()) {
            throw ValidationException::withMessages([
                'subject_id' => 'The selected subject was not found.',
            ]);
        }

        // Check for duplicate assignment
        $exists = TeacherClassSectionSubject::where([
            'teacher_id'       => $data['teacher_id'],
            'class_section_id' => $section->id,
            'subject_id'       => $data['subject_id'],
        ])->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'teacher_id' => 'This teacher is already assigned to teach this subject in this section.',
            ]);
        }

        return DB::transaction(function () use ($section, $data): TeacherClassSectionSubject {
            $assignment = TeacherClassSectionSubject::create([
                'school_id'        => $section->school_id,
                'teacher_id'       => $data['teacher_id'],
                'class_section_id' => $section->id,
                'subject_id'       => $data['subject_id'],
                'role'             => $data['role'] ?? null,
            ]);

            Log::info('Teacher-subject assignment created', [
                'section_id'   => $section->id,
                'teacher_id'   => $data['teacher_id'],
                'subject_id'   => $data['subject_id'],
                'role'         => $data['role'] ?? 'null (default: subject_teacher)',
            ]);

            return $assignment->load('teacher', 'subject');
        });
    }

    /**
     * Remove a teacher-subject assignment from a section.
     *
     * Soft-deletes the record to preserve historical data (Results and Timetable
     * modules may reference past assignments).
     *
     * @param  TeacherClassSectionSubject  $assignment
     * @return bool
     * @throws Throwable
     */
    public function removeSubjectAssignment(TeacherClassSectionSubject $assignment): bool
    {
        return DB::transaction(function () use ($assignment): bool {
            $result = $assignment->delete(); // soft delete

            Log::info('Teacher-subject assignment removed', [
                'assignment_id' => $assignment->id,
                'teacher_id'    => $assignment->teacher_id,
                'subject_id'    => $assignment->subject_id,
                'section_id'    => $assignment->class_section_id,
            ]);

            return (bool) $result;
        });
    }

    /**
     * Update the role on an existing teacher-subject assignment.
     *
     * @param  TeacherClassSectionSubject  $assignment
     * @param  string|null                 $role  New role value, or null to clear
     * @return TeacherClassSectionSubject
     * @throws Throwable
     */
    public function updateSubjectAssignment(
        TeacherClassSectionSubject $assignment,
        ?string $role
    ): TeacherClassSectionSubject {
        return DB::transaction(function () use ($assignment, $role): TeacherClassSectionSubject {
            $assignment->update(['role' => $role]);

            return $assignment->fresh(['teacher', 'subject']);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Throw a ValidationException if the section currently has enrolled students.
     *
     * Checks for is_current = true rows in the pivot — historical rows (students
     * from previous sessions) are ignored. An empty section (no current students)
     * is safe to delete, deactivate, or otherwise modify.
     *
     * @param  ClassSection  $section
     * @param  string        $action  Used in the error message: 'delete', 'deactivate', etc.
     * @throws ValidationException
     */
    private function guardAgainstEnrolledStudents(ClassSection $section, string $action): void
    {
        $currentCount = $section->currentStudents()->count();

        if ($currentCount > 0) {
            throw ValidationException::withMessages([
                'ids' => "Cannot {$action} \"{$section->display_name_computed}\" — " .
                         "{$currentCount} student(s) are currently enrolled. " .
                         "Transfer or withdraw students first.",
            ]);
        }
    }

    /**
     * Compute the display name from the class level name and arm label.
     *
     * Formula: "{classLevelName} {armLabel}"
     * Trims both inputs to prevent accidental double-spaces.
     *
     * Examples:
     *   "JSS 1"    + "A"       → "JSS 1A"
     *   "Primary 2"+ "Diamond" → "Primary 2 Diamond"
     *   "SSS 3"    + "B"       → "SSS 3B"
     *
     * Note: single-character arms are concatenated without a space separator
     * because "JSS 1 A" looks wrong. Multi-word arms get a space: "Primary 2 Diamond".
     *
     * @param  string  $levelName  ClassLevel.name, e.g. "JSS 1"
     * @param  string  $armLabel   The arm name, e.g. "A" or "Diamond"
     * @return string
     */
    private function buildDisplayName(string $levelName, string $armLabel): string
    {
        $levelName = trim($levelName);
        $armLabel  = trim($armLabel);

        // Single character arms: "JSS 1" + "A" → "JSS 1A" (no space)
        if (mb_strlen($armLabel) === 1) {
            return $levelName . $armLabel;
        }

        // Multi-character arms: "Primary 2" + "Diamond" → "Primary 2 Diamond" (with space)
        return $levelName . ' ' . $armLabel;
    }

    /**
     * Get the next sort_order value for a new section within a class level.
     * Uses the 10-gap convention: finds the current max and adds 10.
     *
     * @param  string  $classLevelId  UUID
     * @return int
     */
    private function nextSortOrder(string $classLevelId): int
    {
        $max = ClassSection::withTrashed()
            ->where('class_level_id', $classLevelId)
            ->max('sort_order') ?? 0;

        return $max + 10;
    }
}
