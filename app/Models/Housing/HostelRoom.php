<?php

namespace App\Models\Housing;

use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing a hostel room in the school management system.
 *
 * Manages individual rooms within a hostel.
 *
 * @property int $id Auto-incrementing primary key.
 * @property int $hostel_id Associated hostel ID.
 * @property string $room_number Room identifier (e.g., A1).
 * @property int $capacity Maximum number of students.
 * @property string|null $description Room description.
 * @property array|null $options Room amenities (e.g., AC, Wi-Fi).
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class HostelRoom extends Model
{
    /** @use HasFactory<\Database\Factories\Housing\HostelRoomFactory> */
    use HasFactory, LogsActivity, HasTableQuery, SoftDeletes, BelongsToSchool;

    protected $table = 'hostel_rooms';

    protected $fillable = [
        'hostel_id',
        'room_number',
        'capacity',
        'description',
        'options',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'options' => 'array',
    ];

    protected array $hiddenTableColumns = [
        'id',
        'hostel_id',
        'options',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $globalFilterFields = [
        'room_number',
        'description',
    ];

    public function hostel()
    {
        return $this->belongsTo(Hostel::class);
    }

    public function assignments()
    {
        return $this->hasMany(HostelAssignment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('hostel_room')
            ->setDescriptionForEvent(fn($event) => "Hostel room {$event}: {$this->room_number} in Hostel ID {$this->hostel_id}")
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public static function getSchoolIdColumn(): string
    {
        return 'hostel.school_id';
    }
}
