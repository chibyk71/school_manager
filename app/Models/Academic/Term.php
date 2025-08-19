<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Term extends Model
{
    /** @use HasFactory<\Database\Factories\Academic\TermFactory> */
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'academic_session_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'color',
        'options',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'options' => 'array',
    ];

    protected static $logAttributes = [
        'name',
        'start_date',
        'end_date',
        'status',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('term')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function getOption(string $option)
    {
        return array_get($this->options, $option);
    }
}
