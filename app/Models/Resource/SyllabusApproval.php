<?php

namespace App\Models\Resource;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class SyllabusApproval
 *
 * Represents an approval request for a syllabus in the school management system.
 *
 * @package App\Models\Resource
 * @property int $id
 * @property string $school_id
 * @property int $syllabus_id
 * @property string $requester_id
 * @property string|null $approver_id
 * @property string $status
 * @property string|null $comments
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class SyllabusApproval extends Model
{
    use BelongsToSchool, LogsActivity, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'syllabus_approvals';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'syllabus_id',
        'requester_id',
        'approver_id',
        'status',
        'comments',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Define the relationship with the syllabus.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function syllabus()
    {
        return $this->belongsTo(Syllabus::class);
    }

    /**
     * Define the relationship with the requester (staff).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function requester()
    {
        return $this->belongsTo(\App\Models\Employee\Staff::class, 'requester_id');
    }

    /**
     * Define the relationship with the approver (staff).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function approver()
    {
        return $this->belongsTo(\App\Models\Employee\Staff::class, 'approver_id');
    }

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('syllabus_approval')
            ->logFillable()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
