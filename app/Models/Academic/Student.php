<?php

namespace App\Models\Academic;

use App\Models\Guardian;
use App\Models\Misc\AttendanceLedger;
use App\Models\SchoolSection;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasCustomFields;
use App\Traits\HasTableQuery;
use FarhanShares\MediaMan\Traits\HasMedia;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    /** @use HasFactory<\Database\Factories\StudentFactory> */
    use HasFactory, BelongsToSchool, HasCustomFields, HasTableQuery, HasUuids, SoftDeletes, HasMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'school_id',
        'school_section_id',
    ];

    protected $appends = [
        'current_class_level_name',
        'current_section_name',
    ];

    protected array $hiddenTableColumns = [
        'school_id',
        'school_section_id',
        'user_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $globalFilterFields = [
        'current_class_level_name',
        'current_section_name',
    ];

    /**
     * Get the user that owns the student.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the school section that owns the student.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
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
        return $this->belongsToMany(Guardian::class, 'student_guardian_pivot', 'student_id', 'guardian_id');
    }

    /**
     * Get the class sections associated with the student.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
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

    /**
     * Get the student's current class section based on the active academic session.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany|ClassSection|null
     */
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

    /**
     * Get the student's current class level name (e.g., "JSS1", "SSS2").
     *
     * @return string|null
     */
    public function getCurrentClassLevelNameAttribute(): ?string
    {
        return $this->currentClassLevel()?->display_name ?? $this->currentClassLevel()?->name;
    }

    /**
     * Get the student's current section name (e.g., "JSS1-A").
     *
     * @return string|null
     */
    public function getCurrentSectionNameAttribute(): ?string
    {
        $section = $this->currentClassSection();
        return $section ? ($section->classLevel?->name . '-' . $section->name) : null;
    }

    /**
     * Scope: Students in a specific academic session and class section.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $sessionId
     * @param int $sectionId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInSessionAndSection($query, int $sessionId, int $sectionId)
    {
        return $query->whereHas('classSections', function ($q) use ($sessionId, $sectionId) {
            $q->where('class_section_id', $sectionId)
                ->wherePivot('academic_session_id', $sessionId);
        });
    }
}
