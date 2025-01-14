<?php

namespace App\Models\Scopes;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SchoolScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model)
    {
        // TODO write the actual logic to check if there is a school initialized
        $currentlyInitializedSchool = GetSchoolModel()->id;

        if (! $currentlyInitializedSchool ) {
            return;
        }

        $builder->where($model->qualifyColumn(BelongsToSchool::$schoolIdColumn), $currentlyInitializedSchool);
    }

    public function extend(Builder $builder)
    {
        $builder->macro('withoutSchool', function (Builder $builder) {
            return $builder->withoutGlobalScope(SchoolScope::class);
        });
    }
}
