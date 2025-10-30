<?php

namespace App\Models;

use App\Models\Academic\Student;
use App\Traits\BelongsToSchool;
use App\Traits\HasCustomFields;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Guardian
 *
 * Represents a guardian in the school management system, linked to a user and students.
 * Supports custom fields and tenancy scoping.
 *
 * @package App\Models
 */
class Guardian extends Model
{
    /** @use HasFactory<\Database\Factories\GuardianFactory> */
    use HasFactory, BelongsToSchool, HasCustomFields, HasTableQuery, SoftDeletes, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'school_id',
    ];

    /**
     * Get the user that owns the guardian.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the students (children) associated with the guardian.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardian_pivot', 'guardian_id', 'student_id');
    }
}
