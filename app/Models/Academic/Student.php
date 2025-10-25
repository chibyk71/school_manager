<?php

namespace App\Models\Academic;

use App\Models\Guardian;
use App\Models\Misc\AttendanceLedger;
use App\Models\SchoolSection;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasCustomFields;
use App\Traits\HasTableQuery;
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
    use HasFactory, BelongsToSchool, HasCustomFields, HasTableQuery, HasUuids, SoftDeletes;

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
        return $this->belongsToMany(ClassSection::class, 'student_class_section_pivot', 'student_id', 'class_section_id');
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
}