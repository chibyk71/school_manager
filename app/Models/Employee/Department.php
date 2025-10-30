<?php

namespace App\Models\Employee;

use App\Models\Role;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Department model representing a school department (e.g., HR, Academics).
 *
 * @property string $id
 * @property string $school_id
 * @property string $name
 * @property string|null $category
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $effective_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Department extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BelongsToSchool, HasTableQuery, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'category',
        'description',
        'effective_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'effective_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'description',
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
        'category',
    ];

    /**
     * Configure activity logging options.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('department')
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Department {$this->name} was {$eventName}");
    }

    /**
     * Define the relationship to the Role model (many-to-many).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'department_role', 'department_id', 'role_id')
                    ->withTimestamps();
    }

    /**
     * Define the relationship to the User model (many-to-many).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'department_user', 'department_id', 'user_id')
                    ->withTimestamps();
    }

    /**
     * Scope a query to only include departments for a specific school.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $schoolId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }
}
