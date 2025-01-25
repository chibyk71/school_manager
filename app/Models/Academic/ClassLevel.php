<?php

namespace App\Models\Academic;

use App\Models\SchoolSection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassLevel extends Model
{
    /** @use HasFactory<\Database\Factories\ClassLevelFactory> */
    use HasFactory;

    protected $fillable = ['name', 'school_section_id'];

    public function schoolSection()
    {
        return $this->belongsTo(SchoolSection::class);
    }

    public function classLevels()
    {
        return $this->hasMany(ClassLevel::class);
    }
}
