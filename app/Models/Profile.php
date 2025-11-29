<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use App\Traits\HasConfig;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Profile extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasTableQuery,
        LogsActivity,
        HasConfig;

    protected $fillable = [
        'user_id',
        'profilable_id',
        'profilable_type',
        'school_id',
        'profile_type',        // 'staff', 'student', 'guardian'
        'title',               // Mr, Mrs, Dr, Prof, etc.
        'first_name',
        'last_name',
        'middle_name',
        'gender',              // male, female, other
        'date_of_birth',
        'phone',
        'address',
        'is_primary',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_primary' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profilable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scopes
     */
    public function scopeStaff($query)
    {
        return $query->where('profile_type', 'staff');
    }

    public function scopeStudent($query)
    {
        return $query->where('profile_type', 'student');
    }

    public function scopeGuardian($query)
    {
        return $query->where('profile_type', 'guardian');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Accessors
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->title} {$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getShortNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /**
     * Activity Logging
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('profile')
            ->logFillable()
            ->setDescriptionForEvent(fn(string $eventName) => "{$this->full_name} profile was {$eventName}");
    }

    /**
     * Configurable Properties (for HasConfig trait)
     */
    public function getConfigurableProperties(): array
    {
        return [
            'title',
            'gender',
            'profile_type',
        ];
    }

    /**
     * Helper Methods
     */
    public function isStaff(): bool
    {
        return $this->profile_type === 'staff';
    }

    public function isStudent(): bool
    {
        return $this->profile_type === 'student';
    }

    public function isGuardian(): bool
    {
        return $this->profile_type === 'guardian';
    }

    public function markAsPrimary(): bool
    {
        // Demote others, promote this one
        $this->user->profiles()->update(['is_primary' => false]);
        $this->update(['is_primary' => true]);

        return $this->is_primary;
    }

    /**
     * Cast profilable to correct model
     */
    public function getProfilableModelAttribute()
    {
        return $this->profilable;
    }
}