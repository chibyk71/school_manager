<?php

namespace App\Models\Academic;

use App\Models\SchoolSection;
use App\Traits\BelongsToSections;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Subject extends Model
{
    /** @use HasFactory<\Database\Factories\SubjectFactory> */
    use HasFactory, SoftDeletes, LogsActivity, BelongsToSections, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'code',
        'credit',
        'is_elective',
        'options'
    ];

    protected $casts = [
        'is_elective' => 'boolean',
        'options' => 'json'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('subject')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
