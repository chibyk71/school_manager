<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DynamicEnumOption — first-class option row + Phase 2 identity protection.
 *
 * Identity (immutable after create): id, dynamic_enum_id, value.
 * Mutable presentation: label, sort_order, color, icon.
 * Lifecycle flags: is_active, is_required (managed via lifecycle service).
 *
 * value is the stable machine identity within the definition.
 * Physical deletion is not the normal option lifecycle (deactivate instead).
 */
class DynamicEnumOption extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'dynamic_enum_options';

    protected $fillable = [
        'dynamic_enum_id',
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
            if ($option->isDirty(['dynamic_enum_id', 'value'])) {
                throw new \RuntimeException(
                    'DynamicEnumOption identity fields (dynamic_enum_id, value) are immutable after creation.'
                );
            }

            // Domain invariant: required options must remain active.
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
}
