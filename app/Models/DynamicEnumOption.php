<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DynamicEnumOption — Phase 1 domain foundation.
 *
 * First-class option row belonging to a DynamicEnum definition.
 *
 * value is the stable machine identity within the definition.
 * label / color / icon are presentation metadata.
 * is_active / is_required defaults support future Phase 2 lifecycle rules;
 * Phase 1 only persists them.
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

    /**
     * Database defaults are the source of truth; these attributes ensure
     * new instances without explicit values still surface the expected defaults
     * before persistence (consistent with migration defaults).
     */
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
        'is_required' => false,
    ];

    public function dynamicEnum(): BelongsTo
    {
        return $this->belongsTo(DynamicEnum::class);
    }
}
