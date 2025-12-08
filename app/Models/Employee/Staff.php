<?php

namespace App\Models\Employee;

use App\Models\Misc\AttendanceLedger;
use App\Models\Profile;
use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasAvatar;                    // ← NEW: Unified avatar system
use App\Traits\HasCustomFields;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Staff Model – Clean & Enterprise-Ready
 *
 * All personal data (name, email, phone, photo) now comes from Profile.
 * No more duplicated accessors. Single source of truth.
 */
class Staff extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        BelongsToSections,
        HasCustomFields,
        HasTableQuery,
        LogsActivity,
        HasAvatar; // ← Replaces old media handling + gives photo_url

    protected $table = 'staff';

    protected $fillable = [
        'date_of_employment',
        'date_of_termination',
        'staff_id_number',
    ];

    protected $casts = [
        'date_of_employment' => 'date',
        'date_of_termination' => 'date',
    ];

    protected $appends = [
        // Removed: full_name, short_name, phone, email, photo_url
    ];

    protected array $hiddenTableColumns = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $globalFilterFields = [
        'staff_id_number',
        'profile.full_name',
        'profile.phone',
        'profile.user.email',
    ];

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    /**
     * The Profile that owns this Staff record (polymorphic).
     * Now a clean BelongsTo instead of hasOneThrough.
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'id', 'profilable_id')
            ->where('profilable_type', self::class);
    }

    /**
     * Shortcut to the User via Profile.
     */
    public function user(): BelongsTo
    {
        return $this->profile()->with('user')->select('user_id');
    }

    /**
     * Department roles (many-to-many)
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

    public function attendance(): MorphMany
    {
        return $this->morphMany(AttendanceLedger::class, 'attendable');
    }

    // =================================================================
    // ACCESSORS – Only business logic here
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

    /**
     * Avatar – powered by HasAvatar trait (Spatie MediaLibrary)
     */
    public function getPhotoUrlAttribute(): string
    {
        return $this->avatarUrl('medium', $this->profile?->gender);
    }

    public function getYearsOfServiceAttribute(): ?int
    {
        return $this->date_of_employment?->diffInYears(now());
    }

    public function getIsActiveAttribute(): bool
    {
        return is_null($this->date_of_termination);
    }

    // =================================================================
    // SCOPES
    // =================================================================

    public function scopeActive($query)
    {
        return $query->whereNull('date_of_termination');
    }

    // =================================================================
    // BOOT – Cascade soft delete safely
    // =================================================================

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (self $staff) {
            if ($staff->isForceDeleting())
                return;

            // Delete the associated Profile (which will clear avatar via HasAvatar trait)
            if ($profile = $staff->profile) {
                $profile->delete();
            }

            // Optional: Soft-delete User if they have no other profiles
            if ($user = $staff->user?->first()) {
                $remainingProfiles = $user->profiles()
                    ->where('id', '!=', $profile?->id)
                    ->count();

                if ($remainingProfiles === 0) {
                    $user->delete(); // Soft delete user account
                }
            }
        });

        // Restore cascade
        static::restoring(function (self $staff) {
            if ($profile = $staff->profile()->withTrashed()->first()) {
                $profile->restore();
            }
        });
    }

    // =================================================================
    // ACTIVITY LOGGING
    // =================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('staff')
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(
                fn(string $eventName) =>
                "Staff {$this->full_name} ({$this->staff_id_number}) was {$eventName}"
            );
    }
}
