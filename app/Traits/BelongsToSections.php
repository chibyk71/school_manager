<?php

namespace App\Traits;

use App\Models\SchoolSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Trait BelongsToSections
 *
 * Provides polymorphic many-to-many relationship between any model (e.g. Grade, Exam, Timetable, etc.)
 * and SchoolSection. Allows assigning the same entity (grade scale, exam, etc.) to multiple sections
 * without data duplication.
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Polymorphic M:M – one grade/exam can belong to many sections, one section can have many grades/exams
 * • Multi-tenant safety: only allows attaching sections from the current active school
 * • Clean attach / detach / sync methods with validation & error handling
 * • Efficient scope for filtering records by section
 * • Logging on failure for debugging in production
 * • Prepared for future extensions (e.g. pivot fields like effective_date, priority, is_default)
 *
 * How it fits into the application:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Used on models like Grade, Assessment, Exam, ReportTemplate, etc.
 * • Replaces nullable school_section_id foreign key → more flexible for shared grading scales
 * • Enables UI patterns: "Assign this grade scale to sections…" multi-select in modals
 * • Works with Inertia forms that send array of section IDs
 * • Pivot table expected: sectionables (or school_section_grade, etc.)
 *
 * Pivot table assumption:
 *   - sectionable_id (uuid)
 *   - sectionable_type (string)
 *   - school_section_id (uuid)
 *   - optional: sort_order, created_at, updated_at
 */
trait BelongsToSections
{
    /**
     * Polymorphic many-to-many relationship to SchoolSection.
     */
    public function schoolSections(): MorphToMany
    {
        return $this->morphToMany(
            SchoolSection::class,
            'sectionable',
            'sectionables',           // ← pivot table name (adjust if you use different name)
            'sectionable_id',
            'school_section_id'
        )->withTimestamps();          // optional but recommended
    }

    /**
     * Attach one or more school sections to this model instance.
     *
     * @param array|int $sectionIds
     * @return void
     * @throws Exception
     */
    public function attachSections(array|int $sectionIds): void
    {
        $sectionIds = is_array($sectionIds) ? array_unique($sectionIds) : [$sectionIds];

        if (empty($sectionIds)) {
            return;
        }

        $school = GetSchoolModel();
        if (! $school) {
            throw new Exception('No active school context found.');
        }

        // Validate all IDs belong to current school
        $validIds = SchoolSection::whereIn('id', $sectionIds)
            ->where('school_id', $school->id)
            ->pluck('id')
            ->toArray();

        if (count($validIds) !== count($sectionIds)) {
            throw new Exception('One or more section IDs are invalid or do not belong to the current school.');
        }

        try {
            $this->schoolSections()->attach($validIds);
        } catch (\Exception $e) {
            Log::error('Failed to attach school sections', [
                'model'       => get_class($this),
                'id'          => $this->id,
                'section_ids' => $sectionIds,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Detach one or more school sections from this model.
     *
     * @param array|int|null $sectionIds  null = detach all
     * @return int Number of detached relationships
     */
    public function detachSections(array|int|null $sectionIds = null): int
    {
        if (is_null($sectionIds)) {
            return $this->schoolSections()->detach();
        }

        $sectionIds = is_array($sectionIds) ? $sectionIds : [$sectionIds];

        try {
            return $this->schoolSections()->detach($sectionIds);
        } catch (\Exception $e) {
            Log::error('Failed to detach school sections', [
                'model'       => get_class($this),
                'id'          => $this->id,
                'section_ids' => $sectionIds,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Sync school sections – replace current assignments with the provided list.
     *
     * @param array $sectionIds
     * @return array  synced IDs
     * @throws Exception
     */
    public function syncSections(array $sectionIds): array
    {
        $sectionIds = array_unique($sectionIds);

        $school = GetSchoolModel();
        if (! $school) {
            throw new Exception('No active school context found.');
        }

        $validIds = SchoolSection::whereIn('id', $sectionIds)
            ->where('school_id', $school->id)
            ->pluck('id')
            ->toArray();

        if (count($validIds) !== count($sectionIds)) {
            throw new Exception('One or more section IDs are invalid or do not belong to the current school.');
        }

        try {
            return $this->schoolSections()->sync($validIds);
        } catch (\Exception $e) {
            Log::error('Failed to sync school sections', [
                'model'       => get_class($this),
                'id'          => $this->id,
                'section_ids' => $sectionIds,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Scope: Filter records that are assigned to a specific school section.
     */
    public function scopeInSection(Builder $query, int $sectionId): Builder
    {
        return $query->whereHas('schoolSections', function ($q) use ($sectionId) {
            $q->where('school_sections.id', $sectionId);
        });
    }

    /**
     * Scope: Filter records that are NOT assigned to a specific school section.
     */
    public function scopeNotInSection(Builder $query, int $sectionId): Builder
    {
        return $query->whereDoesntHave('schoolSections', function ($q) use ($sectionId) {
            $q->where('school_sections.id', $sectionId);
        });
    }
}
