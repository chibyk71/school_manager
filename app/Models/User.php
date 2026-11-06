<?php

namespace App\Models;

use App\Enums\Auth\AccountLifecycle;
use App\Models\Employee\Department;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Laragear\TwoFactor\TwoFactorAuthentication;
use Laratrust\Contracts\LaratrustUser;
use Laratrust\Traits\HasRolesAndPermissions;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Authentication Account (User).
 *
 * Credentials and authentication capability only — not the person's full identity.
 * Personal identity and communication email live on Profile (Person).
 * One account belongs to exactly one Person; one Person may have zero or one account.
 */
class User extends Authenticatable implements LaratrustUser, TwoFactorAuthenticatable
{
    use CausesActivity,
        HasFactory,
        HasRolesAndPermissions,
        HasUuids,
        LogsActivity,
        Notifiable,
        TwoFactorAuthentication;

    protected $fillable = [
        'username',
        'password',
        'lifecycle',
        'force_password_change',
        'auth_email_verified_at',
        'auth_email_enabled',
        'auth_phone',
        'auth_phone_verified_at',
        'auth_phone_enabled',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'password' => 'hashed',
        'lifecycle' => AccountLifecycle::class,
        'force_password_change' => 'boolean',
        'auth_email_verified_at' => 'datetime',
        'auth_email_enabled' => 'boolean',
        'auth_phone_verified_at' => 'datetime',
        'auth_phone_enabled' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    protected array $globalFilterFields = [
        'username',
        'profile.first_name',
        'profile.last_name',
        'profile.email',
        'profile.phone',
    ];

    protected $with = ['profile'];

    protected $appends = ['full_name', 'email', 'phone', 'avatar_url'];

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    /**
     * Linked Person (Profile). Schema ownership: profiles.user_id → users.id.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_users');
    }

    // =================================================================
    // LIFECYCLE & ELIGIBILITY
    // =================================================================

    public function isProvisioned(): bool
    {
        return $this->lifecycle === AccountLifecycle::Provisioned;
    }

    public function isActiveLifecycle(): bool
    {
        return $this->lifecycle === AccountLifecycle::Active;
    }

    /**
     * Whether this account may authenticate (credentials path).
     * Does not grant workspace access when force_password_change is set.
     */
    public function canAuthenticate(): bool
    {
        if (! $this->lifecycle?->canAuthenticate()) {
            return false;
        }

        if ($this->profile === null) {
            return false;
        }

        if ($this->profile->trashed()) {
            return false;
        }

        return true;
    }

    public function canUseEmailForLogin(): bool
    {
        if (! $this->canAuthenticate()) {
            return false;
        }

        $email = $this->profile?->email;

        return filled($email)
            && $this->auth_email_verified_at !== null
            && $this->auth_email_enabled === true;
    }

    public function canUsePhoneForLogin(): bool
    {
        if (! $this->canAuthenticate()) {
            return false;
        }

        return filled($this->auth_phone)
            && $this->auth_phone_verified_at !== null
            && $this->auth_phone_enabled === true;
    }

    public function requiresPasswordChange(): bool
    {
        return (bool) $this->force_password_change;
    }

    // =================================================================
    // ROLE CHECK HELPERS
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
            $this->isStaff() => 'staff',
            $this->isStudent() => 'student',
            $this->isGuardian() => 'guardian',
            default => null,
        };
    }

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
    // ACCESSORS (Person data from Profile)
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

    /**
     * Laragear Two-Factor TOTP credential label identifier (package v3.0.0).
     *
     * The package default returns {@see getAttribute('email')}. This Authentication
     * Account model does not store Person email as a users-table column; primary
     * communication email lives on Profile (Person). Returning that attribute would
     * yield null and break MFA enrollment (return-type error).
     *
     * Username is the account's mandatory unique credential handle and is always
     * present as a string column on users. It is used only as the TOTP URI/label
     * identifier for authenticator apps — not as a change to login-identifier
     * policy, email verification, or Person identity resolution.
     */
    public function getTwoFactorUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * Compatibility with diego-ninja/laravel-devices session bootstrap only.
     *
     * DeviceTracker calls this when creating tracked sessions (initialStatus). School
     * Manager MFA is exclusively Laragear Two-Factor (`hasTwoFactorEnabled`, challenge
     * routes, and Laragear safe devices). config/devices.php `google_2fa_enabled` is
     * false for the same reason — no Has2FA trait, no second TOTP secret, and no
     * DeviceTracker MFA enrollment. Always returns false so device-session status stays
     * Active without locking on an unused Google2FA integration.
     */
    public function google2faEnabled(): bool
    {
        return false;
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
            ->logOnly(['username', 'lifecycle', 'force_password_change', 'auth_email_enabled', 'auth_phone'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn (string $eventName) => "Authentication account {$this->username} was {$eventName}"
            );
    }

    // =================================================================
    // LEGACY RELATIONSHIPS
    // =================================================================

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_user');
    }
}
