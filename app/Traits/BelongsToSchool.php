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

    public static function bootBelongsToSchool()
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function ($model) {
            if (! $model->getAttribute(BelongsToSchool::$schoolIdColumn) && ! $model->relationLoaded('school')) {
                $currentSchool = GetSchoolModel()->id;
                if ($currentSchool) {
                    $model->setAttribute(BelongsToSchool::$schoolIdColumn, $currentSchool);
                    $model->setRelation('school', GetSchoolModel());
                }
            }
        });
    }
    
}