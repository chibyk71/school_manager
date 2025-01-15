<?php

namespace App\Models\Academic;

use App\Models\SchoolSection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Subject extends Model
{
    /** @use HasFactory<\Database\Factories\SubjectFactory> */
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'code',
        'credit',
        'is_elective',
        'school_secttion_id',
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

    public function schoolSection()
    {
        return $this->belongsTo(SchoolSection::class);
    }
}
