<?php

namespace App\Traits;

use App\Models\Configuration\Config;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
     * Get a single config value.
     *
     * The lookup order is:
     *   1. exact scope (type+id) → 2. school scope → 3. system (null scope)
     *
     * @param string $name      Config name
     * @param mixed  $fallback  Value returned when nothing is found
     * @param array  $scope     ['type'=>…, 'id'=>…] – overrides the auto-school detection
     *
     * @return mixed
     */
    public function getConfig(string $name, $fallback = null, array $scope = [])
    {
        $query = $this->configs()->where('name', $name);

        // 1. Exact scope supplied by the caller
        if (!empty($scope['type']) && !empty($scope['id'])) {
            $query->where('scope_type', $scope['type'])
                ->where('scope_id', $scope['id']);
            $config = $query->first();
            if ($config) {
                return $config->value;
            }
        }

        // 2. School scope (auto-detected)
        $school = GetSchoolModel();
        if ($school) {
            $config = $query->clone()
                ->where('scope_type', School::class)
                ->where('scope_id', $school->id)
                ->first();
            if ($config) {
                return $config->value;
            }
        }

        // 3. System scope (null)
        $config = $query->clone()
            ->whereNull('scope_type')
            ->whereNull('scope_id')
            ->first();

        return $config?->value ?? $fallback;
    }

    /**
     * Get many configs at once.
     *
     * @param array|string[] $names   Config names to fetch (empty = all)
     * @param array          $scope   Same as getConfig()
     *
     * @return \Illuminate\Database\Eloquent\Collection  Config models (keyed by name)
     */
    public function getConfigs(array $names = [], array $scope = []): Collection
    {
        $query = $this->configs();

        if ($names) {
            $query->whereIn('name', $names);
        }

        // Apply the same precedence logic as getConfig()
        $school = GetSchoolModel();

        // 1. Exact scope
        if (!empty($scope['type']) && !empty($scope['id'])) {
            $query->where('scope_type', $scope['type'])
                ->where('scope_id', $scope['id']);
            return $query->get()->keyBy('name');
        }

        // 2. School scope
        if ($school) {
            $schoolConfigs = $query->clone()
                ->where('scope_type', School::class)
                ->where('scope_id', $school->id)
                ->get()
                ->keyBy('name');

            if ($schoolConfigs->isNotEmpty()) {
                // Merge with system configs (system = lower priority)
                $systemConfigs = $query->clone()
                    ->whereNull('scope_type')
                    ->whereNull('scope_id')
                    ->get()
                    ->keyBy('name');

                return $systemConfigs->merge($schoolConfigs);
            }
        }

        // 3. System only
        return $query->whereNull('scope_type')
            ->whereNull('scope_id')
            ->get()
            ->keyBy('name');
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
