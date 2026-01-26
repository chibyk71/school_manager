<?php

namespace App\Models;

use App\Traits\HasAddress;
use App\Traits\HasCustomFields;
use App\Traits\HasDynamicEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit;

/**
 * Profile Model – Central Person Entity (v1.0 – Production-Ready)
 *
 * This is the single source of truth for any individual in the system (students, staff, guardians, etc.).
 * It stores shared personal information to prevent duplication across roles and schools.
 *
 * Features / Problems Solved:
 * - De-duplication: One Profile per real person → same person can be staff in School A and guardian in School B without duplicating name/phone/DOB/photo.
 * - Role flexibility: hasMany relationships to Student, Staff, Guardian models allow multiple roles over time/schools.
 * - Optional login: hasOne User relationship → not every profile needs app access (e.g., young students, some guardians).
 * - Multi-tenant readiness: No direct BelongsToSchool (tenant-wide), but role models (Student/Staff/Guardian) apply school scoping.
 * - Rich personal data: Supports HasAddress (multiple addresses), HasCustomFields (school-specific extensions), HasDynamicEnum (title, gender, etc.).
 * - Media handling: Single avatar via Spatie Media Library with thumb/medium conversions + gender-based fallback.
 * - Activity logging: Full audit trail via Spatie LogsActivity (useful for admin review, compliance).
 * - Soft deletes: Safe archival of inactive profiles without losing historical role links.
 * - Accessors: full_name, short_name, age, photo_url (with fallback) for consistent display.
 * - Helper methods: isStaff(), isStudent(), isGuardian(), markAsPrimary() for easy role/type checks.
 * - Boot logic: Enforces single primary profile per user (if multiple profiles exist), auto-demotes/promotes on changes/deletes.
 *
 * Fits into the User Management Module:
 * - Hub model: Created bundled with roles (e.g., StudentController creates Profile + Student; StaffController creates Profile + Staff + optional User).
 * - Never created standalone: Always through role-specific flows (enroll student, hire staff, register guardian).
 * - Used in:
 *   - Data tables: ProfilesTable.vue (search/merge across roles), StudentsTable.vue (joins profile for name/photo).
 *   - Modals: ProfileFormModal.vue (shared partial for name/DOB/gender), StudentEnrollmentModal.vue (extends with student fields).
 *   - Permissions: User model (linked via hasOne) holds roles/permissions; Profile holds personal data only.
 * - Integrates with traits:
 *   - HasAddress → multiple addresses per person (home, work, temporary).
 *   - HasCustomFields → school-defined fields (e.g., blood_group, allergies for students; qualifications for staff).
 *   - HasDynamicEnum → title (Mr/Mrs), gender (male/female/other/prefer_not_to_say), etc.
 * - Security: No sensitive auth data here (lives in User); school scoping delegated to role models.
 * - Performance: Indexes on searchable fields; eager loading recommended for role joins.
 *
 * Important Notes:
 * - This model does NOT use profilable_id / profilable_type (polymorphic) — we use explicit hasMany relationships instead for clarity and easier querying.
 * - profile_type field removed — role detection now via existence of related records (has student() / staff() / guardian() relationships).
 * - is_primary removed — primary profile concept moved to User model if needed (most systems only need one "main" profile per user).
 */

class Profile extends Model implements HasMedia
{
    use HasFactory,
        SoftDeletes,
        HasAddress,
        HasCustomFields,
        HasDynamicEnum,
        InteractsWithMedia,
        LogsActivity;

    protected $fillable = [
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'phone',
        'email',           // optional — only if not using User email
        'notes',
        'user_id'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    protected $appends = [
        'full_name',
        'short_name',
        'age',
        'photo_url',
    ];

    // For HasTableQuery trait – fields available for global search
    protected array $globalFilterFields = [
        'first_name',
        'middle_name',
        'last_name',
        'phone',
        'email',
        'title',
    ];

    // For HasDynamicEnum trait – declare dynamic enum properties
    public function getDynamicEnumProperties(): array
    {
        return ['title', 'gender'];
    }

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    /**
     * Optional login account (1:1)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * All student enrollment records (historical & current)
     */
    public function students(): HasMany
    {
        return $this->hasMany(\App\Models\Academic\Student::class);
    }

    /**
     * All staff/employment positions (historical & current)
     */
    public function staffPositions(): HasMany
    {
        return $this->hasMany(\App\Models\Employee\Staff::class);
    }

    /**
     * All guardian responsibilities
     */
    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    // =================================================================
    // MEDIA LIBRARY – AVATAR
    // =================================================================

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->useDisk('public');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 120, 120)
            ->sharpen(10);

        $this->addMediaConversion('medium')
            ->fit(Fit::Crop, 600, 600)
            ->optimize()
            ->performOnCollections('photo');
    }

    // =================================================================
    // ACCESSORS
    // =================================================================

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn() => trim(implode(' ', array_filter([
                $this->title,
                $this->first_name,
                $this->middle_name,
                $this->last_name,
            ]))) ?: 'Unknown Person'
        );
    }

    protected function shortName(): Attribute
    {
        return Attribute::make(
            get: fn() => trim("{$this->first_name} {$this->last_name}") ?: 'Unknown'
        );
    }

    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->date_of_birth?->age
        );
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->getFirstMediaUrl('photo', 'medium')
            ?: asset('images/avatars/default-' . ($this->gender === 'female' ? 'female' : 'male') . '.png')
        );
    }

    // =================================================================
    // ROLE HELPERS
    // =================================================================

    public function isStudent(): bool
    {
        return $this->students()->exists();
    }

    public function isStaff(): bool
    {
        return $this->staffPositions()->exists();
    }

    public function isGuardian(): bool
    {
        return $this->guardians()->exists() || $this->wards()->exists();
    }

    public function hasLogin(): bool
    {
        return $this->user()->exists();
    }

    /**
     * Determine the active role type of this profile (student, staff, or guardian).
     *
     * @return string|null The active role type, or null if no active role exists.
     */
    public function activeRoleType(): ?string
    {
        return match (true) {
            $this->isStudent()  => 'student',
            $this->isStaff()    => 'staff',
            $this->isGuardian() => 'guardian',
            default             => null,
        };
    }

    // =================================================================
    // ACTIVITY LOGGING
    // =================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('profiles')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Profile {$this->full_name} was {$eventName}");
    }
}
