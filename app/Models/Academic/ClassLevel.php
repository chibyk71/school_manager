<?php

namespace App\Models\Academic;

use App\Models\SchoolSection;
use App\Traits\BelongsToSections;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassLevel extends Model
{
    /** @use HasFactory<\Database\Factories\ClassLevelFactory> */
    use HasFactory;

    protected $fillable = ['name','description','display_name', 'school_section_id'];

    public function schoolSection()
    {
        return $this->belongsTo(SchoolSection::class);
    }

    public function classSections()
    {
        return $this->hasMany(ClassSection::class);
    }
}
