<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSection extends Model
{
    /** @use HasFactory<\Database\Factories\ClassSectionFactory> */
    use HasFactory;

    protected $fillable = [
        'class_level_id',
        'name'
    ];

    /**
     * Get the ClassLevel that owns the ClassSection
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ClassLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class, 'class_level',);
    }
}
