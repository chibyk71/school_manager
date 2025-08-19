<?php

namespace App\Models\Resource;

use App\Models\Academic\ClassLevel;
use App\Models\Academic\Subject;
use App\Models\Model;
use App\Traits\BelongsToSchool;
use FarhanShares\MediaMan\Traits\HasMedia;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BookList extends Model
{
    use HasMedia, BelongsToSchool, LogsActivity;

    protected $fillable = [
        'school_id',
        'class_level_id',
        'subject_id',
        'title',
        'author',
        'isbn',
        'edition',
        'description',
        'price',
    ];

    protected $casts = [
        'price' => 'float',
    ];

    public function getActivityLogOptions() {
        return LogOptions::defaults()
        ->logAll()
        ->logExcept(['updated_at'])
        ->useLogName('book_list');
    }

    public function classLevel()
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }   
}
