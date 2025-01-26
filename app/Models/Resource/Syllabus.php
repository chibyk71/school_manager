<?php

namespace App\Models\Resource;

use App\Traits\BelongsToSchool;
use FarhanShares\MediaMan\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Syllabus extends Model
{
    use HasMedia, BelongsToSchool, LogsActivity;
    
    protected $table = 'syllabi';

    protected $fillable = [
        'school_id',
        'class_level_id',
        'subject_id',
        'term_id',
        'topic',
        'sub_topic',
        'description',
        'status',
        'options'
    ];

    protected $casts = [
        'options' => 'json'
    ];

    public function getOption(string $option)
    {
        return array_get($this->options, $option);
    }

    public function classLevel()
    {
        return $this->belongsTo('App\Models\Academic\ClassLevel', 'class_level_id');
    }

    public function subject()
    {
        return $this->belongsTo('App\Models\Academic\Subject', 'subject_id');
    }

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logOnly(['*'])
            ->useLogName('Syllabus')
            ->setDescriptionForEvent(fn(string $eventName) => "This model has been {$eventName}");
    }

    public function term()
    {
        return $this->belongsTo('App\Models\Academic\Term', 'term_id');
    }
}
