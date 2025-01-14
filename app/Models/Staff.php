<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use SpykApp\LaravelCustomFields\Traits\HasCustomFields;
use SpykApp\LaravelCustomFields\Traits\LoadCustomFields;

class Staff extends Model
{
    /** @use HasFactory<\Database\Factories\StaffFactory> */
    use HasFactory, HasCustomFields, LoadCustomFields;

    protected $fillable = [
        'user_id'
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function schoolSection()
    {
        return $this->belongsToMany(SchoolSection::class, 'staff_school_section_pivot');
    }
}
