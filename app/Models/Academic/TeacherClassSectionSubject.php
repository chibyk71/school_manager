<?php

namespace App\Models\Academic;

use App\Models\Employee\Staff;
use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TeacherClassSectionSubject extends Pivot
{
    use LogsActivity, HasConfig;

    public $incrementing = true;

    protected $primaryKey = 'id';

    protected $fillable = [
        'teacher_id',
        'class_section_id',
        'subject_id',
    ];

    protected $appends = [
        'role'
    ];

    protected $table = 'teacher_class_section_subjects';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    public function getRoleAttribute()
    {
        return $this->configs()->all()->latest();
    }

    public function teacher()
    {
        return $this->belongsTo(Staff::class, 'teacher_id');
    }

    public function classSection()
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
