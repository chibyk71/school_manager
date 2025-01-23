<?php

namespace App\Models\Misc;

use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Model;

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
