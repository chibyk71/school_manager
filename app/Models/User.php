<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Models\Academic\Student;
use App\Models\Employee\Department;
use App\Models\Employee\Staff;
use App\Models\Finance\FeeConcession;
use App\Models\Guardian;
use App\Models\Transport\Route;
use App\Models\Transport\Vehicle\Vehicle;
use App\Notifications\TimeTableGeneratedNotification;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laratrust\Contracts\LaratrustUser;
use Laratrust\Traits\HasRolesAndPermissions;
use RuangDeveloper\LaravelSettings\Traits\HasSettings;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * User Model
 *
 * Central authentication model for all user types (students, staff, guardians).
 * Uses polymorphic profiles to support multiple roles per user across schools.
 *
 * @property string $id
 * @property string $enrollment_id
 * @property string $email
 * @property string|null $password
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|Profile[] $profiles
 * @property-read Profile|null $primaryProfile
 * @property-read Profile|null $staffProfile
 * @property-read Profile|null $studentProfile
 * @property-read Profile|null $guardianProfile
 * @property-read \Illuminate\Database\Eloquent\Collection|School[] $schools
 *
 * @method static \Illuminate\Database\Eloquent\Builder|User tableQuery(\Illuminate\Http\Request $request, array $extraFields = [], array $customModifiers = [])
 */
class User extends Authenticatable implements LaratrustUser
{
    use HasFactory,
        Notifiable,
        HasSettings,
        CausesActivity,
        HasRolesAndPermissions,
        HasUuids,
        Filterable,
        Sortable,
        HasTableQuery,
        LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'enrollment_id',
        'email',
        'password',
        'must_change_password',
        'is_active', // Explicitly allow mass assignment for status toggle
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
    ];

    /**
     * Columns used for global search filtering.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'email',
        'enrollment_id',
        'profiles.first_name',
        'profiles.last_name',
        'profiles.phone',
        'schools.name', // Relation-based filtering
    ];

    /**
     * Columns hidden from table output but still selectable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
        'email_verified_at',
    ];

    // =================================================================
    // CORE RELATIONSHIPS (NEW ARCHITECTURE)
    // =================================================================

    /**
     * Get all profiles associated with the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }

    /**
     * Get the primary profile (marked as is_primary = true).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function primaryProfile(): HasOne
    {
        return $this->hasOne(Profile::class)->where('is_primary', true);
    }

    /**
     * Get the staff profile (if exists).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(Profile::class)->where('profile_type', 'staff');
    }

    /**
     * Get the student profile (if exists).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(Profile::class)->where('profile_type', 'student');
    }

    /**
     * Get the guardian profile (if exists).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function guardianProfile(): HasOne
    {
        return $this->hasOne(Profile::class)->where('profile_type', 'guardian');
    }

    /**
     * Get schools the user is associated with via profiles.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_users');
    }

    // =================================================================
    // ACCESSORS & MUTATORS
    // =================================================================

    /**
     * Get the user's full name from the primary profile.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return $this->primaryProfile?->full_name ?? $this->email;
    }

    /**
     * Get the user's primary role type (student, staff, guardian).
     *
     * @return string|null
     */
    public function getTypeAttribute(): ?string
    {
        return $this->primaryProfile?->profile_type;
    }

    // =================================================================
    // HELPER METHODS
    // =================================================================

    /**
     * Check if user is staff at a given school.
     *
     * @param \App\Models\School $school
     * @return bool
     */
    public function isStaffAt(School $school): bool
    {
        return $this->profiles()
            ->where('school_id', $school->id)
            ->where('profile_type', 'staff')
            ->exists();
    }

    /**
     * Check if user is student at a given school.
     *
     * @param \App\Models\School $school
     * @return bool
     */
    public function isStudentAt(School $school): bool
    {
        return $this->profiles()
            ->where('school_id', $school->id)
            ->where('profile_type', 'student')
            ->exists();
    }

    /**
     * Check if user is guardian at a given school.
     *
     * @param \App\Models\School $school
     * @return bool
     */
    public function isGuardianAt(School $school): bool
    {
        return $this->profiles()
            ->where('school_id', $school->id)
            ->where('profile_type', 'guardian')
            ->exists();
    }

    /**
     * Determine if user has multiple profile types.
     *
     * @return bool
     */
    public function hasMultipleRoles(): bool
    {
        return $this->profiles->groupBy('profile_type')->count() > 1;
    }

    /**
     * Check if the user is forced to change password on next login.
     */
    public function getMustChangePasswordAttribute(): bool
    {
        return (bool) $this->attributes['must_change_password'] ?? false;
    }

    // =================================================================
    // ACTIVITY LOGGING
    // =================================================================

    /**
     * Get the activity log options.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user')
            ->logOnly(['email', 'enrollment_id', 'is_active'])
            ->setDescriptionForEvent(fn(string $eventName) => "User {$this->full_name} ({$this->email}) was {$eventName}")
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // =================================================================
    // LEGACY SUPPORT (OPTIONAL)
    // =================================================================

    /**
     * Get primary category (legacy fallback).
     *
     * @return string|null
     */
    public function getPrimaryCategory(): ?string
    {
        return $this->departments()->exists()
            ? parent::getPrimaryCategory()
            : null;
    }

    /**
     * Legacy: departments relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_user');
    }

    /**
     * Legacy: fee concessions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function feeConcessions(): BelongsToMany
    {
        return $this->belongsToMany(FeeConcession::class, 'user_fee_concessions');
    }

    /**
     * Legacy: transport routes.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'route_vehicle', 'user_id', 'route_id')
            ->withPivot('vehicle_id');
    }

    /**
     * Legacy: vehicles.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'route_vehicle', 'user_id', 'vehicle_id')
            ->withPivot('route_id');
    }

    /**
     * Legacy: broadcast notifications.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function receiveBroadcastNotifications(): MorphMany
    {
        return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable')
            ->where('type', TimeTableGeneratedNotification::class);
    }
}
