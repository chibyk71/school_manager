<?php

namespace App\Models\Academic;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
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
        Filterable,
        Sortable,
        HasAvatar; // ← Replaces old HasMedia + getPhotoUrlAttribute()

    protected $fillable = [
        'school_section_id',
    ];

    // Keep these for table views (search/filter)
    protected $appends = [
        'current_class_level_name',
        'current_section_name',
        'student_id',           // ← NEW: Admission/Enrolment ID
        'roll_number',          // ← NEW: From pivot or custom field
        'parent_name',          // ← NEW: Primary guardian name
        'parent_phone',         // ← NEW: Primary guardian phone
        'today_attendance',     // ← NEW: Present/Absent/Late
        'fee_status',           // ← NEW: Paid/Due/Partial
        'status_badge',         // ← NEW: Active/Inactive/Left
    ];

    protected array $hiddenTableColumns = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // =================================================================
    // Make these fields searchable in global search
    // =================================================================
    protected array $globalFilterFields = [
        'student_id',
        'current_class_level_name',
        'current_section_name',
        'roll_number',
        'profile.full_name',
        'profile.phone',
        'parent_name',
        'parent_phone',
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
    // ACCESSORS – RICH TABLE DATA
    // =================================================================

    public function getStudentIdAttribute(): ?string
    {
        return $this->user?->enrollment_id;
    }

    public function getRollNumberAttribute(): ?string
    {
        // Option A: Stored in pivot (recommended) student_class_section_pivot.roll_number
        return $this->currentClassSection()?->pivot?->roll_number
            // Option B: Fallback to custom field
            ?? $this->getCustomFieldValue('roll_number')
            // Option C: Generate from name or ID (fallback)
            ?? null;
    }

    public function getParentNameAttribute(): ?string
    {
        $guardian = $this->guardians()->with('user')->first();
        return $guardian?->user?->name ?? $guardian?->profile?->full_name ?? null;
    }

    public function getParentPhoneAttribute(): ?string
    {
        $guardian = $this->guardians()->with('profile')->first();
        return $guardian?->profile?->phone ?? null;
    }

    public function getTodayAttendanceAttribute(): ?string
    {
        $today = today()->toDateString();
        $ledger = $this->attendance()
            ->whereDate('date', $today)
            ->first();

        return match ($ledger?->status ?? 'absent') {
            'present' => 'Present',
            'late'    => 'Late',
            'half_day'=> 'Half Day',
            'holiday' => 'Holiday',
            default   => 'Absent',
        };
    }

    public function getFeeStatusAttribute(): string
    {
        // Replace with your actual fee logic (e.g., via relationship or service)
        // This is a realistic placeholder
        $due = $this->outstandingFees(); // assume method exists

        if ($due <= 0) return 'Paid';
        if ($due < 5000) return 'Partial';
        return 'Due ₹' . number_format($due);
    }

    public function getStatusBadgeAttribute(): string
    {
        if ($this->trashed()) return 'Left';
        if (!$this->user?->hasVerifiedEmail() || !$this->user?->status) return 'Inactive';
        return 'Active';
    }

    public function getCurrentSectionNameAttribute(): ?string
    {
        $section = $this->currentClassSection();
        if (!$section) return null;

        $level = $section->classLevel?->display_name ?? $section->classLevel?->name;
        return $level . '-' . $section->name;
    }

    // Optional: Better photo with fallback
    public function getPhotoUrlAttribute(): string
    {
        return $this->avatarUrl('thumb', $this->profile?->gender);
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
