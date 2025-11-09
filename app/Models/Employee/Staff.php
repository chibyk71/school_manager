<?php

namespace App\Models\Employee;

use App\Models\Misc\AttendanceLedger;
use App\Models\Model;
use App\Models\School;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasCustomFields;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class Staff
 *
 * Represents a staff member in the school management system, linked to a user, school sections, and attendance.
 * Supports custom fields and tenancy scoping.
 *
 * @property string $id
 * @property string $user_id
 * @property string $school_id
 * @property string|null $department_role_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @package App\Models\Employee
 */
class Staff extends Model
{
    /** @use HasFactory<\Database\Factories\StaffFactory> */
    use HasFactory, BelongsToSchool, BelongsToSections, HasCustomFields, HasTableQuery, SoftDeletes, LogsActivity, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'staff';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'school_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
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
        'user.name',
        'departmentRole.name',
    ];

    /**
     * Configure activity logging options.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('staff')
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Staff for user ID {$this->user_id} was {$eventName}");
    }

    /**
     * Get the user that owns the staff.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the school associated with the staff.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the department role associated with the staff.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function departmentRole()
    {
        return $this->belongsToMany(DepartmentRole::class, 'staff_department_role', 'staff_id', 'department_role_id');
    }

    /**
     * Get the attendance records for the staff.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function attendance(): MorphMany
    {
        return $this->morphMany(AttendanceLedger::class, 'attendable');
    }

    /**
     * Get the school ID column name.
     *
     * @return string
     */
    public static function getSchoolIdColumn(): string
    {
        return 'school_id';
    }
}
