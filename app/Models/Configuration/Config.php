<?php

namespace App\Models\Configuration;

use App\Models\Model;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing a configuration in the school management system.
 *
 * Configurations can be system-wide or scoped to a school.
 *
 * @property string $id Auto-incrementing primary key.
 * @property string $name Configuration name (e.g., theme_color).
 * @property string|null $description Configuration description.
 * @property string|null $color Optional color value (e.g., hex code).
 * @property string|null $scope_type Morph type for scope (e.g., School, null for system).
 * @property string|null $scope_id Morph ID for scope.
 * @property string $configurable_type Morph type for configurable entity.
 * @property string $configurable_id Morph ID for configurable entity.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Config extends Model
{
    use HasFactory, LogsActivity, HasTableQuery, SoftDeletes, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'value',
        'color',
        'scope_type',
        'scope_id',
        'configurable_type',
        'configurable_id',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'id',
        'scope_id',
        'scope_type',
        'configurable_id',
        'configurable_type',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'name',
        'description',
    ];

    /**
     * Get the configurable entity associated with this configuration.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function configurable()
    {
        return $this->morphTo(__FUNCTION__, 'configurable_type', 'configurable_id');
    }

    /**
     * Get the scope entity associated with this configuration.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function scopeModel()
    {
        return $this->morphTo(__FUNCTION__, 'scope_type', 'scope_id');
    }

    /**
     * Get the options for logging changes to the model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('configuration')
            ->setDescriptionForEvent(function ($event) {
                $configurable = $this->configurable ? class_basename($this->configurable_type) : 'unknown';
                return "Configuration {$event} on {$configurable}: {$this->name}";
            })
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
