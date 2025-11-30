<?php

namespace App\Models\Configuration;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Config extends Model
{
    use HasFactory, LogsActivity, HasTableQuery, HasUuids, BelongsToSchool;

    protected $fillable = [
        'label', // UI label
        'name', // machine name (e.g., currency)
        'applies_to', // Model class (e.g., App\Models\School)
        'description',
        'color',
        'options', // possible values to choose from
        'school_id',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    protected array $hiddenTableColumns = [
        'id',
        'school_id',
        'created_at',
        'updated_at',
    ];

    protected array $globalFilterFields = [
        'name',
        'label',
        'description',
    ];

    /* ------------------------------------------------------------------ */
    /* Scopes – visible to a school or system-wide                        */
    /* ------------------------------------------------------------------ */

    public function scopeVisibleToSchool(Builder $query, ?string $schoolId = null): Builder
    {
        return $query->where(function ($q) use ($schoolId) {
            $q->whereNull('school_id')
              ->orWhere(function ($sq) use ($schoolId) {
                  $sq->where('school_id', $schoolId);
              });
        });
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('school_id');
    }

    public function scopeForSchool(Builder $query, ?string $schoolId = null): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeForModel(Builder $query, string $modelClass): Builder
    {
        return $query->where('applies_to', $modelClass);
    }

    /* ------------------------------------------------------------------ */
    /* Activity log                                                       */
    /* ------------------------------------------------------------------ */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('configuration')
            ->setDescriptionForEvent(fn(string $event) => "Configuration {$event}: {$this->name}")
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
