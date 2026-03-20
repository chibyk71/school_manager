<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\Student;
use App\Models\Employee\Staff;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laratrust\Models\Team;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * SchoolSection Model — Production-Ready
 *
 * Represents a major academic division/stream within a school.
 * Examples: Pre-Nursery, Nursery, Primary, Junior Secondary, Senior Secondary.
 *
 * These are HIGH-LEVEL organizational blocks — NOT individual classes.
 * Individual classes (JSS 1, Primary 4A, SS2 Science) belong to ClassLevel,
 * which holds school_section_id as its parent foreign key.
 *
 * ── Architecture ────────────────────────────────────────────────────────
 * Every row belongs to exactly ONE school (school_id always required).
 * There are no global/shared rows — each school fully owns their sections.
 *
 * Sections are created in two ways:
 *   1. From predefined templates (config/school_section_templates.php)
 *      → source = 'template'
 *   2. Custom-created from scratch by a school admin
 *      → source = 'custom'
 *
 * When a school edits a template-sourced section, SchoolSectionObserver
 * automatically changes source to 'custom'. Two states only, ever.
 *
 * ── Role in the System ──────────────────────────────────────────────────
 * 1. LARATRUST TEAM
 *    SchoolSection extends Laratrust\Models\Team. This makes sections the
 *    "team" boundary for role/permission scoping. Example:
 *      - "teacher" role scoped to JSS only
 *      - "principal" role scoped to all sections (no team = school-wide)
 *    Laratrust calls SchoolSection::find($id) internally during permission
 *    checks. BelongsToSchool global scope is intentionally active during
 *    these calls — it ensures a user can never use a section from another
 *    school as their team context (correct security behavior).
 *    DO NOT remove BelongsToSchool from this model thinking it conflicts
 *    with Laratrust. It does not — it enforces correct tenant isolation.
 *
 * 2. HIERARCHICAL PARENT
 *    SchoolSection → ClassLevel → Student/Subject/Timetable/Results
 *    Sections are the top of the academic hierarchy within a school.
 *
 * 3. RESOURCE SCOPING (via BelongsToSections trait)
 *    Grades, exams, report templates, and other resources can be scoped
 *    to specific sections via the polymorphic sectionables pivot table.
 *    This is separate from the hasMany ClassLevel relationship.
 *
 * 4. DATATABLE INTEGRATION (via HasTableQuery)
 *    Enables AdvancedDataTable with server-side pagination, search,
 *    sort, and filter. Configuration via protected array properties below.
 *
 * ── Source Field Behavior ───────────────────────────────────────────────
 * source = 'template' → created from config/school_section_templates.php
 * source = 'custom'   → created from scratch or edited after template import
 * Mutation from template → custom is handled by SchoolSectionObserver,
 * not here, to keep the model clean of observer-level concerns.
 *
 * ── Deletion Safety ─────────────────────────────────────────────────────
 * The migration uses restrictOnDelete on the school_id FK. This means the
 * DB will refuse to delete a school that still has sections. Cleanup must
 * go through SchoolSectionService::deleteAllForSchool() which handles
 * Laratrust role_user pivot cleanup before section deletion.
 * Sections themselves use soft deletes — hard deletion should be rare and
 * always go through the service layer, never directly.
 *
 * ── Key Relationships ───────────────────────────────────────────────────
 * belongsTo    School          (via BelongsToSchool trait)
 * hasMany      ClassLevel
 * hasManyThrough Student       (via ClassLevel)
 * belongsToMany Staff          (via staff_school_section_pivot)
 *
 * @property string      $id
 * @property string      $school_id
 * @property string      $name           canonical slug e.g. junior_secondary
 * @property string      $display_name   UI label e.g. Junior Secondary School
 * @property string      $short_code     badge/report code e.g. JSS
 * @property string|null $description
 * @property bool        $is_active
 * @property int         $sort_order     display order, lower = first
 * @property string      $source         template|custom
 * @property string|null $deleted_at
 *
 * @see App\Services\SchoolSectionService   (createFromTemplates, deleteAllForSchool)
 * @see App\Observers\SchoolSectionObserver (source mutation, cache invalidation)
 * @see App\Policies\SchoolSectionPolicy    (authorization rules)
 * @see config/school_section_templates.php (predefined template data)
 * @see config/laratrust.php                (teams table = school_sections)
 */
class SchoolSection extends Team
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasTableQuery,
        LogsActivity,
        Filterable,
        Sortable;

    /**
     * Explicitly defined to prevent Laratrust\Models\Team from resolving
     * a different table name. Team's parent may have its own $table property
     * that could shadow Laravel's conventional pluralization.
     *
     * @var string
     */
    protected $table = 'school_sections';

    /**
     * The attributes that are mass assignable.
     * school_id is included here because SchoolSectionService injects it
     * explicitly during createFromTemplates(). BelongsToSchool also sets
     * it automatically on the creating event as a safety net.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'display_name',
        'short_code',
        'description',
        'is_active',
        'sort_order',
        'source',
    ];

    /**
     * Attribute casting.
     * Note: school_id is intentionally NOT cast to 'string' here.
     * HasUuids handles UUID columns automatically. Explicit string cast
     * on UUID foreign keys is redundant and can cause subtle type issues.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * Default attribute values.
     * These ensure newly created sections always have sensible defaults
     * even when individual fields are not explicitly passed.
     *
     * sort_order = 99 → new sections appear last until manually reordered
     * is_active  = true → sections are active on creation
     * source     = 'custom' → default assumption; 'template' injected
     *              explicitly by SchoolSectionService::createFromTemplates()
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 99,
        'is_active' => true,
        'source' => 'custom',
    ];

    // ────────────────────────────────────────────────────────────────────
    // HasTableQuery Configuration
    // ────────────────────────────────────────────────────────────────────

    /**
     * Fields included in the global free-text search box in AdvancedDataTable.
     * Searched with LIKE %term% when user types in the search input.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'name',
        'display_name',
        'short_code',
        'description',
    ];

    /**
     * Columns that are NEVER sent to the frontend in DataTable responses.
     * school_id is excluded because BelongsToSchool already scopes all
     * queries — sending it to the frontend would be redundant and leaks
     * internal tenant identifiers unnecessarily.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'school_id',
    ];

    /**
     * Columns sent to the frontend but hidden by default.
     * Users can toggle these visible via the column chooser.
     * source is hidden by default — it's an internal tracking field,
     * not relevant to most admin workflows.
     *
     * @var array<string>
     */
    protected array $defaultHiddenColumns = [
        'source',
        'description',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // ────────────────────────────────────────────────────────────────────
    // Laratrust Team Interface
    // ────────────────────────────────────────────────────────────────────

    /**
     * The foreign key name Laratrust uses when referencing this team
     * in the role_user and permission_user pivot tables.
     * Must match the column name defined in config/laratrust.php
     * under foreign_keys.team.
     *
     * @return string
     */
    public static function modelForeignKey(): string
    {
        return 'school_section_id';
    }

    // ────────────────────────────────────────────────────────────────────
    // Activity Logging
    // ────────────────────────────────────────────────────────────────────

    /**
     * Configure Spatie activity log behavior.
     * Logs all fillable field changes, but only when something actually
     * changed (logOnlyDirty). Ignores updated_at-only touches to avoid
     * noise in the audit trail.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('school_section')
            ->setDescriptionForEvent(
                fn(string $event) => "School section '{$this->display_name}' was {$event}"
            );
    }

    // ────────────────────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────────────────────

    /**
     * The granular class levels that belong to this section.
     * Examples: JSS 1, Primary 4 Blue, SS2 Science Stream.
     * This is the direct child in the academic hierarchy.
     */
    public function classLevels()
    {
        return $this->hasMany(ClassLevel::class);
    }

    /**
     * All students enrolled in any class level under this section.
     * Traverses: SchoolSection → ClassLevel → Student.
     * Useful for section-level reporting and headcounts.
     */
    public function students()
    {
        return $this->hasManyThrough(Student::class, ClassLevel::class);
    }

    /**
     * Staff members assigned to work within this section.
     * Uses a dedicated pivot table separate from the main staff assignments.
     * Named staffs() (not staff()) to match existing codebase references.
     *
     * @see database/migrations/xxxx_create_staff_school_section_pivot_table.php
     */
    public function staffs()
    {
        return $this->belongsToMany(
            Staff::class,
            'staff_school_section_pivot'
        )->withTimestamps();
    }

    // ────────────────────────────────────────────────────────────────────
    // Scopes
    // ────────────────────────────────────────────────────────────────────

    /**
     * Filter to active sections only.
     * Use this in all dropdowns, pickers, and resource creation forms
     * to prevent assigning resources to inactive sections.
     * Inactive sections are hidden from users but data is preserved.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Filter to sections created from predefined templates.
     * Useful for analytics (how many schools used template X) and
     * for the "available templates" filter in getAvailableTemplates().
     * Note: edited templates will have source='custom' after Observer runs.
     */
    public function scopeFromTemplate(Builder $query): Builder
    {
        return $query->where('source', 'template');
    }

    /**
     * Filter to custom-created sections (includes edited templates).
     * Used in analytics to understand how many schools deviated from
     * the predefined template list.
     */
    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('source', 'custom');
    }

    /**
     * Order sections for consistent UI listing.
     * Primary order: sort_order (explicit position).
     * Secondary order: display_name (alphabetical fallback for ties).
     * Consistent with how CustomField, Grade, DynamicEnum order themselves.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }

    // ────────────────────────────────────────────────────────────────────
    // Accessors & Helpers
    // ────────────────────────────────────────────────────────────────────

    /**
     * Human-readable label for dropdowns and UI components.
     * Returns display_name with fallback to name if display_name is empty.
     * Used by AsyncSelect and other picker components.
     *
     * @return string
     */
    public function getLabelAttribute(): string
    {
        return $this->display_name ?: $this->name;
    }

    /**
     * Check if this section originated from a predefined template.
     * Returns false for sections that were template-sourced but later
     * edited (Observer changes source to 'custom' on first edit).
     *
     * @return bool
     */
    public function isFromTemplate(): bool
    {
        return $this->source === 'template';
    }

    /**
     * Check if this section is custom (created from scratch or edited
     * from a template). The inverse of isFromTemplate().
     *
     * @return bool
     */
    public function isCustom(): bool
    {
        return $this->source === 'custom';
    }
}
