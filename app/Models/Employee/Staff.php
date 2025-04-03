<?php

namespace App\Models\Employee;

use App\Models\Misc\AttendanceLedger;
use App\Models\SchoolSection;
use App\Models\User;
use App\Traits\BelongsToSections;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use SpykApp\LaravelCustomFields\Traits\HasCustomFields;
use SpykApp\LaravelCustomFields\Traits\LoadCustomFields;

class Staff extends Model
{
    /** @use HasFactory<\Database\Factories\StaffFactory> */
    use HasFactory, HasCustomFields, LoadCustomFields, BelongsToSections;

    protected $fillable = [
        'user_id',
        'department_role_id'
    ];

    /**
     * Get the user that owns the Staff
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The schoolSection that belong to the Staff
     *
    //  * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    // public function schoolSection()
    // {
    //     return $this->belongsToMany(SchoolSection::class, 'staff_school_section_pivot');
    // }

    /**
     * the attendance for the staff
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<AttendanceLedger, Staff>
     */
    public function attendance() {
        return $this->morphMany(AttendanceLedger::class, 'attendable');
    }
    
}
