<?php

namespace App\Models\Housing;

use App\Models\Employee\Staff;
use App\Models\Model;
use App\Models\School;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use App\Traits\HasCustomFields;
use App\Traits\BelongsToSections;
use App\Traits\HasAddress;
use App\Traits\HasTransaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\SchemalessAttributes\SchemalessAttributes;

class Hostel extends Model
{
    use HasFactory, LogsActivity, HasTableQuery, SoftDeletes, BelongsToSchool, HasCustomFields, HasUuids, BelongsToSections, HasAddress;

    protected $fillable = [
        'school_id',
        'name',
        'type',
        'capacity',
        'description',
        'warden_id',
        'custom_fields',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'capacity' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('hostel')
            ->logFillable()
            ->logOnlyDirty();
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function warden()
    {
        return $this->belongsTo(Staff::class, 'warden_id');
    }
}
