<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Models\Employee\Department;
use App\Models\Finance\FeeConcession;
use App\Models\Transport\Route;
use App\Models\Transport\Vehicle\Vehicle;
use App\Notifications\TimeTableGeneratedNotification;
use App\Traits\HasProfile;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Laragear\TwoFactor\TwoFactorAuthentication;
use Laratrust\Contracts\LaratrustUser;
use Laratrust\Traits\HasRolesAndPermissions;
use RuangDeveloper\LaravelSettings\Traits\HasSettings;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable implements LaratrustUser, TwoFactorAuthenticatable
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
        TwoFactorAuthentication,
        LogsActivity,
        HasProfile;

    protected $fillable = [
        'enrollment_id',
        'email',
        'password',
        'must_change_password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'must_change_password',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
    ];

    protected $appends = [
        'type',
        'full_name',
    ];

    protected array $globalFilterFields = [
        'email',
        'enrollment_id',
        'profiles.full_name',
        'profiles.phone',
        'schools.name',
    ];

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }

    public function primaryProfile(): HasOne
    {
        return $this->hasOne(Profile::class)->where('is_primary', true);
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_users');
    }

    // =================================================================
    // ACCESSORS
    // =================================================================

    public function getFullNameAttribute(): string
    {
        return $this->primaryProfile?->full_name ?? $this->email;
    }

    public function getTypeAttribute(): ?string
    {
        return $this->primaryProfile?->profile_type;
    }

    // =================================================================
    // ROLE CONFLICT VALIDATION (Business Rules)
    // =================================================================

    public function getAllowedRoleCombinations(): array
    {
        return [
            'staff'           => true,
            'student'         => true,
            'guardian'        => true,
            'staff-guardian'  => true,     // Teacher with kids in school
            'student-guardian'=> false,    // Not allowed (age conflict)
            'staff-student'   => false,    // Not allowed
        ];
    }

    public function canAddRole(string $roleType): bool
    {
        $current = $this->profiles->pluck('profile_type')->unique()->sort()->values();

        if ($current->contains($roleType)) {
            return true; // Already has this role
        }

        $proposed = $current->push($roleType)->sort()->values();
        $key = $proposed->implode('-');

        return $this->getAllowedRoleCombinations()[$key] ?? false;
    }

    public function hasValidRoleCombination(): bool
    {
        $roles = $this->profiles->pluck('profile_type')->unique()->sort()->values();
        $key = $roles->implode('-');

        return $this->getAllowedRoleCombinations()[$key] ?? false;
    }

    public function hasMultipleRoles(): bool
    {
        return $this->profiles->groupBy('profile_type')->count() > 1;
    }

    // =================================================================
    // SCHOOL-SPECIFIC ROLE CHECKS
    // =================================================================

    public function isStaffAt(School $school): bool
    {
        return $this->profiles()->where('school_id', $school->id)->where('profile_type', 'staff')->exists();
    }

    public function isStudentAt(School $school): bool
    {
        return $this->profiles()->where('school_id', $school->id)->where('profile_type', 'student')->exists();
    }

    public function isGuardianAt(School $school): bool
    {
        return $this->profiles()->where('school_id', $school->id)->where('profile_type', 'guardian')->exists();
    }

    public function rolesAtSchool(School $school): array
    {
        return $this->profiles()
            ->where('school_id', $school->id)
            ->pluck('profile_type')
            ->unique()
            ->toArray();
    }


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

    // =================================================================
    // QUERY OPTIMIZATION
    // =================================================================

    public function scopeWithCommonRelations($query)
    {
        return $query->with([
            'primaryProfile.profilable',
            'profiles' => fn($q) => $q->select('id', 'user_id', 'profile_type', 'is_primary', 'school_id'),
            'schools:id,name',
        ]);
    }

    // =================================================================
    // ACTIVITY LOGGING
    // =================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user')
            ->logOnly(['email', 'enrollment_id', 'is_active'])
            ->setDescriptionForEvent(fn($event) => "User {$this->full_name} ({$this->email}) was {$event}")
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
