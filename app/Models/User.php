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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laratrust\Contracts\LaratrustUser;
use Laratrust\Traits\HasRolesAndPermissions;
use RuangDeveloper\LaravelSettings\Traits\HasSettings;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;

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

    protected $fillable = [
        'enrollment_id',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    protected array $globalFilterFields = ['email', 'enrollment_id'];
    protected array $hiddenTableColumns = ['password', 'remember_token', 'created_at', 'updated_at'];

    // =================================================================
    // CORE RELATIONSHIPS (NEW ARCHITECTURE)
    // =================================================================

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }

    public function primaryProfile(): HasOne
    {
        return $this->hasOne(Profile::class)->where('is_primary', true);
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(Profile::class)->where('profile_type', 'staff');
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(Profile::class)->where('profile_type', 'student');
    }

    public function guardianProfile(): HasOne
    {
        return $this->hasOne(Profile::class)->where('profile_type', 'guardian');
    }

    // Backwards compatibility (for old code) — will be removed in v2
    public function student()
    {
        return $this->hasOne(Student::class, 'user_id');
    }

    public function teacher()
    {
        return $this->hasOne(Staff::class, 'user_id');
    }

    public function guardian()
    {
        return $this->hasOne(Guardian::class, 'user_id');
    }

    // =================================================================
    // SCHOOL & TENANCY
    // =================================================================

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_users');
    }

    // =================================================================
    // ACCESSORS (MOST USED IN BLADE/VUE)
    // =================================================================

    public function getFullNameAttribute(): string
    {
        return $this->primaryProfile?->full_name
            ?? $this->profiles->first()?->full_name
            ?? $this->email;
    }

    public function getShortNameAttribute(): string
    {
        return $this->primaryProfile?->short_name
            ?? $this->profiles->first()?->short_name
            ?? explode('@', $this->email)[0];
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->primaryProfile?->photo_url
            ?? asset('images/avatar-placeholder.png');
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->primaryProfile?->phone;
    }

    public function getGenderAttribute(): ?string
    {
        return $this->primaryProfile?->gender;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->primaryProfile?->age;
    }

    // =================================================================
    // ROLE HELPERS
    // =================================================================

    public function isStaffAt(School $school): bool
    {
        return $this->profiles()
            ->where('school_id', $school->id)
            ->where('profile_type', 'staff')
            ->exists();
    }

    public function isStudentAt(School $school): bool
    {
        return $this->profiles()
            ->where('school_id', $school->id)
            ->where('profile_type', 'student')
            ->exists();
    }

    public function isGuardianAt(School $school): bool
    {
        return $this->profiles()
            ->where('school_id', $school->id)
            ->where('profile_type', 'guardian')
            ->exists();
    }

    public function hasMultipleRoles(): bool
    {
        return $this->profiles->groupBy('profile_type')->count() > 1;
    }

    // =================================================================
    // ACTIVITY LOGGING
    // =================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user')
            ->logOnly(['email', 'enrollment_id'])
            ->setDescriptionForEvent(fn(string $eventName) => "User {$this->full_name} ({$this->email}) was {$eventName}");
    }

    // =================================================================
    // LEGACY SUPPORT (OPTIONAL)
    // =================================================================

    public function getPrimaryCategory(): ?string
    {
        // Keep your old logic if needed
        return $this->departments()->exists()
            ? parent::getPrimaryCategory()
            : null;
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_user');
    }

    public function feeConcessions(): BelongsToMany
    {
        return $this->belongsToMany(FeeConcession::class, 'user_fee_concessions');
    }

    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'route_vehicle', 'user_id', 'route_id')
            ->withPivot('vehicle_id');
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'route_vehicle', 'user_id', 'vehicle_id')
            ->withPivot('route_id');
    }

    // Keep old notification logging if needed
    public function receiveBroadcastNotifications()
    {
        return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable')
            ->where('type', TimeTableGeneratedNotification::class);
    }
}