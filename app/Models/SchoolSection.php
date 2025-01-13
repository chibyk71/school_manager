<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolSection extends Model
{
    /** @use HasFactory<\Database\Factories\SchoolSectionFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'name'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
