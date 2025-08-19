<?php

namespace App\Models\Configuration;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;use App\Models\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Config extends Model
{
    /** @use HasFactory<\Database\Factories\Configuration\ConfigFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'configurable_id',
        'configurable_type',
        'description',
        'color',
        'scope_id',
        'scope_type', // scope_type is the type of the scope (e.g. nulll for application wide, school, school section etc.)
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('configuration')
            ->setDescriptionForEvent(function ($event) {
                $configurable = $this->configurable_type;
                $configurableName = $configurable ? $configurable->name : 'unknown';
                return "Configuration event on {$configurableName}: {$event}";
            })
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    public function configurable()
    {
        return $this->morphTo(__FUNCTION__, 'configurable_type', 'configurable_id');
    }

    public function scopeModel()
    {
        return $this->morphTo(__FUNCTION__, 'scope_type', 'scope_id');
    }
}
