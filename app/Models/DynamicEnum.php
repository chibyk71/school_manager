<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DynamicEnum — application-owned definition (Phase 2R).
 *
 * One definition per stable key (e.g. profile.gender). Administrators do not
 * create arbitrary definitions; keys are application identity.
 *
 * school_id on the definition row is retained for optional school-level
 * presentation of the definition itself. Option ownership is on DynamicEnumOption.
 *
 * Identity (immutable): id, key (and school_id when set for presentation rows).
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

    /** Tenant baseline options (school_id IS NULL). */
    public function tenantOptions(): HasMany
    {
        return $this->hasMany(DynamicEnumOption::class)
            ->whereNull('school_id')
            ->orderBy('sort_order');
    }

    /** Sparse overlay / school-only options for a specific school. */
    public function schoolOptions(string $schoolId): HasMany
    {
        return $this->hasMany(DynamicEnumOption::class)
            ->where('school_id', $schoolId)
            ->orderBy('sort_order');
    }

    public function isApplicationDefinition(): bool
    {
        return $this->school_id === null;
    }
}
