<?php

namespace App\Traits;

use App\Models\Configuration\Config;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasConfig
{
    /**
     * Set the configuration value for a given key.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function addConfig(string $name, $scope = ['id' => null, 'type'])
    {
        $config = $this->configs()->create(['name' => $name, 'scope_id' => $scope['id'], 'scope_type' => $scope['type']]);
    }

    /**
     * Get all configurations for the model.
     *
     * @return 
     */
    public function configs(): MorphOne
    {
        return $this->morphOne(Config::class, 'configurable');
    }

    /**
     * Scope a query to only include configurations for a specific school.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param int|null $school_id
     */
    public function scopeSchool(Builder $builder, $school_id = null)
    {
        if (!$school_id) {
            $school_id = GetSchoolModel()->id;
        }

        $builder->where('configs.scope_type', School::class)->where('configs.scope_id', $school_id);
    }

    /**
     * Scope a query to include configurations for a specific school or system configurations.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param int|null $school_id
     */
    public function scopeAll(Builder $builder, $school_id = null)
    {
        $builder->where(function ($query) use ($school_id) {
            $query->school($school_id)->orWhere->system();
        });
    }

/**
     * Scope a query to only include system configurations.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeSystem(Builder $query)
    {
        $query->where('configs.scope_type', null)->where('configs.scope_id', null);
    }
}
