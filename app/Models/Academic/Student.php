<?php

namespace App\Models\Academic;

use App\Models\Academic\ClassSection;
use App\Models\Misc\AttendanceLedger;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use SpykApp\LaravelCustomFields\Traits\HasCustomFields;
use SpykApp\LaravelCustomFields\Traits\LoadCustomFields;

class Student extends Model
{
    /** @use HasFactory<\Database\Factories\StudentFactory> */
    use HasFactory, HasCustomFields, LoadCustomFields, HasUuids;

    protected $fillable = [
        'user_id',
        'school_section_id'
    ];

    /**
     * Get the user that owns the Student
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the schoolSection that owns the Student
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function schoolSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }

    /**
     * The guardian that belong to the Student
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardian_pivot');
    }

    /**
     * The classSection that belong to the Student
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function classSections(): BelongsToMany
    {
        return $this->belongsToMany(ClassSection::class, 'student_class_section_pivot', 'user_id');
    }

    public function attendance() {
        return $this->morphMany(AttendanceLedger::class, 'attendable');
    }
    
}
