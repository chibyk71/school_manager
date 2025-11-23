<?php

namespace App\Models\Academic;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class AcademicSession
 *
 * Represents an academic session for a school, such as a school year or semester.
 * Supports multi-tenancy by associating with a specific school via the BelongsToSchool trait.
 *
 * @package App\Models\Academic
 */
class AcademicSession extends Model
{
    /** @use HasFactory<\Database\Factories\AcademicSessionFactory> */
    use HasFactory, BelongsToSchool, SoftDeletes, HasTableQuery, LogsActivity, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_current',
        'school_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_current' => 'boolean',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    /**
     * Hidden columns in table view (not searchable/sortable/filterable).
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'deleted_at',
    ];

    /**
     * Columns used for global search.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'name',
    ];

    /**
     * Get the terms associated with this academic session.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function terms()
    {
        return $this->hasMany(Term::class, 'academic_session_id', 'id');
    }

    /**
     * Get the current academic session for the active school.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCurrentSession($query)
    {
        return $query->where('is_current', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('academic_session')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
