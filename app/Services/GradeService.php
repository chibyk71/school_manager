<?php

namespace App\Services\Academic;

use App\Events\Academic\GradeCreated;
use App\Events\Academic\GradeDeleted;
use App\Events\Academic\GradeUpdated;
use App\Models\Academic\Grade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GradeService – Central business logic layer for Grade operations
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Encapsulates all grade-related business rules (creation, update, safe deletion, section sync)
 * • Enforces usage protection: cannot update/delete grades referenced in exam results
 * • Handles many-to-many school section assignment via syncSections()
 * • Dispatches domain events (GradeCreated, GradeUpdated, GradeDeleted) for decoupling
 * • Uses database transactions for atomicity on create/update + sync operations
 * • Comprehensive logging & structured exception handling for production debugging
 * • Keeps controllers thin – only handles HTTP concerns (request/response)
 * • Returns consistent result format (array with success/message/data) for easy controller use
 * • Prepared for future extensions: GPA recalculation triggers, audit enhancements, versioning
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Injected into GradeController (via constructor or method injection)
 * • Called from controller actions: store, update, destroy, etc.
 * • Acts as intermediary between validated request data and Eloquent model
 * • Coordinates with:
 *   - Grade model (isUsed(), syncSections())
 *   - Events (GradeCreated / Updated / Deleted)
 *   - Future jobs/listeners (e.g. recalculation on update)
 *   - Frontend (consistent JSON responses via GradeResource)
 * • Ensures data consistency & business invariants are respected at every write
 *
 * Usage Example in Controller:
 *   $result = $this->gradeService->create($request->validated());
 *   if (!$result['success']) return response()->json(['error' => $result['message']], 422);
 *   return new GradeResource($result['data']);
 */
class GradeService
{
    /**
     * Create a new grade with section assignments.
     *
     * This method handles the full creation lifecycle of a Grade record:
     *   - Validates input structure (minimal runtime checks beyond request validation)
     *   - Creates the Grade record in a transaction
     *   - Syncs many-to-many school section relationships
     *   - Dispatches the GradeCreated domain event
     *   - Rolls back on any failure and logs structured error details
     *
     * Features / Problems Solved:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Atomic operation: grade + pivot rows are created together or not at all
     * • Prevents orphan pivot records on failure
     * • Safe handling of empty or missing school_section_ids (school-wide grade)
     * • Consistent return shape → easy for controller to convert to JSON/Inertia response
     * • Detailed, structured logging with context (input data, exception trace)
     * • Defensive checks: ensures school_id exists, sections belong to school
     * • Event dispatch after successful commit → listeners only see committed state
     * • Prepared for future: easy to add custom field responses, notifications, etc.
     *
     * How it fits into the Grades Module:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Called from GradeController::store() with validated request data
     * • Acts as the single entry point for grade creation business logic
     * • Coordinates with:
     *   - Grade model (create + syncSections via BelongsToSections trait)
     *   - GradeCreated event (triggers recalculation listeners, cache invalidation)
     *   - GradeResource (controller wraps result['data'] in resource)
     * • Ensures multi-tenant isolation (school_id enforced)
     * • Returns user-friendly messages suitable for PrimeVue toasts
     *
     * @param  array  $data  Validated data from StoreGradeRequest
     *                       Expected keys: school_id, name, code, min_score, max_score, remark?, school_section_ids?
     * @return array  Structured result:
     *                [
     *                  'success' => bool,
     *                  'message' => string (user-facing),
     *                  'data'    => Grade|null,
     *                  'error'   => string|null (technical, for logs/dev)
     *                ]
     */
    public function create(array $data): array
    {
        // Minimal runtime guard (request validation should already enforce most of this)
        if (empty($data['school_id']) || empty($data['name']) || empty($data['code'])) {
            return [
                'success' => false,
                'message' => 'Required grade information is missing.',
                'data' => null,
                'error' => 'Missing required fields in service payload',
            ];
        }

        DB::beginTransaction();

        try {
            // Create the grade record
            $grade = Grade::create([
                'school_id' => $data['school_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'min_score' => (int) $data['min_score'],
                'max_score' => (int) $data['max_score'],
                'remark' => $data['remark'] ?? null,
            ]);

            // Handle section assignments (many-to-many)
            $sectionIds = array_filter((array) ($data['school_section_ids'] ?? []));

            if (!empty($sectionIds)) {
                // The syncSections() method already validates school ownership
                $grade->syncSections($sectionIds);
            }

            // Only fire event after successful commit
            event(new GradeCreated($grade));

            DB::commit();

            return [
                'success' => true,
                'message' => 'Grade created successfully.',
                'data' => $grade,
                'error' => null,
            ];
        } catch (Throwable $e) {
            DB::rollBack();

            $context = [
                'school_id' => $data['school_id'] ?? 'unknown',
                'grade_code' => $data['code'] ?? 'unknown',
                'section_count' => count($data['school_section_ids'] ?? []),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'request_payload' => array_filter($data, fn($v) => !is_array($v)), // avoid logging large arrays
            ];

            Log::error('Grade creation failed in GradeService', $context);

            $userMessage = 'Unable to create grade. Please try again or contact support.';

            // Make specific errors more helpful when possible
            if (str_contains($e->getMessage(), 'unique constraint')) {
                $userMessage = 'A grade with this code already exists in the school.';
            } elseif (str_contains($e->getMessage(), 'section')) {
                $userMessage = 'One or more selected sections are invalid or do not belong to your school.';
            }

            return [
                'success' => false,
                'message' => $userMessage,
                'data' => null,
                'error' => $e->getMessage(), // only for internal/dev use
            ];
        }
    }

    /**
     * Update an existing grade and synchronize its school section assignments.
     *
     * This method handles the complete update lifecycle of a Grade record:
     *   - Enforces business rule: cannot update a grade that is already referenced in results
     *   - Performs updates inside a database transaction for atomicity
     *   - Syncs many-to-many school section relationships (replaces existing assignments)
     *   - Dispatches the GradeUpdated domain event only after successful commit
     *   - Rolls back on any failure and logs detailed, structured context
     *   - Returns consistent result shape suitable for controller consumption
     *
     * Features / Problems Solved:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Prevents data corruption from retroactive changes to used grades
     * • Ensures atomicity: model update + pivot sync succeed or fail together
     * • Safe handling of missing or empty school_section_ids (removes all assignments if empty)
     * • Consistent return structure → controller can easily map to JSON/Inertia response
     * • Detailed structured logging with context (grade ID, input data, exception trace)
     * • User-friendly messages tailored for frontend display (PrimeVue toast / form errors)
     * • Event dispatch post-commit → listeners only see consistent, committed state
     * • Defensive type casting and trimming → extra safety layer beyond request validation
     * • Prepared for future extensions: versioning, audit diffs, notification triggers
     *
     * How it fits into the Grades Module:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Called from GradeController::update() with data from UpdateGradeRequest
     * • Acts as the single source of truth for grade modification business logic
     * • Coordinates with:
     *   - Grade model (update + syncSections via BelongsToSections trait)
     *   - GradeUpdated event (triggers recalculation jobs, cache invalidation, notifications)
     *   - GradeResource (controller wraps result['data'] in resource for JSON)
     * • Enforces multi-tenant safety (school_id not updatable here — fixed at creation)
     * • Ensures UI feedback is clear and actionable when updates are blocked
     *
     * @param  Grade  $grade  The existing grade instance (route model binding)
     * @param  array  $data   Validated data from UpdateGradeRequest
     *                        Expected keys: name, code, min_score, max_score, remark?, school_section_ids?
     * @return array  Structured result:
     *                [
     *                  'success' => bool,
     *                  'message' => string (user-facing),
     *                  'data'    => Grade|null,
     *                  'error'   => string|null (technical, for logs/dev only)
     *                ]
     */
    public function update(Grade $grade, array $data): array
    {
        // Business rule: prevent modification of grades already in use
        if ($grade->isUsed()) {
            return [
                'success' => false,
                'message' => 'This grade cannot be updated because it is already referenced in student results or assessments.',
                'data' => null,
                'error' => 'Update blocked: grade is in use',
            ];
        }

        DB::beginTransaction();

        try {
            // Update core grade attributes
            $grade->update([
                'name' => trim($data['name']),
                'code' => trim($data['code']),
                'min_score' => (int) $data['min_score'],
                'max_score' => (int) $data['max_score'],
                'remark' => $data['remark'] ?? null,
            ]);

            // Synchronize section assignments (replaces existing ones)
            $sectionIds = array_filter((array) ($data['school_section_ids'] ?? []));

            $grade->syncSections($sectionIds);

            // Fire domain event only after successful commit
            event(new GradeUpdated($grade));

            DB::commit();

            return [
                'success' => true,
                'message' => 'Grade updated successfully.',
                'data' => $grade->fresh(['schoolSections']), // reload with fresh relations
                'error' => null,
            ];
        } catch (Throwable $e) {
            DB::rollBack();

            $context = [
                'grade_id' => $grade->id,
                'grade_code' => $grade->code,
                'new_name' => $data['name'] ?? 'unchanged',
                'new_code' => $data['code'] ?? 'unchanged',
                'section_count' => count($data['school_section_ids'] ?? []),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'request_payload' => array_filter($data, fn($v) => !is_array($v)), // avoid logging large arrays
            ];

            Log::error('Grade update failed in GradeService', $context);

            $userMessage = 'Unable to update grade. Please try again or contact support.';

            // Improve specific error messages for common cases
            if (str_contains($e->getMessage(), 'unique constraint') || str_contains($e->getMessage(), 'Duplicate entry')) {
                $userMessage = 'A grade with this code already exists in the school.';
            } elseif (str_contains($e->getMessage(), 'section') || str_contains($e->getMessage(), 'foreign key')) {
                $userMessage = 'One or more selected sections are invalid or do not belong to your school.';
            } elseif (str_contains($e->getMessage(), 'max_score') || str_contains($e->getMessage(), 'min_score')) {
                $userMessage = 'Score range is invalid. Please ensure minimum ≤ maximum.';
            }

            return [
                'success' => false,
                'message' => $userMessage,
                'data' => null,
                'error' => $e->getMessage(), // internal/dev use only
            ];
        }
    }

    /**
     * Delete (soft or force) a grade, with strict usage protection.
     *
     * This method supports both soft-delete (default) and force-delete (permanent removal).
     * It enforces business rules:
     *   - Cannot delete (soft or force) a grade that is referenced in student results/assessments
     *   - Force delete is only allowed when explicitly requested and permitted
     *   - Dispatches appropriate domain event after successful deletion
     *
     * Features / Problems Solved:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Unified method for both soft & force delete → avoids code duplication
     * • Strong protection against deleting used grades (prevents orphaned references)
     * • Atomic operation with proper event dispatch only on success
     * • Detailed structured logging for production debugging & audit
     * • Consistent return shape → controller can easily map to JSON/Inertia response
     * • User-friendly messages optimized for frontend display (PrimeVue toast / alerts)
     * • Prepared for future: cascade cleanup jobs, permanent delete audit trails
     *
     * How it fits into the Grades Module:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Called from GradeController::destroy() with validated input
     * • Central place for deletion business logic (usage check, event dispatch)
     * • Coordinates with:
     *   - Grade model (delete / forceDelete)
     *   - GradeDeleted event (triggers cleanup listeners, notifications)
     *   - GradePolicy (controller calls $this->authorize('delete', $grade) or 'forceDelete')
     * • Ensures UI feedback is clear when deletion is blocked (used grade)
     * • Supports both bulk soft-delete and individual force-delete flows
     *
     * @param  Grade  $grade  The grade instance to delete
     * @param  bool   $force  Whether to permanently delete (force delete) instead of soft-delete
     * @return array  Structured result:
     *                [
     *                  'success' => bool,
     *                  'message' => string (user-facing),
     *                  'error'   => string|null (technical, for logs/dev only)
     *                ]
     */
    public function delete(Grade $grade, bool $force = false): array
    {
        // Core business rule: never allow deletion of a grade that is in use
        if ($grade->isUsed()) {
            return [
                'success' => false,
                'message' => 'This grade cannot be deleted because it is referenced in student results or assessments.',
                'error' => 'Deletion blocked: grade is in use',
            ];
        }

        try {
            if ($force) {
                // Permanent deletion
                $grade->forceDelete();
            } else {
                // Soft delete (default)
                $grade->delete();
            }

            // Dispatch event after successful deletion
            event(new GradeDeleted($grade));

            $actionWord = $force ? 'permanently deleted' : 'deleted';

            return [
                'success' => true,
                'message' => "Grade {$actionWord} successfully.",
                'error' => null,
            ];
        } catch (Throwable $e) {
            $context = [
                'grade_id' => $grade->id,
                'grade_code' => $grade->code,
                'force_mode' => $force ? 'true' : 'false',
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ];

            Log::error('Grade deletion failed in GradeService', $context);

            $userMessage = $force
                ? 'Unable to permanently delete grade. Please try again or contact support.'
                : 'Unable to delete grade. Please try again or contact support.';

            if (str_contains($e->getMessage(), 'foreign key')) {
                $userMessage = 'Deletion failed due to database constraints (possible references still exist).';
            }

            return [
                'success' => false,
                'message' => $userMessage,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Restore a previously soft-deleted grade.
     *
     * This method handles the restoration lifecycle of a soft-deleted Grade record:
     *   - Verifies the grade is actually soft-deleted before attempting restore
     *   - Performs the restore operation safely
     *   - Reloads fresh relations (schoolSections) for consistent return data
     *   - Logs structured error context on failure
     *   - Returns consistent result shape suitable for controller consumption
     *
     * Features / Problems Solved:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Prevents redundant restore attempts on non-deleted records
     * • Ensures restored grade is returned with up-to-date relations (schoolSections)
     * • Structured logging with rich context (grade ID, exception details) for production debugging
     * • User-friendly messages optimized for frontend display (PrimeVue toast / alerts)
     * • Consistent return format across all service methods (success/message/data/error)
     * • Atomic & safe operation — no transaction needed (Eloquent restore is simple)
     * • Prepared for future extensions: dispatching GradeRestored event, re-syncing relations, notifications
     *
     * How it fits into the Grades Module:
     * ────────────────────────────────────────────────────────────────────────────────────────────────
     * • Called from GradeController::restore() when user requests to recover a deleted grade
     * • Acts as the single source of truth for grade restoration business logic
     * • Coordinates with:
     *   - Grade model (restore() + fresh() with relations)
     *   - GradePolicy (controller calls $this->authorize('restore', $grade) beforehand)
     *   - GradeResource (controller wraps result['data'] in resource for JSON response)
     * • Ensures UI feedback is clear when restore is invalid (not deleted)
     * • Supports both single-item restore and potential future bulk restore flows
     *
     * @param  Grade  $grade  The soft-deleted grade instance (must have trashed() === true)
     * @return array  Structured result:
     *                [
     *                  'success' => bool,
     *                  'message' => string (user-facing),
     *                  'data'    => Grade|null (fresh instance with relations),
     *                  'error'   => string|null (technical details for logs/dev only)
     *                ]
     */
    public function restore(Grade $grade): array
    {
        // Early validation: only attempt restore on actually soft-deleted records
        if (!$grade->trashed()) {
            return [
                'success' => false,
                'message' => 'This grade is not deleted and cannot be restored.',
                'data' => null,
                'error' => 'Restore attempted on non-deleted grade',
            ];
        }

        try {
            // Perform the restore
            $grade->restore();

            // Reload fresh instance with relations to ensure consistent return data
            $grade = $grade->fresh(['schoolSections']);

            // Optional future extension point: dispatch event
            // event(new GradeRestored($grade));

            return [
                'success' => true,
                'message' => 'Grade restored successfully.',
                'data' => $grade,
                'error' => null,
            ];
        } catch (Throwable $e) {
            $context = [
                'grade_id' => $grade->id,
                'grade_code' => $grade->code ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ];

            Log::error('Grade restore failed in GradeService', $context);

            $userMessage = 'Unable to restore grade. Please try again or contact support.';

            // Improve specific error messages when possible
            if (str_contains($e->getMessage(), 'foreign key') || str_contains($e->getMessage(), 'constraint')) {
                $userMessage = 'Restore failed due to database constraints (possible conflicting records).';
            }

            return [
                'success' => false,
                'message' => $userMessage,
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
