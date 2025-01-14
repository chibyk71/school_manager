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
        return $this->belongsTo(School::class, BelongsToschool::$schoolIdColumn);
    }

    public static function bootBelongsToschool()
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function ($model) {
            if (! $model->getAttribute(BelongsToschool::$schoolIdColumn) && ! $model->relationLoaded('school')) {
                $currentSchool = GetSchoolModel()->id;
                if (tenancy()->initialized) {
                    $model->setAttribute(BelongsToschool::$schoolIdColumn, school()->getschoolKey());
                    $model->setRelation('school', school());
                }
            }
        });
    }
    
}