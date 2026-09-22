<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DynamicEnumOption — tenant baseline or sparse school overlay (Phase 2R).
 *
 * Ownership:
 *   school_id IS NULL  → tenant baseline option
 *   school_id = S      → school override of same value, or school-only option
 *
 * Identity (immutable after create): id, dynamic_enum_id, value, school_id.
 * value is the stable business identity stored on consuming records.
 */
class DynamicEnumOption extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'dynamic_enum_options';

    protected $fillable = [
        'dynamic_enum_id',
        'school_id',
        'value',
        'label',
        'sort_order',
        'is_active',
        'is_required',
        'color',
        'icon',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_required' => 'boolean',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
        'is_required' => false,
    ];

    protected static function booted(): void
    {
        static::updating(function (self $option) {
            if ($option->isDirty(['dynamic_enum_id', 'value', 'school_id'])) {
                throw new \RuntimeException(
                    'DynamicEnumOption identity fields (dynamic_enum_id, value, school_id) are immutable after creation.'
                );
            }

            if ($option->is_required && ! $option->is_active) {
                throw new \RuntimeException(
                    'A required DynamicEnumOption cannot be inactive.'
                );
            }
        });
    }

    public function dynamicEnum(): BelongsTo
    {
        return $this->belongsTo(DynamicEnum::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isTenantOption(): bool
    {
        return $this->school_id === null;
    }

    public function isSchoolOption(): bool
    {
        return $this->school_id !== null;
    }
}
