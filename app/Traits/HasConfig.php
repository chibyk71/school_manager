<?php

namespace App\Traits;

use App\Models\Configuration\Config;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Log;

/**
 * Trait to manage polymorphic configurations for a model.
 */
trait HasConfig
{
    /**
     * Get all configurations for the model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function configs(): MorphMany
    {
        return $this->morphMany(Config::class, 'configurable');
    }

    /**
     * Set the configuration value for a given key.
     *
     * @param string $name The configuration name.
     * @param mixed $value The configuration value.
     * @param array $scope Scope details ['id' => int|null, 'type' => string|null].
     * @return \App\Models\Configuration\Config The created or updated config.
     * @throws \Exception If configuration creation fails.
     */
    public function addConfig(string $name, $value, array $scope = ['id' => null, 'type' => null]): Config
    {
        try {
            $school = GetSchoolModel();
            if (!$school && $scope['type'] === School::class) {
                throw new \Exception('No active school found for school-scoped configuration.');
            }

            return $this->configs()->updateOrCreate(
                [
                    'name' => $name,
                    'scope_id' => $scope['id'] ?? $school?->id,
                    'scope_type' => $scope['type'] ?? ($school ? School::class : null),
                ],
                ['value' => $value]
            );
        } catch (\Exception $e) {
            Log::error("Failed to add config '$name': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Scope a query to only include configurations for a specific school.
     *
     * @param Builder $builder
     * @param int|null $school_id
     * @return Builder
     */
    public function scopeSchool(Builder $builder, $school_id = null): Builder
    {
        $school_id = $school_id ?? GetSchoolModel()?->id;
        if (!$school_id) {
            throw new \Exception('No active school found.');
        }

        return $builder->where('configs.scope_type', School::class)
                      ->where('configs.scope_id', $school_id);
    }

    /**
     * Scope a query to include configurations for a specific school or system configurations.
     *
     * @param Builder $builder
     * @param int|null $school_id
     * @return Builder
     */
    public function scopeAll(Builder $builder, $school_id = null): Builder
    {
        return $builder->where(function ($query) use ($school_id) {
            $query->school($school_id)->orWhere(function ($q) {
                $q->system();
            });
        });
    }

    /**
     * Scope a query to only include system configurations.
     *
     * @param Builder $builder
     * @return Builder
     */
    public function scopeSystem(Builder $builder): Builder
    {
        return $builder->whereNull('configs.scope_type')
                      ->whereNull('configs.scope_id');
    }
}
