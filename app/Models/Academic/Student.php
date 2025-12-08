<?php

namespace App\Models\Academic;

use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\Guardian;
use App\Models\Misc\AttendanceLedger;
use App\Models\SchoolSection;
use App\Models\Profile;
use App\Traits\BelongsToSchool;
use App\Traits\HasAvatar;                    // ← NEW: Unified avatar handling
use App\Traits\HasCustomFields;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Student Model – Clean & Modern Edition
 *
 * All personal information (name, age, phone, photo) now comes from Profile.
 * No more duplicated accessors!
 */
class Student extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasCustomFields,
        HasTableQuery,
        HasAvatar; // ← Replaces old HasMedia + getPhotoUrlAttribute()

    protected $fillable = [
        'school_section_id',
    ];

    // Keep these for table views (search/filter)
    protected $appends = [
        'current_class_level_name',
        'current_section_name',
        // Removed: full_name, age, gender, phone, photo_url → now from profile/avatar
    ];

    protected array $hiddenTableColumns = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $globalFilterFields = [
        'current_class_level_name',
        'current_section_name',
        // These will still work via relationship in tableQuery()
        'profile.full_name',
        'profile.phone',
    ];

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    /**
     * Get the polymorphic Profile that owns this Student record.
     */
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

    /**
     * Shortcut: Get the User through the profile.
     */
    public function user()
    {
        return $this->profile()->with('user')->select('user_id');
    }

    public function schoolSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(
            Guardian::class,
            'student_guardian_pivot',
            'student_id',
            'guardian_id'
        )->withTimestamps();
    }

    public function classSections(): BelongsToMany
    {
        return $this->belongsToMany(
            ClassSection::class,
            'student_class_section_pivot',
            'student_id',
            'class_section_id'
        )
            ->withPivot('academic_session_id')
            ->withTimestamps();
    }

    public function attendance(): MorphMany
    {
        return $this->morphMany(AttendanceLedger::class, 'attendable');
    }

    // =================================================================
    // CURRENT ACADEMIC CONTEXT
    // =================================================================

    public function currentClassSection(): ?ClassSection
    {
        $currentSession = app(\App\Services\AcademicSessionService::class)->currentSession();

        if (!$currentSession) {
            return null;
        }

        return $this->classSections()
            ->wherePivot('academic_session_id', $currentSession->id)
            ->first();
    }

    public function currentClassLevel(): ?ClassLevel
    {
        return $this->currentClassSection()?->classLevel;
    }

    // =================================================================
    // ACCESSORS – ONLY ACADEMIC CONTEXT (no personal data here!)
    // =================================================================

    public function getCurrentClassLevelNameAttribute(): ?string
    {
        return $this->currentClassLevel()?->display_name
            ?? $this->currentClassLevel()?->name;
    }

    public function getCurrentSectionNameAttribute(): ?string
    {
        $section = $this->currentClassSection();
        return $section
            ? ($section->classLevel?->name . '-' . $section->name)
            : null;
    }

    // =================================================================
    // MAGIC ACCESSORS – Personal data comes from Profile
    // =================================================================

    /**
     * Full name from profile.
     */
    public function getFullNameAttribute(): string
    {
        return $this->profile?->full_name ?? 'Unknown Student';
    }

    public function getAgeAttribute(): ?int
    {
        return $this->profile?->age;
    }

    public function getGenderAttribute(): ?string
    {
        return $this->profile?->gender;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->profile?->phone;
    }

    /**
     * Avatar – now powered by HasAvatar trait (Spatie Media Library)
     */
    public function getPhotoUrlAttribute(): string
    {
        return $this->avatarUrl('medium', $this->profile?->gender);
    }

    // =================================================================
    // SCOPES
    // =================================================================

    public function scopeInSessionAndSection($query, int $sessionId, int $sectionId)
    {
        return $query->whereHas('classSections', function ($q) use ($sessionId, $sectionId) {
            $q->where('class_section_id', $sectionId)
                ->wherePivot('academic_session_id', $sessionId);
        });
    }

    public function scopeActive($query)
    {
        return $query->whereHas('profile.user', fn($q) => $q->whereNull('deleted_at'));
    }

    // =================================================================
    // BOOT – CASCADE SOFT DELETE
    // =================================================================

    /**
     * When a Student is soft-deleted:
     *   → Delete their Profile
     *   → Soft-delete their User (optional – you decide)
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (self $student) {
            // If hard-deleting (forceDelete), skip cascade
            if ($student->isForceDeleting()) {
                return;
            }

            // 1. Delete the Profile (this will also delete media via HasAvatar trait)
            if ($profile = $student->profile) {
                $profile->delete(); // This triggers HasAvatar::boot() → clears avatar
            }

            // 2. Optional: Soft-delete the User if they have no other profiles
            if ($user = $student->user?->first()) {
                $otherProfiles = $user->profiles()->where('id', '!=', $profile?->id)->count();

                if ($otherProfiles === 0) {
                    $user->delete(); // Soft delete user
                }
            }
        });

        // Optional: Restore cascade
        static::restoring(function (self $student) {
            if ($profile = $student->profile()->withTrashed()->first()) {
                $profile->restore();
            }
        });
    }
}
