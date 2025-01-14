<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClassSection extends Model
{
    /** @use HasFactory<\Database\Factories\ClassSectionFactory> */
    use HasFactory;

    protected $fillable = [
        'class_level_id',
        'name'
    ];

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
