<?php

namespace App\Models\Academic;

use App\Models\SchoolSection;
use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Subject model representing an academic subject (e.g., Mathematics, English) within a school.
 *
 * @property string $id
 * @property int $school_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property float|null $credit
 * @property bool $is_elective
 * @property array|null $options
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string $status
 */
class Subject extends Model
{
    use HasFactory, HasUuids, SoftDeletes, LogsActivity, BelongsToSchool, BelongsToSections, HasTableQuery;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'code',
        'description',
        'credit',
        'is_elective',
        'options',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'credit' => 'float',
        'is_elective' => 'boolean',
        'options' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = ['status'];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'options',
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
        'code',
        'description',
    ];

    /**
     * Configure activity logging options.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('subject')
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Subject {$this->name} ({$this->code}) was {$eventName}");
    }

    /**
     * Get the status attribute based on soft delete state.
     *
     * @return string
     */
    public function getStatusAttribute(): string
    {
        return $this->trashed() ? 'archived' : 'active';
    }
}