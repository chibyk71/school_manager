<?php

namespace App\Traits;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToSchool
{

    public static function getSchoolIdColumn(): string
    {
        return 'school_id'; // Uses overridden value if set in the model
    }

    public function school()
    {
        return $this->belongsTo(School::class, Static::getSchoolIdColumn());
    }

    protected static function bootBelongsToSchool()
    {
        static::addGlobalScope(new SchoolScope);

        // Add a global scope for the school
        static::creating(function ($model) {
            // Check if the school_id attribute is not set and the school relation is not loaded
            if (! $model->getAttribute(Static::getSchoolIdColumn()) && ! $model->relationLoaded('school')) {
                // Get the current school's ID
                $currentSchool = GetSchoolModel()->id;
                // If a current school is set, assign its ID and relation to the model
                if ($currentSchool) {
                    $model->setAttribute(Static::getSchoolIdColumn(), $currentSchool);
                    $model->setRelation('school', GetSchoolModel());
                }
            }
        });
    }
    
}