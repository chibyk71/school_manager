<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Support\CustomFieldType;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class CustomField
 *
 * Represents a single custom field definition.
 * Can be:
 *   - Global preset (school_id = null) — visible to all schools unless overridden
 *   - School-specific override (school_id filled) — takes priority over global
 *
 * Key behaviors:
 *   - Protects global fields from being edited/deleted by school admins
 *   - Invalidates cache on any change
 *   - Provides merged/effective view via scopeEffectiveFor()
 *   - Logs changes via Spatie Activitylog
 *
 * Relationships:
 *   - responses() → all values ever saved for this field
 *   - model()    → polymorphic (rarely used — usually the field belongs to the system)
 */
class CustomField extends Model
{
    use HasFactory,
        BelongsToSchool,
        HasTableQuery,
        SoftDeletes,
        Filterable,
        Sortable,
        LogsActivity;

    protected $fillable = [
        'name',
        'label',
        'placeholder',
        'rules',
        'classes',
        'field_type',
        'options',
        'default_value',
        'description',
        'hint',
        'sort',
        'category',
        'extra_attributes',
        'field_options',
        'cast_as',
        'has_options',
        'model_type',
        'school_id',
        // File/image support
        'file_path',
        'file_paths',
        'file_type',
        'max_file_size_kb',
        'allowed_extensions',
        // Advanced
        'conditional_rules',
        'preset_key',
        'is_preset',
        'visibility_scope',
        'role_restrictions',
    ];

    protected $casts = [
        'rules' => 'array',
        'classes' => 'array',
        'options' => 'array',
        'extra_attributes' => 'array',
        'field_options' => 'array',
        'file_paths' => 'array',
        'allowed_extensions' => 'array',
        'conditional_rules' => 'array',
        'role_restrictions' => 'array',
        'has_options' => 'boolean',
        'is_preset' => 'boolean',
        'max_file_size_kb' => 'integer',
    ];

    protected $appends = [
        'required',
        'is_global',
        'is_override',
    ];

    // ──────────────────────────────────────────────────────────────
    // Activity Logging (Spatie)
    // ──────────────────────────────────────────────────────────────
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly([
                'name',
                'label',
                'field_type',
                'rules',
                'options',
                'school_id',
                'model_type',
                'preset_key',
                'is_preset',
            ])
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('custom-field');
    }

    // ──────────────────────────────────────────────────────────────
    // Boot / Global protections
    // ──────────────────────────────────────────────────────────────
    protected static function booted()
    {
        // Default ordering
        static::addGlobalScope(
            'ordered',
            fn(Builder $q) =>
            $q->orderBy('sort')->orderBy('id')
        );

        // Protect global fields (school_id = null) from school-level users
        static::updating(function (self $field) {
            if (is_null($field->school_id) && GetSchoolModel() !== null) {
                Log::warning('Blocked attempt to update global custom field', [
                    'field_id' => $field->id,
                    'user_id' => auth()->id(),
                ]);
                abort(403, 'Global default fields can only be modified by tenant/super administrators.');
            }
        });

        static::deleting(function (self $field) {
            if (is_null($field->school_id) && GetSchoolModel() !== null) {
                Log::warning('Blocked attempt to delete global custom field', [
                    'field_id' => $field->id,
                    'user_id' => auth()->id(),
                ]);
                abort(403, 'Global default fields cannot be deleted by school administrators.');
            }
        });
    }

    // ──────────────────────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────────────────────

    public function getRequiredAttribute(): bool
    {
        return in_array('required', $this->rules ?? [], true);
    }

    public function getIsGlobalAttribute(): bool
    {
        return is_null($this->school_id);
    }

    public function getIsOverrideAttribute(): bool
    {
        return !is_null($this->school_id);
    }

    // ──────────────────────────────────────────────────────────────
    // Scopes – Core merging logic
    // ──────────────────────────────────────────────────────────────

    /**
     * Get the effective (merged) fields for a given school and model type.
     *
     * Logic:
     *   1. Include all global fields (school_id = null)
     *   2. Include school overrides (school_id = given school)
     *   3. Order by school_id DESC → overrides come first
     *   4. Deduplicate by name (school override wins)
     *   5. Final sort by 'sort' column
     */
    public function scopeEffectiveFor(Builder $query, ?School $school, string $modelType): Collection
    {
        return $query
            ->where('model_type', $modelType)
            ->where(function ($q) use ($school) {
                $q->whereNull('school_id')
                    ->orWhere('school_id', $school?->id);
            })
            ->orderByRaw('school_id IS NULL ASC') // globals last
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->keyBy('name')          // school override wins (later in collection)
            ->values()               // reset keys
            ->sortBy('sort');        // enforce final display order
    }

    /**
     * Scope: Get the base query for effective fields (global + school overrides)
     * Returns Builder — ready for filters, sorts, pagination
     *
     * Use this when you need to apply additional query constraints (Purity, etc.)
     * For simple merged list without filters, use effectiveFor() which returns Collection
     */
    public function scopeEffectiveQuery(Builder $query, ?School $school, string $modelType): Builder
    {
        return $query
            ->where('model_type', $modelType)
            ->where(function ($q) use ($school) {
                $q->whereNull('school_id')
                    ->orWhere('school_id', $school?->id);
            })
            ->orderByRaw('school_id IS NULL ASC') // school overrides first
            ->orderBy('sort')
            ->orderBy('id');
    }

    public function scopeGlobalDefaults(Builder $query): Builder
    {
        return $query->whereNull('school_id');
    }

    public function scopeOverridesForSchool(Builder $query, School $school): Builder
    {
        return $query->where('school_id', $school->id);
    }

    // ──────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────

    public function model()
    {
        return $this->morphTo();
    }

    public function responses()
    {
        return $this->hasMany(CustomFieldResponse::class);
    }

    // ──────────────────────────────────────────────────────────────
    // Cache invalidation
    // ──────────────────────────────────────────────────────────────

    public function invalidateRelatedCache(): void
    {
        $tags = [
            'custom_fields',
            $this->school_id
            ? "custom_fields:school:{$this->school_id}"
            : 'custom_fields:global',
            "custom_fields:model:" . md5($this->model_type),
        ];

        Cache::tags($tags)->flush();

        if (!app()->environment('production')) {
            Log::debug('Custom field cache flushed', [
                'field_id' => $this->id,
                'school_id' => $this->school_id,
                'model_type' => $this->model_type,
            ]);
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(fn(self $model) => $model->invalidateRelatedCache());
        static::deleted(fn(self $model) => $model->invalidateRelatedCache());
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    public static function getSchoolIdColumn(): string
    {
        return 'school_id';
    }

    /**
     * Quick helper: is this field a file/image upload type?
     */
    public function isFileField(): bool
    {
        return CustomFieldType::tryFrom($this->field_type)?->isFileType() ?? false;
    }
}
