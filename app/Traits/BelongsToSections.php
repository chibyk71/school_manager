<?php
namespace App\Traits;

use App\Models\SchoolSection;

trait BelongsToSections
{
    /**
     * Polymorphic relationship to school sections.
     */
    public function schoolSections()
    {
        return $this->morphToMany(SchoolSection::class, 'sectionable');
    }

    /**
     * Attach one or more school sections to the model.
     */
    public function attachSections(array $sectionIds)
    {
        // Validate that all sectionIds are valid (optional)
        if (empty($sectionIds)) {
            return; // or throw an exception
        }

        $this->schoolSections()->attach($sectionIds);
    }

    /**
     * Detach one or more school sections from the model.
     */
    public function detachSections(array|int $sectionIds)
    {
        return $this->schoolSections()->detach($sectionIds);
    }

    /**
     * Sync school sections for the model.
     * This will replace existing sections with the provided ones.
     */
    public function syncSections(array $sectionIds)
    {
        return $this->schoolSections()->sync($sectionIds);
    }

    /**
     * Scope query to filter by a specific school section.
     */
    public function scopeInSection($query, int $sectionId)
    {
        return $query->whereHas('schoolSections', function ($q) use ($sectionId) {
            $q->where('school_section_id', $sectionId);
        });
    }
}
