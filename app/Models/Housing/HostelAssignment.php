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
 * Model representing a hostel assignment in the school management system.
 *
 * Tracks student assignments to hostel rooms.
 *
 * @property int $id Auto-incrementing primary key.
 * @property int $hostel_room_id Associated room ID.
 * @property int $student_id Associated student ID.
 * @property string $status Assignment status (e.g., checked-in, checked-out).
 * @property \Illuminate\Support\Carbon $check_in_date Check-in date.
 * @property \Illuminate\Support\Carbon|null $check_out_date Check-out date.
 * @property string|null $notes Additional notes.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class HostelAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\Housing\HostelAssignmentFactory> */
    use HasFactory, LogsActivity, HasTableQuery, SoftDeletes, BelongsToSchool;

    protected $table = 'hostel_assignments';

    protected $fillable = [
        'hostel_room_id',
        'student_id',
        'status',
        'check_in_date',
        'check_out_date',
        'notes',
    ];

    protected $casts = [
        'status' => 'string',
        'check_in_date' => 'datetime',
        'check_out_date' => 'datetime',
    ];

    protected array $hiddenTableColumns = [
        'id',
        'hostel_room_id',
        'student_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $globalFilterFields = [
        'status',
        'notes',
    ];

    public function room()
    {
        return $this->belongsTo(HostelRoom::class, 'hostel_room_id');
    }

    public function student()
    {
        return $this->belongsTo('App\Models\Student');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('hostel_assignment')
            ->setDescriptionForEvent(fn($event) => "Hostel assignment {$event}: Student ID {$this->student_id} to Room ID {$this->hostel_room_id}")
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public static function getSchoolIdColumn(): string
    {
        return 'hostel_room.hostel.school_id';
    }
}
