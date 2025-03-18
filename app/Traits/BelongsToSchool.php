<?php

namespace App\Traits;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToSchool
{
    public static $schoolIdColumn = 'school_id';

    public function school()
    {
        return $this->belongsTo(School::class, BelongsToSchool::$schoolIdColumn);
    }

    protected static function bootBelongsToSchool()
    {
        static::addGlobalScope(new SchoolScope);

        // Add a global scope for the school
        static::creating(function ($model) {
            // Check if the school_id attribute is not set and the school relation is not loaded
            if (! $model->getAttribute(BelongsToSchool::$schoolIdColumn) && ! $model->relationLoaded('school')) {
                // Get the current school's ID
                $currentSchool = GetSchoolModel()->id;
                // If a current school is set, assign its ID and relation to the model
                if ($currentSchool) {
                    $model->setAttribute(BelongsToSchool::$schoolIdColumn, $currentSchool);
                    $model->setRelation('school', GetSchoolModel());
                }
            }
        });
    }
    
}