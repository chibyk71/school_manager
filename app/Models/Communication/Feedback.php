<?php

namespace App\Models\Communication;

use App\Traits\BelongsToSchool;
use App\Traits\HasConfig;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Model representing feedback in the school management system.
 *
 * Feedback is school-scoped and can be submitted by various users (students, parents, teachers, etc.).
 *
 * @property string $id UUID primary key.
 * @property string|null $school_id Associated school ID.
 * @property string $feedbackable_id Morph ID for feedbackable entity.
 * @property string $feedbackable_type Morph type for feedbackable entity.
 * @property string|null $handled_by User ID who reviewed the feedback.
 * @property string $status Feedback status (pending, reviewed, resolved).
 * @property string $subject Feedback subject.
 * @property string $message Feedback message.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Feedback extends Model
{
    use HasFactory, LogsActivity, HasTableQuery, SoftDeletes, BelongsToSchool, HasConfig;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'feedback';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'school_id',
        'feedbackable_id',
        'feedbackable_type',
        'handled_by',
        'status',
        'subject',
        'message',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'category',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'id',
        'school_id',
        'feedbackable_id',
        'feedbackable_type',
        'handled_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'subject',
        'message',
    ];

    /**
     * Get the feedbackable entity associated with this feedback.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function feedbackable()
    {
        return $this->morphTo();
    }

    /**
     * Get the user who handled the feedback.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function handledBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'handled_by');
    }

    /**
     * Get the feedback category (Complaint, Suggestion, Appreciation).
     *
     * @return string|null
     */
    public function getCategoryAttribute(): ?string
    {
        return $this->addConfig('category', null)->value ?? null;
    }

    /**
     * Scope a query to only include handled feedback.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHandled($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Scope a query to only include pending feedback.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include reviewed feedback.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    /**
     * Get the options for logging changes to the model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('feedback')
            ->setDescriptionForEvent(function ($event) {
                return "Feedback {$event}: {$this->subject}";
            })
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
