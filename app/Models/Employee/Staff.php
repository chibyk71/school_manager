<?php

namespace App\Models\Employee;

use App\Models\Misc\AttendanceLedger;
use App\Models\Model;
use App\Models\Profile;
use App\Models\School;
use App\Models\User;
use App\Traits\BelongsToPrimaryModel;
use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasCustomFields;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
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
    use HasFactory, BelongsToSections, HasCustomFields, HasTableQuery, SoftDeletes, LogsActivity, HasUuids, BelongsToPrimaryModel;

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
        'date_of_employment',
        'date_of_termination',
        'staff_id_number',
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
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'full_name',
        'short_name',
        'phone',
        'email',
        'photo_url',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'full_name', 'staff_id_number',
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
            ->setDescriptionForEvent(fn(string $eventName) =>
                "Staff {$this->full_name} ({$this->staff_id_number}) was {$eventName}"
            );
    }

    public function profile(): HasOneThrough
    {
        return $this->hasOneThrough(
            Profile::class,
            Profile::class,
            'profilable_id',
            'id',
            'id',
            'profilable_id'
        )->where('profilable_type', static::class);
    }

    public function getRelationshipToPrimaryModel(): string
    {
        return 'profile';
    }


    /**
     * Get the user that owns the staff.
     *
     * @return mixed
     */
    public function user()
    {
        return $this->profile()->with('user')->select('user_id');
    }

    /**
     * Get the department role associated with the staff.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function departmentRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            DepartmentRole::class,
            'staff_department_role',
            'staff_id',
            'department_role_id'
        )->withTimestamps();
    }

    public function departments()
    {
        return $this->departmentRoles()->with('department')->get()->pluck('department')->unique();
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

    // =================================================================
    // ACCESSORS — USED EVERYWHERE (Dashboard, Reports, SMS, PDF)
    // =================================================================

    public function getFullNameAttribute(): string
    {
        return $this->profile?->full_name ?? 'Unknown Staff';
    }

    public function getShortNameAttribute(): string
    {
        return $this->profile?->short_name ?? 'Staff';
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->profile?->phone;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->profile?->user?->email;
    }

    public function getPhotoUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('photo') ?: asset('images/staff-avatar.png');
    }

    public function getYearsOfServiceAttribute(): ?int
    {
        if (!$this->date_of_employment) return null;
        return $this->date_of_employment->diffInYears(now());
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->date_of_termination === null;
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

    public function scopeActive($query)
    {
        return $query->whereNull('date_of_termination');
    }
}
