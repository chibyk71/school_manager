<?php

namespace App\Models\Exam;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssessmentType extends Model
{
    use BelongsToSchool, LogsActivity;

    protected $table = 'assessment_types';

    protected $fillable = ['name', 'description', 'status', 'weight', 'school_id'];

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    public function getActivityLogOptions()
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['updated_at']);
    }
}
