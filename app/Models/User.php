<?php

namespace App\Models;

use App\Models\Employee\Department;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

/**
 * User Model – Authentication & Core Identity (v1.0 – Aligned with New Architecture)
 *
 * This is the central authentication model (extends Laravel's Authenticatable).
 * It links to exactly one Profile (via user_id on Profile – nullable, set null on user delete).
 * Personal data (name, photo, phone, gender, DOB, addresses, etc.) lives exclusively on Profile.
 * Role-specific data (student enrollments, staff positions, guardian responsibilities) lives on
 * dedicated models (Student, Staff, Guardian) linked to Profile.
 *
 * Key Architectural Decisions Reflected Here:
 * - No profile_type column or polymorphic profilable anymore
 * - No multiple profiles per user (1:1 User ↔ Profile)
 * - Profile is nullable → not every user needs personal data (but most do)
 * - Roles checked via Profile → Student/Staff/Guardian relationships
 * - No HasProfile trait (replaced with explicit methods on User or Profile)
 * - Legacy/deprecated relationships (departments, feeConcessions, etc.) preserved but isolated
 * - Multi-school access via schools() pivot (if users have cross-school permissions)
 *
 * Features / Problems Solved:
 * - Clean separation: Auth + settings + permissions here; personal data on Profile; role data on role models
 * - Optional personal profile → supports service accounts, system users, or future anonymous access
 * - Secure 2FA, roles/permissions (Laratrust), activity logging, notifications
 * - Role validation moved to services/controllers → avoids model bloat
 * - Performance: eager loading helpers, scoped queries, global filter fields for tables
 * - Security: hidden sensitive fields, hashed password, two-factor support
 * - Extensibility: HasSettings for user preferences, HasRolesAndPermissions for access control
 *
 * Fits into User Management Module:
 * - Entry point for login, registration, password reset, profile linking
 * - Used in middleware (e.g., check if user has staff/student context)
 * - Integrates with Inertia: passes user + profile data to frontend
 * - Controllers (Auth, Profile, Student, Staff, Guardian) interact with this model
 * - Frontend: usePermissions composable reads roles from here; avatar from profile
 * - No direct UI manipulation of personal data here — delegated to Profile/Role flows
 *
 * Important Conventions:
 * - Never store name/phone/photo/email on User — always go through Profile
 * - User deletion → cascade set null on Profile.user_id (protected foreign key)
 * - Role checks: prefer $user->profile?->isStudent() / isStaff() / isGuardian()
 * - Cross-school access: controlled via schools() pivot + permissions
 */

class User extends Authenticatable implements LaratrustUser, TwoFactorAuthenticatable
{
    use HasFactory,
        Notifiable,
        HasSettings,
        CausesActivity,
        HasRolesAndPermissions,
        HasUuids,
        TwoFactorAuthentication,
        LogsActivity;

    protected $fillable = [
        'username',
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
        'email_verified_at'     => 'datetime',
        'password'              => 'hashed',
        'is_active'             => 'boolean',
        'must_change_password'  => 'boolean',
    ];

    // For HasTableQuery trait (if used on user listings)
    protected array $globalFilterFields = [
        'username',
        'email',
        'profile.first_name',
        'profile.last_name',
        'profile.phone',
    ];

    protected $with = ['profile'];

    protected $appends = ['full_name', 'email', 'phone', 'avatar_url'];

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    /**
     * The single personal Profile linked to this user (nullable)
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Schools this user has explicit access to (pivot table: school_users)
     * Used for cross-school permissions or multi-school staff/guardians
     */
    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_users');
    }

    // =================================================================
    // ROLE CHECK HELPERS (replaces old HasProfile trait)
    // =================================================================

    public function isStudent(): bool
    {
        return $this->profile?->students()->exists() ?? false;
    }

    public function isStaff(): bool
    {
        return $this->profile?->staffPositions()->exists() ?? false;
    }

    public function isGuardian(): bool
    {
        return $this->profile?->guardians()->exists() ?? false;
    }

    public function activeRoleType(): ?string
    {
        return match (true) {
            $this->isStaff()    => 'staff',
            $this->isStudent()  => 'student',
            $this->isGuardian() => 'guardian',
            default             => null,
        };
    }

    // =================================================================
    // SCHOOL-SPECIFIC ROLE CHECKS
    // =================================================================

    public function isStaffAt(School $school): bool
    {
        return $this->profile?->staffPositions()
            ->where('school_id', $school->id)
            ->exists() ?? false;
    }

    public function isStudentAt(School $school): bool
    {
        return $this->profile?->students()
            ->where('school_id', $school->id)
            ->exists() ?? false;
    }

    public function isGuardianAt(School $school): bool
    {
        return $this->profile?->guardians()
            ->where('school_id', $school->id)
            ->exists() ?? false;
    }

    // =================================================================
    // ACCESSORS (minimal – most data lives on Profile)
    // =================================================================

    public function getFullNameAttribute(): ?string
    {
        return $this->profile?->full_name;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->profile?->phone;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->profile?->email;
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->profile?->photo_url ?? asset('images/avatars/default-male.png');
    }

    // =================================================================
    // ACTIVITY LOGGING
    // =================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['username', 'email', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "User {$this->email} ({$this->full_name}) was {$eventName}");
    }

    // =================================================================
    // LEGACY / TRANSITION RELATIONSHIPS (keep for backward compatibility)
    // =================================================================

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_user');
    }
}
