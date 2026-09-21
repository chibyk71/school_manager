<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DynamicEnum — domain foundation + Phase 2 identity protection.
 *
 * Ownership:
 *   school_id = null  → tenant-wide / default definition
 *   school_id = S     → school-specific definition for the same key
 *
 * Identity (immutable after create): id, school_id, key.
 * Mutable presentation: label, description.
 *
 * Intentionally does NOT use BelongsToSchool / SchoolScope.
 * Lifecycle operations live in DynamicEnumLifecycleService (Phase 2).
 * Effective resolution is Phase 3.
 */
class DynamicEnum extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'dynamic_enums';

    protected $fillable = [
        'school_id',
        'key',
        'label',
        'description',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $definition) {
            if ($definition->isDirty(['school_id', 'key'])) {
                throw new \RuntimeException(
                    'DynamicEnum identity fields (school_id, key) are immutable after creation.'
                );
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(DynamicEnumOption::class)->orderBy('sort_order');
    }

    public function isDefault(): bool
    {
        return $this->school_id === null;
    }

    public function isSchoolOwned(): bool
    {
        return $this->school_id !== null;
    }
}
