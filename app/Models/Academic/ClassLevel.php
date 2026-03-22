<?php

/**
 * ClassLevel Model
 *
 * Represents a single academic year/stage within a school section.
 * Examples: JSS 1, Primary 6, Form 1, Year 7, Grade 3.
 *
 * Architecture decisions:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Uses BelongsToPrimaryModel (NOT BelongsToSchool) because ClassLevel has no
 *   direct school_id column. Tenant scoping flows through:
 *   class_levels.school_section_id → school_sections.school_id
 *   The BelongsToPrimaryModel trait declares SchoolSection as the primary model,
 *   which applies the ParentModelScope for all queries automatically.
 *
 * - Does NOT use BelongsToSections — that trait is for models that are assigned
 *   to many sections via a pivot (e.g. Grade scale → many sections). ClassLevel
 *   belongs to exactly ONE section via a simple belongsTo foreign key.
 *
 * - HasTableQuery powers the AdvancedDataTable on both the section detail page
 *   (ClassLevelsTab.vue) and the global settings view (Settings/Academic/ClassLevels.vue).
 *
 * Relationships:
 * ─────────────────────────────────────────────────────────────────────────────
 * - belongsTo SchoolSection (primary owner)
 * - hasMany ClassSection (the actual classrooms/streams, e.g. JSS 1A, JSS 1B)
 *   Note: ClassSection is a future module — relationship defined here so other
 *   code can reference it without touching this model again.
 *
 * Scoping:
 * ─────────────────────────────────────────────────────────────────────────────
 * - scopeForSection(): filters by section, used heavily in the controller
 * - scopeActive(): filters is_active = true, used in dropdowns across the app
 * - scopeOrdered(): orders by sequence ASC, always use this for display/promotion
 *
 * Activity logging:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Logs name, display_name, alias, sequence, max_arms, is_active changes
 * - Only logs dirty attributes to keep the log clean
 */

namespace App\Models\Academic;

use App\Models\SchoolSection;
use App\Traits\BelongsToPrimaryModel;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClassLevel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use BelongsToPrimaryModel;  // Scoping via school_section → school (NOT BelongsToSchool)
    use HasTableQuery;
    use LogsActivity;

    // ─── Table & fillable ─────────────────────────────────────────────────────

    protected $table = 'class_levels';

    protected $fillable = [
        'school_section_id',
        'name',
        'display_name',
        'alias',
        'description',
        'sequence',
        'max_arms',
        'is_active',
    ];

    protected $casts = [
        'sequence'   => 'integer',
        'max_arms'   => 'integer',
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ─── HasTableQuery configuration ──────────────────────────────────────────

    /**
     * Columns never sent to the frontend DataTable.
     * school_section_id is kept hidden since section context
     * is always known from the route — no need to display it.
     */
    protected array $hiddenTableColumns = [
        'deleted_at',
        'description', // shown in detail/modal, not in table row
    ];

    /**
     * Columns hidden in the table by default but user can toggle on.
     */
    protected array $defaultHiddenColumns = [
        'alias',
        'display_name',
        'updated_at',
    ];

    /**
     * Fields searched when the user types in the global search box.
     */
    protected array $globalFilterFields = [
        'name',
        'display_name',
        'alias',
    ];

    // ─── BelongsToPrimaryModel requirement ────────────────────────────────────

    /**
     * Declares the relationship to the primary model that owns this resource.
     * BelongsToPrimaryModel uses this to apply ParentModelScope, ensuring all
     * ClassLevel queries are automatically scoped to the current school via
     * the school_section relationship.
     */
    public function getRelationshipToPrimaryModel(): string
    {
        return 'schoolSection';
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The section this class level belongs to.
     * This is the primary ownership relationship — every class level
     * lives inside exactly one school section.
     */
    public function schoolSection(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SchoolSection::class, 'school_section_id');
    }

    /**
     * The individual classrooms/streams under this level.
     * Example: JSS 1 → [JSS 1A, JSS 1B, JSS 1C]
     * ClassSection is a future module — relationship defined now so other
     * services can reference it without needing to modify this model later.
     */
    public function classSections(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClassSection::class, 'class_level_id');
    }

    // ─── Query scopes ─────────────────────────────────────────────────────────

    /**
     * Filter to a specific school section.
     * Always use this when listing levels on the section detail page.
     *
     * @example ClassLevel::forSection($sectionId)->ordered()->get()
     */
    public function scopeForSection(Builder $query, string $sectionId): Builder
    {
        return $query->where('school_section_id', $sectionId);
    }

    /**
     * Only return active class levels.
     * Use in dropdowns, student assignment forms, timetable builders, etc.
     * Never use in admin management views — those need to see inactive levels too.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Order by sequence ascending.
     * Always use this for any display or promotion logic.
     * Without this, JSS 3 might appear before JSS 1.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sequence', 'asc');
    }

    /**
     * Find the next level in the promotion path.
     * Used by PromotionService to determine where a student moves after session end.
     * Returns null if this is the final level in the section (graduated).
     *
     * @example $nextLevel = ClassLevel::nextAfter($currentLevel)
     */
    public function scopeNextAfter(Builder $query, ClassLevel $current): Builder
    {
        return $query
            ->where('school_section_id', $current->school_section_id)
            ->where('sequence', '>', $current->sequence)
            ->orderBy('sequence', 'asc');
    }

    // ─── Computed attributes ──────────────────────────────────────────────────

    /**
     * The best available label for display in dropdowns and badges.
     * Uses alias if set, falls back to name.
     * Example: alias = "JS1", name = "JSS 1" → returns "JS1"
     */
    public function getShortLabelAttribute(): string
    {
        return $this->alias ?? $this->name;
    }

    /**
     * The most descriptive available label.
     * Uses display_name if set, falls back to name.
     * Example: display_name = "Junior Secondary One" → returns that
     */
    public function getFullLabelAttribute(): string
    {
        return $this->display_name ?? $this->name;
    }

    // ─── Activity log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'display_name',
                'alias',
                'sequence',
                'max_arms',
                'is_active',
            ])
            ->logOnlyDirty()
            ->setDescriptionForEvent(
                fn(string $event) => "Class level \"{$this->name}\" was {$event}"
            );
    }
}
