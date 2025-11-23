<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;

/**
 * Global scope to filter models based on their relationship to a primary model.
 *
 * Applies a whereHas constraint to ensure models are associated with the active school's primary model.
 * Provides a `withoutParentModel` macro to disable the scope.
 */
class ParentModelScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param Builder $builder The query builder instance.
     * @param Model $model The Eloquent model instance.
     * @return void
     * @throws \Exception If no active school is found or relationship is invalid.
     */
    public function apply(Builder $builder, Model $model): void
    {
        try {
            $school = GetSchoolModel();
            if (!$school) {
                Log::warning('No active school found for ParentModelScope.');
                return;
            }

            $relationship = method_exists($model, 'getRelationshipToPrimaryModel')
                ? $model->getRelationshipToPrimaryModel()
                : null;

            if (!$relationship || !method_exists($model, $relationship)) {
                throw new \Exception("Invalid or undefined relationship to primary model in " . get_class($model));
            }

            $builder->whereHas($relationship, function (Builder $query) use ($school) {
                $query->where('id', $school->id);
            });
        } catch (\Exception $e) {
            Log::error('Failed to apply ParentModelScope: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extend the query builder with additional macros.
     *
     * Adds a `withoutParentModel` macro to disable this scope.
     *
     * @param Builder $builder The query builder instance.
     * @return void
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutParentModel', function (Builder $builder) {
            return $builder->withoutGlobalScope(static::class);
        });
    }
}
