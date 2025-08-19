<?php

namespace App\Models\Resource;

use App\Models\Model;

class SyllabusDetail extends Model
{
    protected $fillable = [
        'syllabus_id',
        'week',
        'objectives',
        'topic',
        'sub_topics',
        'description',
        'resources',
        'status'
    ];

    public function syllabus()
    {
        return $this->belongsTo(Syllabus::class);
    }
}
