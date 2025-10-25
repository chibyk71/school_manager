<?php

namespace App\Traits;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Trait to associate models with a specific school.
 *
 * Provides a `school` relationship, auto-assigns `school_id` on creation,
 * and applies a global `SchoolScope` for filtering with fallback to global records.
 */
trait BelongsToSchool
{
    /**
     * Get the column name used for the school ID.
     *
     * @return string The school ID column name (default: 'school_id').
     */
    public static function getSchoolIdColumn(): string
    {
        return 'school_id';
    }

    /**
     * Define the "belongs to" relationship with the School model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo(School::class, static::getSchoolIdColumn());
    }

    /**
     * Boot the trait by adding the SchoolScope and auto-assigning school_id.
     *
     * @return void
     * @throws \Exception If no active school is found during creation.
     */
    protected static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function ($model) {
            try {
                $schoolIdColumn = static::getSchoolIdColumn();
                if (!$model->getAttribute($schoolIdColumn) && !$model->relationLoaded('school')) {
                    $currentSchool = GetSchoolModel();
                    if (!$currentSchool) {
                        throw new \Exception('No active school found during model creation.');
                    }
                    $model->setAttribute($schoolIdColumn, $currentSchool->id);
                    $model->setRelation('school', $currentSchool);
                }
            } catch (\Exception $e) {
                Log::error('Failed to assign school_id in BelongsToSchool: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Scope to retrieve records with fallback to global entries.
     *
     * @param Builder $builder The query builder instance.
     * @param string|array $groupByColumns Columns to group by for fallback.
     * @return Builder
     */
    public function scopeWithSchoolFallback(Builder $builder, string|array $groupByColumns = 'name'): Builder
    {
        return $builder->withoutGlobalScope(SchoolScope::class)
            ->addGlobalScope(new SchoolScope($groupByColumns));
    }
}
