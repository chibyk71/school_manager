<?php

namespace App\Models\Academic;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Models\School;
use App\Models\SchoolSection;
use App\Traits\BelongsToSchool;
use App\Traits\HasDynamicEnum;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Subject Model — academic subject within a school.
 *
 * type and category are Dynamic Enum–backed scalars:
 *   type     → academic.subject_type
 *   category → academic.subject_category
 */
class Subject extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use BelongsToSchool;
    use HasTableQuery;
    use Filterable;
    use Sortable;
    use LogsActivity;
    use HasDynamicEnum;

    protected $fillable = [
        'school_id',
        'name',
        'code',
        'description',
        'type',
        'category',
        'is_active',
        'pass_mark',
        'credit_hours',
        'sort',
        'color',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'pass_mark' => 'integer',
        'credit_hours' => 'integer',
        'sort' => 'integer',
    ];

    protected array $globalFilterFields = [
        'name',
        'code',
        'description',
        'type',
        'category',
    ];

    protected array $hiddenTableColumns = [
        'school_id',
    ];

    protected array $defaultHiddenColumns = [
        'description',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolSections(): BelongsToMany
    {
        return $this->belongsToMany(SchoolSection::class, 'school_section_subject')
            ->withTimestamps();
    }

    public function classLevels(): BelongsToMany
    {
        return $this->belongsToMany(ClassLevel::class, 'class_level_subject')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('subject')
            ->setDescriptionForEvent(
                fn (string $event) => "Subject {$event}: {$this->name} ({$this->code})"
            )
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDynamicEnumProperties()
    {
        return ['type', 'category'];
    }
}
