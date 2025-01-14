<?php
namespace App\Traits;

use App\Models\Tenant\Attendance;


trait HasAttendance
{
    /**
     * Define a polymorphic relationship for attendance.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function attendanceRecords()
    {
        return $this->morphMany(Attendance::class, 'attendable');
    }

    /**
     * Mark attendance as present.
     *
     * @param string|null $note
     * @return Attendance
     */
    public function markPresent($note = null)
    {
        return $this->attendanceRecords()->create([
            'status' => 'present',
            'note' => $note,
        ]);
    }

    /**
     * Mark attendance as absent.
     *
     * @param string|null $note
     * @return Attendance
     */
    public function markAbsent($note = null)
    {
        return $this->attendanceRecords()->create([
            'status' => 'absent',
            'note' => $note,
        ]);
    }

    /**
     * Mark attendance as late.
     *
     * @param string|null $note
     * @return Attendance
     */
    public function markLate($note = null)
    {
        return $this->attendanceRecords()->create([
            'status' => 'late',
            'note' => $note,
        ]);
    }
}
