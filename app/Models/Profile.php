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
class Profile extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasTableQuery,
        LogsActivity,
        HasConfig;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'is_primary' => 'boolean',
    ];

    /**
     * Columns used for global search filtering in table queries.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'first_name',
        'last_name',
        'middle_name',
        'email',           // From User relationship
        'phone',
        'title',
        'profile_type',
        'school.name',     // Relation-based
    ];

    /**
     * Columns hidden from table output but still selectable in queries.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'deleted_at',
        'updated_at',
        'created_at',
        'profilable_id',
        'profilable_type',
        'user_id',
    ];

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    /**
     * Get the user this profile belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the polymorphic model (e.g., Student, Staff, Guardian).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function profilable(): MorphTo
    {
        return $this->morphTo();
    }

    // =================================================================
    // SCOPES
    // =================================================================

    /**
     * Scope: Filter profiles where profile_type is 'staff'.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStaff($query)
    {
        return $query->where('profile_type', 'staff');
    }

    /**
     * Scope: Filter profiles where profile_type is 'student'.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStudent($query)
    {
        return $query->where('profile_type', 'student');
    }

    /**
     * Scope: Filter profiles where profile_type is 'guardian'.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeGuardian($query)
    {
        return $query->where('profile_type', 'guardian');
    }

    /**
     * Scope: Filter profiles marked as primary for their user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    // =================================================================
    // ACCESSORS & MUTATORS
    // =================================================================

    /**
     * Get the full name including title.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->title} {$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    /**
     * Get the short name (first + last).
     *
     * @return string
     */
    public function getShortNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the age based on date_of_birth.
     *
     * @return int|null
     */
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    // =================================================================
    // ACTIVITY LOGGING
    // =================================================================

    /**
     * Get the activity log options for this model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('profile')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Profile [{$this->full_name}] was {$eventName}");
    }

    // =================================================================
    // CONFIGURABLE PROPERTIES (HasConfig Trait)
    // =================================================================

    /**
     * Define which properties are configurable via the HasConfig trait.
     *
     * @return array<string>
     */
    public function getConfigurableProperties(): array
    {
        return [
            'title',
            'gender',
            'profile_type',
        ];
    }

    // =================================================================
    // HELPER METHODS
    // =================================================================

    /**
     * Check if this profile is of type staff.
     *
     * @return bool
     */
    public function isStaff(): bool
    {
        return $this->profile_type === 'staff';
    }

    /**
     * Check if this profile is of type student.
     *
     * @return bool
     */
    public function isStudent(): bool
    {
        return $this->profile_type === 'student';
    }

    /**
     * Check if this profile is of type guardian.
     *
     * @return bool
     */
    public function isGuardian(): bool
    {
        return $this->profile_type === 'guardian';
    }

    /**
     * Mark this profile as primary for the user.
     * Demotes all other profiles to non-primary.
     *
     * @return bool
     */
    public function markAsPrimary(): bool
    {
        $this->user->profiles()->update(['is_primary' => false]);
        $this->update(['is_primary' => true]);

        return $this->is_primary;
    }

    /**
     * Cast the profilable relationship to its correct model instance.
     *
     * @return \Illuminate\Database\Eloquent\Model|\Eloquent
     */
    public function getProfilableModelAttribute()
    {
        return $this->profilable;
    }
}
