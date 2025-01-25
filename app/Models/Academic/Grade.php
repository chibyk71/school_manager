<?php

namespace App\Models\Academic;

use App\Models\SchoolSection;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use App\Models\School;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Grade extends Model
{
    use LogsActivity, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'school_section_id',
        'name',
        'code',
        'min_score',
        'max_score',
        'remark'
    ];

    public function getActivityLogOptions() {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'min_score', 'max_score', 'remark'])
            ->logOnlyDirty();
    }

    public function schoolSection()
    {
        return $this->belongsTo(SchoolSection::class);
    }
}
