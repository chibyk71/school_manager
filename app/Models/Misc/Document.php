<?php

namespace App\Models\Misc;

use App\Models\Model;
use App\Traits\HasConfig;

class Document extends Model
{
    use HasConfig;

    protected $fillable = [
        'name',
        'description',
        'file_path',
        'attachable_id',
        'attachable_type',
    ];
}
