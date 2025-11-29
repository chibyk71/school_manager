<?php

namespace App\Models\Academic;

use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\Guardian;
use App\Models\Misc\AttendanceLedger;
use App\Models\SchoolSection;
use App\Models\Profile;
use App\Traits\BelongsToSchool;
use App\Traits\HasCustomFields;
use App\Traits\HasTableQuery;
use FarhanShares\MediaMan\Traits\HasMedia;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Student
 *
 * Represents a student in the school management system, linked to a user, school section, guardians, and class sections.
 * Supports custom fields and tenancy scoping.
 *
 * @package App\Models\Academic
 */
class Student extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasCustomFields,
        HasTableQuery,
        HasMedia;

    protected $fillable = [
        'school_section_id',
    ];

    protected $appends = [
        'current_class_level_name',
        'current_section_name',
        'full_name',
        'age',
        'gender',
        'phone',
    ];

    protected array $hiddenTableColumns = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $globalFilterFields = [
        'current_class_level_name',
        'current_section_name',
        'full_name',
    ];

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    public function profile(): HasOneThrough
    {
        return $this->hasOneThrough(
            Profile::class,
            Profile::class,
            'profilable_id',  // Foreign key on profiles table
            'id',             // Local key on profiles table
            'id',             // Local key on students table
            'profilable_id'
        )->where('profilable_type', static::class);
    }

    public function user()
    {
        return $this->profile()->select('user_id')->with('user');
    }

    public function schoolSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }

    /**
     * Get the guardians associated with the student.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
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

    /**
     * Get the attendance records for the student.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
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

    /**
     * Get the student's current class level (e.g., JSS1, SSS2) via their current section.
     *
     * @return \App\Models\Academic\ClassLevel|null
     */
    public function currentClassLevel(): ?ClassLevel
    {
        return $this->currentClassSection()?->classLevel;
    }

    // =================================================================
    // ACCESSORS — MOST USED IN DASHBOARD, REPORTS, SMS
    //

    public function getCurrentClassLevelNameAttribute(): ?string
    {
        return $this->currentClassLevel()?->display_name
            ?? $this->currentClassLevel()?->name;
    }

    /**
     * Get the student's current section name (e.g., "JSS1-A").
     *
     * @return string|null
     */
    public function getCurrentSectionNameAttribute(): ?string
    {
        $section = $this->currentClassSection();
        return $section
            ? ($section->classLevel?->name . '-' . $section->name)
            : null;
    }

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

    public function getPhotoUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('photo') ?: asset('images/student-avatar.png');
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
}