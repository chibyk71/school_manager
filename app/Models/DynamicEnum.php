<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DynamicEnum — Phase 1 domain foundation.
 *
 * Represents a finite configurable vocabulary whose meaning belongs to the
 * tenant/school rather than application code.
 *
 * Ownership:
 *   school_id = null  → tenant-wide / default definition
 *   school_id = S     → school-specific definition for the same key
 *
 * Identity is (school_id, key). Options are first-class rows (DynamicEnumOption).
 *
 * Intentionally does NOT use BelongsToSchool / SchoolScope: both default and
 * school-specific rows for the same key must remain queryable independently.
 * Effective resolution is Phase 3.
 *
 * Phase 1 responsibilities only: identity, ownership, persistence, relationships,
 * casting. No resolution, lifecycle, validation orchestration, or API behavior.
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

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(DynamicEnumOption::class)->orderBy('sort_order');
    }
}
