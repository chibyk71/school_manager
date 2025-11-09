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
 * Model representing a notice in the school management system.
 *
 * Notices are school-scoped (optional) and can be public or targeted to specific recipients.
 *
 * @property int $id Auto-incrementing primary key.
 * @property string $title Notice title.
 * @property string $body Notice content.
 * @property string|null $school_id Associated school ID (nullable for public notices).
 * @property string $sender_id User ID of the sender.
 * @property bool $is_public Whether the notice is public.
 * @property \Illuminate\Support\Carbon $effective_date Effective date of the notice.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Notice extends Model
{
    use HasFactory, LogsActivity, HasTableQuery, SoftDeletes, BelongsToSchool, HasConfig;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'body',
        'school_id',
        'sender_id',
        'is_public',
        'effective_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'effective_date' => 'datetime',
        'is_public' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    public function getConfigurableProperties(): array {
        return ['type',];
    }

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'id',
        'school_id',
        'sender_id',
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
        'title',
        'body',
    ];

    /**
     * Get the school associated with the notice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo(\App\Models\School::class);
    }

    /**
     * Get the sender of the notice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sender()
    {
        return $this->belongsTo(\App\Models\User::class, 'sender_id');
    }

    /**
     * Get the recipients of the notice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function recipients()
    {
        return $this->belongsToMany(\App\Models\User::class, 'notice_recipients', 'notice_id', 'user_id')
                    ->withPivot('is_read')
                    ->withTimestamps();
    }

    /**
     * Get the notice type (e.g., Announcement, Alert).
     * TODO add type seeder in config
     * @return string|null
     */
    // public function getTypeAttribute(): ?string
    // {
    //     return $this->addConfig('type', null)->value ?? null;
    // }

    /**
     * Get the options for logging changes to the model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('notice')
            ->setDescriptionForEvent(function ($event) {
                return "Notice {$event}: {$this->title}";
            })
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
