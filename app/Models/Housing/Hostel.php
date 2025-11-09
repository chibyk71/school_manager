<?php

namespace App\Models\Housing;

use App\Models\Employee\Staff;
use App\Models\Model;
use App\Models\School;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use App\Traits\HasCustomFields;
use App\Traits\HasConfig;
use App\Traits\BelongsToSections;
use App\Traits\HasAddress;
use App\Traits\HasTransaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\SchemalessAttributes\SchemalessAttributes;

/**
 * Model representing a hostel in the school management system.
 *
 * Manages hostel buildings (e.g., Boys’ Hostel) with support for multi-tenancy.
 *
 * @property int $id Auto-incrementing primary key.
 * @property int|null $school_id Associated school ID.
 * @property string $name Hostel name.
 * @property string|null $description Hostel description.
 * @property int|null $staff_id Warden or supervisor ID.
 * @property array|null $options Additional options (e.g., amenities).
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read School $school
 * @property-read Staff|null $warden
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Housing\HostelRoom[] $rooms
 * @property-read SchemalessAttributes $extra_attributes
 */
class Hostel extends Model
{
    use HasFactory, LogsActivity, HasTableQuery, SoftDeletes, BelongsToSchool, HasCustomFields, HasUuids, BelongsToSections, HasAddress;

    protected $table = 'hostels';

    protected $fillable = [
        'school_id',
        'name',
        'description',
        'staff_id',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    protected array $hiddenTableColumns = [
        'id',
        'school_id',
        'options',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $globalFilterFields = [
        'name',
        'description',
    ];

    /**
     * Get the school associated with the hostel.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the warden assigned to the hostel.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function warden()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * Get the rooms in the hostel.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function rooms()
    {
        return $this->hasMany(HostelRoom::class);
    }

    /**
     * Get activity log options.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('hostel')
            ->setDescriptionForEvent(fn($event) => "Hostel {$event}: {$this->name}")
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
