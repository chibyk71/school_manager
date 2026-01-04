<?php
/**
 * app/Models/DynamicEnum.php
 *
 * Eloquent model representing a customizable "enum-like" option list (Dynamic Enum).
 *
 * Features / Problems Solved:
 * - Centralizes storage and querying for dynamic dropdown/radio options that need to be
 *   customizable per school (tenant) while falling back to system-wide defaults.
 * - Replaces the subset of rows in the generic `configs` table that were previously used
 *   for enum-style options (title, gender, profile_type, address type, etc.).
 * - Provides clean, dedicated scopes for retrieving enums visible to a school
 *   (global + school-specific overrides) and filtering by the model they apply to.
 * - Full integration with existing app standards:
 *     • UUID primary keys
 *     • BelongsToSchool trait for automatic school_id assignment and global scoping
 *     • HasTableQuery for powerful DataTable support (sorting, filtering, full/window modes)
 *     • Spatie Activitylog for audit trail of changes
 *     • HasFactory for easy testing/seeding
 * - Casts `options` JSON column to PHP array for type-safe usage.
 * - Hidden/default-hidden columns configured for clean table displays.
 * - Global search fields defined for the DataTable global filter.
 *
 * Fits into the DynamicEnums Module:
 * - Acts as the core data access layer.
 * - Used by HasDynamicEnum trait to fetch visible options for a model/property.
 * - Used by admin CRUD (DynamicEnumController + DataTable) to list/manage definitions.
 * - Used by custom validation rule InDynamicEnum to enforce allowed values.
 * - Scoped queries ensure multi-tenant isolation while allowing school overrides.
 */

namespace App\Models;

use App\Models\Model; // Your custom base model (extends Illuminate\Database\Eloquent\Model)
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DynamicEnum extends Model
{
    use HasFactory,
        LogsActivity,
        HasTableQuery,
        HasUuids,
        BelongsToSchool;

    /**
     * The table associated with the model.
     */
    protected $table = 'dynamic_enums';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',        // machine name (e.g., 'gender')
        'label',       // UI label (e.g., 'Gender')
        'applies_to',  // Fully qualified model class (e.g., App\Models\Profile)
        'description',
        'color',
        'options',     // JSON array of [{value: string, label: string, color?: string}]
        'school_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'options' => 'array',
    ];

    /**
     * Columns that should never be exposed in table responses.
     */
    protected array $hiddenTableColumns = [
        'id',
        'school_id',
        'created_at',
        'updated_at',
    ];

    /**
     * Columns that are sent but hidden by default in the DataTable (user can show them).
     */
    protected array $defaultHiddenColumns = [
        'description',
        'applies_to',
    ];

    /**
     * Fields used for the global free-text search in DataTables.
     */
    protected array $globalFilterFields = [
        'name',
        'label',
        'description',
    ];

    /* ------------------------------------------------------------------ */
    /* Scopes – visibility and filtering                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Scope: All enums visible to a school – system-wide (school_id null) OR belonging to the school.
     */
    public function scopeVisibleToSchool(Builder $query, ?string $schoolId = null): Builder
    {
        return $query->where(function (Builder $q) use ($schoolId) {
            $q->whereNull('school_id')
              ->orWhere('school_id', $schoolId);
        });
    }

    /**
     * Scope: Only system-wide (global) enums.
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('school_id');
    }

    /**
     * Scope: Only enums belonging to a specific school.
     */
    public function scopeForSchool(Builder $query, ?string $schoolId = null): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope: Enums that apply to a specific model class.
     */
    public function scopeForModel(Builder $query, string $modelClass): Builder
    {
        return $query->where('applies_to', $modelClass);
    }

    /**
     * Scope: Combine visibility + model filtering (most common use case).
     */
    public function scopeVisibleForModel(Builder $query, string $modelClass, ?string $schoolId = null): Builder
    {
        return $query->visibleToSchool($schoolId)->forModel($modelClass);
    }

    /* ------------------------------------------------------------------ */
    /* Activity logging                                                   */
    /* ------------------------------------------------------------------ */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('dynamic_enum')
            ->setDescriptionForEvent(fn(string $event) => "Dynamic enum {$event}: {$this->name} ({$this->applies_to})")
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
