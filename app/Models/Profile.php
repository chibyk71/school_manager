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
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Conversions\Manipulations;


/**
 * Profile Model
 *
 * Represents a role-specific profile (student, staff, guardian) linked to a User.
 * Supports multi-role users across schools/branches via polymorphic relationship.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $profilable_id
 * @property string|null $profilable_type
 * @property string $school_id
 * @property string $profile_type        // 'staff', 'student', 'guardian'
 * @property string|null $title          // Mr, Mrs, Dr, etc.
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string $gender              // male, female, other
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property string|null $phone
 * @property string|null $address
 * @property bool $is_primary
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $profilable
 * @property-read \App\Models\School $school
 * @property-read string $full_name
 * @property-read string $short_name
 * @property-read int|null $age
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Profile tableQuery(\Illuminate\Http\Request $request, array $extraFields = [], array $customModifiers = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Profile staff()
 * @method static \Illuminate\Database\Eloquent\Builder|Profile student()
 * @method static \Illuminate\Database\Eloquent\Builder|Profile guardian()
 * @method static \Illuminate\Database\Eloquent\Builder|Profile primary()
 */
class Profile extends Model implements HasMedia
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasTableQuery,
        LogsActivity,
        HasConfig,
        InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'profilable_id',
        'profilable_type',
        'school_id',
        'profile_type',
        'title',
        'first_name',
        'last_name',
        'middle_name',
        'gender',
        'date_of_birth',
        'phone',
        'address',
        'is_primary',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_primary'    => 'boolean',
    ];

    protected $appends = [
        'full_name',
        'short_name',
        'age',
        'photo_url',
    ];

    protected array $globalFilterFields = [
        'first_name',
        'last_name',
        'middle_name',
        'phone',
        'title',
        'profile_type',
    ];

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profilable(): MorphTo
    {
        return $this->morphTo();
    }

    // =================================================================
    // MEDIA LIBRARY – AVATAR SUPPORT
    // =================================================================

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile() // Only one photo per profile
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('public');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        // Simplified to two conversions for better performance; use responsive images where needed
        $this
            ->addMediaConversion('thumb')
            ->fit(Fit::Crop, 100, 100)
            ->sharpen(10)
            ->performOnCollections('photo');

        $this
            ->addMediaConversion('medium')
            ->fit(Fit::Crop, 600, 600)
            ->performOnCollections('photo');
    }

    /**
     * Get the URL to the user's photo with fallback.
     */
    public function getPhotoUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('photo', 'medium')
            ?: asset('images/avatars/default-' . ($this->gender === 'female' ? 'female' : 'male') . '.png');
    }

    // =================================================================
    // SCOPES
    // =================================================================

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('profile_type', $type);
    }

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

    public function scopeForRole($query, string $role)
    {
        return $query->where('profile_type', $role);
    }

    // =================================================================
    // ACCESSORS
    // =================================================================

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->title,
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]);

        return trim(implode(' ', $parts)) ?: 'Unknown User';
    }

    public function getShortNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    // =================================================================
    // BOOT – DATA INTEGRITY PROTECTION
    // =================================================================

    protected static function boot(): void
    {
        parent::boot();

        // Prevent more than one primary profile per user
        static::saving(function (self $profile) {
            if ($profile->is_primary) {
                // If this profile is being set as primary, demote others
                if ($profile->isDirty('is_primary') && $profile->is_primary) {
                    $profile->user->profiles()
                        ->where('id', '!=', $profile->id)
                        ->update(['is_primary' => false]);
                }

                // If this was the only primary and now it's being turned off, pick another
                if ($profile->isDirty('is_primary') && !$profile->is_primary) {
                    $hasOtherPrimary = $profile->user->profiles()
                        ->where('id', '!=', $profile->id)
                        ->where('is_primary', true)
                        ->exists();

                    if (!$hasOtherPrimary && $profile->user->profiles()->count() > 1) {
                        $profile->user->profiles()
                            ->where('id', '!=', $profile->id)
                            ->orderBy('created_at')
                            ->first()
                            ?->update(['is_primary' => true]);
                    } elseif (!$hasOtherPrimary && $profile->user->profiles()->count() === 1) {
                        // Prevent demotion if this is the only profile
                        $profile->is_primary = true;
                    }
                }
            }
        });

        // When a profile is deleted, ensure another one becomes primary if needed
        static::deleted(function (self $profile) {
            if ($profile->is_primary) {
                $newPrimary = $profile->user->profiles()
                    ->where('is_primary', false)
                    ->first();

                if ($newPrimary) {
                    $newPrimary->update(['is_primary' => true]);
                }
            }
        });
    }

    // =================================================================
    // HELPER METHODS
    // =================================================================

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
        $this->user->profiles()->update(['is_primary' => false]);
        $this->update(['is_primary' => true]);

        return $this->is_primary;
    }

    // =================================================================
    // ACTIVITY LOGGING
    // =================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('profile')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Profile [{$this->full_name}] was {$eventName}");
    }

    public function getConfigurableProperties(): array
    {
        return ['title', 'gender', 'profile_type'];
    }
}
