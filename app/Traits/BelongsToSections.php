<?php

namespace App\Traits;

use App\Models\School;
use App\Models\SchoolSection;
use Illuminate\Support\Facades\Log;

/**
 * Trait to manage polymorphic relationships with school sections.
 */
trait BelongsToSections
{
    /**
     * Get the polymorphic relationship to school sections.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function schoolSections()
    {
        return $this->morphToMany(SchoolSection::class, 'sectionable');
    }

    /**
     * Attach one or more school sections to the model.
     *
     * @param array $sectionIds Array of school section IDs to attach.
     * @return void
     * @throws \Exception If section IDs are invalid or not scoped to the active school.
     */
    public function attachSections(array $sectionIds)
    {
        try {
            if (empty($sectionIds)) {
                return;
            }

            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate section IDs belong to the school
            $validSections = SchoolSection::whereIn('id', $sectionIds)
                ->where('school_id', $school->id)
                ->pluck('id')
                ->toArray();

            if (count($validSections) !== count($sectionIds)) {
                throw new \Exception('Some section IDs are invalid or not associated with the active school.');
            }

            $this->schoolSections()->attach($validSections);
        } catch (\Exception $e) {
            Log::error('Failed to attach sections: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Detach one or more school sections from the model.
     *
     * @param array|int $sectionIds Section ID(s) to detach.
     * @return int Number of detached sections.
     * @throws \Exception If detachment fails.
     */
    public function detachSections($sectionIds)
    {
        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            $sectionIds = is_array($sectionIds) ? $sectionIds : [$sectionIds];
            $validSections = SchoolSection::whereIn('id', $sectionIds)
                ->where('school_id', $school->id)
                ->pluck('id')
                ->toArray();

            return $this->schoolSections()->detach($validSections);
        } catch (\Exception $e) {
            Log::error('Failed to detach sections: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync school sections for the model.
     *
     * Replaces existing sections with the provided ones.
     *
     * @param array $sectionIds Array of school section IDs to sync.
     * @return array Synced section IDs.
     * @throws \Exception If sync fails or section IDs are invalid.
     */
    public function syncSections(array $sectionIds)
    {
        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            $validSections = SchoolSection::whereIn('id', $sectionIds)
                ->where('school_id', $school->id)
                ->pluck('id')
                ->toArray();

            if (count($validSections) !== count($sectionIds)) {
                throw new \Exception('Some section IDs are invalid or not associated with the active school.');
            }

            return $this->schoolSections()->sync($validSections);
        } catch (\Exception $e) {
            Log::error('Failed to sync sections: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Scope query to filter by a specific school section.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $sectionId The school section ID.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInSection($query, int $sectionId)
    {
        return $query->whereHas('schoolSections', function ($q) use ($sectionId) {
            $q->where('school_section_id', $sectionId);
        });
    }
}
