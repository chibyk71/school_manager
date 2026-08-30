<?php

namespace App\Models\Academic;

use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AcademicSession extends Model
{
    /** @use HasFactory<\Database\Factories\Academic\AcademicSessionFactory> */
    use HasFactory, HasUuids, SoftDeletes, BelongsToSchool, HasTableQuery, LogsActivity;

    protected $table = 'academic_sessions';

    protected $fillable = [
        'school_id',
        'name',
        'start_date',
        'end_date',
        'is_current',
        'status',
        'activated_at',
        'closed_at',
    ];

    protected $casts = [
        'start_date'    => 'date:Y-m-d',
        'end_date'      => 'date:Y-m-d',
        'is_current'    => 'boolean',
        'activated_at'  => 'datetime',
        'closed_at'     => 'datetime',
        'status'        => 'string',
    ];

    protected $hidden = [
        'school_id',
    ];

    protected array $hiddenTableColumns = [
        'school_id',
        'deleted_at',
        'created_at',
    ];

    protected array $defaultHiddenColumns = [
        'activated_at',
        'closed_at',
    ];

    protected array $globalFilterFields = [
        'name',
    ];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_UPCOMING  = 'upcoming';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_CLOSED    = 'closed';
    public const STATUS_ARCHIVED  = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_UPCOMING,
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
        self::STATUS_ARCHIVED,
    ];

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class, 'academic_session_id')
            ->orderBy('ordinal_number');
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function canModifyStartDate(): bool
    {
        return ! $this->isActive && $this->status !== self::STATUS_CLOSED;
    }

    public function getDurationAttribute(): ?string
    {
        if (! $this->start_date || ! $this->end_date) {
            return null;
        }

        $days = $this->start_date->diffInDays($this->end_date) + 1;

        return $this->start_date->format('M d, Y') . ' - ' .
               $this->end_date->format('M d, Y') .
               " ({$days} days)";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('academic_session')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "Academic session \"{$this->name}\" has been {$eventName}");
    }
}
