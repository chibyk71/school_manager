<?php

namespace App\Models\Misc;

use App\Models\Model;
use App\Traits\HasConfig;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Document extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'file_path',
        'attachable_id',
        'attachable_type',
    ];
}
