<?php

namespace App\Models\Academic;

use App\Models\Academic\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClassSection extends Model
{
    /** @use HasFactory<\Database\Factories\ClassSectionFactory> */
    use HasFactory;

    protected $fillable = [
        'class_level_id',
        'name',
        'capacity',
        'status',
    ];

    protected $appends = [
        'no_students',
    ];

    /**
     * Get the number of students in the ClassSection
     *
     * @return int
     */
    public function getNoStudentsAttribute(): int
    {
        return $this->students()->count();
    }

    /**
     * Get the ClassLevel that owns the ClassSection
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ClassLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class, 'class_level',);
    }

    /**
     * The students that belong to the ClassSection
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_class_section_pivot');
    }
}
