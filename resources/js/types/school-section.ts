// resources/js/types/school-section.ts

/**
 * SchoolSection Types — Production-Ready
 *
 * Single source of truth for all TypeScript shapes related to SchoolSection
 * (high-level academic divisions: Pre-Nursery, Nursery, Primary, JSS, SSS, etc.).
 *
 * ── Alignment With Backend ────────────────────────────────────────────────
 * All shapes mirror their backend counterparts exactly:
 *   SchoolSection          ↔ SchoolSectionResource::toArray()
 *   SchoolSectionMinimal   ↔ SchoolSectionController::options() map
 *   SchoolSectionFormData  ↔ StoreSchoolSectionRequest / UpdateSchoolSectionRequest
 *   SchoolSectionTemplate  ↔ SchoolSectionController::templates() response
 *   SchoolSectionPageProps ↔ SchoolSectionController::index() Inertia props
 *
 * ── Field Naming ─────────────────────────────────────────────────────────
 * sort_order — NOT sequence. Locked during migration design to match
 * CustomField, Grade, and DynamicEnum conventions in this codebase.
 *
 * ── Date Fields ──────────────────────────────────────────────────────────
 * The Resource returns both a formatted display string (e.g. "Jan 15, 2026")
 * and a raw ISO string for each date. Components use the formatted string
 * for display and the raw string for date pickers or relative time.
 *
 * ── Source Field ─────────────────────────────────────────────────────────
 * source: 'template' | 'custom'
 *   'template' = created from config-defined preset, name/display_name/
 *                short_code unchanged from template
 *   'custom'   = created manually OR template fields were edited
 * Frontend uses this to show a badge (e.g. "Template" chip on DataTable rows)
 * and to disable certain edit hints in the form modal.
 *
 * ── IDs ──────────────────────────────────────────────────────────────────
 * All IDs are UUID strings. Never numbers.
 *
 * ── Counts ───────────────────────────────────────────────────────────────
 * class_levels_count and students_count are optional — only present when
 * the controller called withCount(). Components must handle their absence
 * gracefully (fallback to 0 or hide count display).
 *
 * @see App\Models\SchoolSection
 * @see App\Http\Resources\SchoolSectionResource
 * @see App\Http\Controllers\Settings\SchoolSectionController
 * @see App\Http\Requests\StoreSchoolSectionRequest
 * @see App\Http\Requests\UpdateSchoolSectionRequest
 * @see App\Http\Requests\StoreFromTemplatesRequest
 * @see App\Http\Requests\BulkSchoolSectionRequest
 */

import type { ColumnDefinition } from './datatables';

// ──────────────────────────────────────────────────────────────────────────
// Core Model Shape
// ──────────────────────────────────────────────────────────────────────────

/**
 * Full SchoolSection shape — mirrors SchoolSectionResource::toArray().
 *
 * Used in:
 *   - AdvancedDataTable rows (index page)
 *   - Show page detail panel
 *   - Edit modal pre-fill
 *   - Any component receiving a full section object
 */
export interface SchoolSection {
    // ── Identity ──────────────────────────────────────────────────────
    id: string;
    name: string;
    display_name: string;
    short_code: string;
    description: string | null;

    // ── State ─────────────────────────────────────────────────────────
    is_active: boolean;

    /**
     * 'template' = created from a config preset, tracked fields unchanged.
     * 'custom'   = created manually or template fields were edited.
     * Frontend uses this for badge display and edit hint text.
     */
    source: 'template' | 'custom';

    /**
     * Position in ordered list. 10-gap convention (10, 20, 30...).
     * Used by drag-and-drop reorder on the index page.
     */
    sort_order: number;

    // ── Counts ────────────────────────────────────────────────────────
    // Optional — only present when controller called withCount().
    // Always falls back to 0 in SchoolSectionResource so these are
    // effectively always present in practice, but typed as optional
    // for defensive component code.
    class_levels_count?: number;
    students_count?: number;

    // ── Dates — formatted ─────────────────────────────────────────────
    // Human-readable (e.g. "Jan 15, 2026") — use for display only.
    created_at: string | null;
    deleted_at: string | null;   // non-null = soft-deleted record

    // Machine-readable ISO strings — use for date pickers, relative time.
    created_at_raw: string | null;
    deleted_at_raw: string | null;

    // ── Relationships ─────────────────────────────────────────────────
    // Only present when explicitly eager-loaded (controller called
    // $section->load('classLevels')). Absent on list responses.
    class_levels?: ClassLevelMinimal[];
}

/**
 * Minimal class level shape used inside SchoolSection.class_levels.
 * Full ClassLevel types live in their own type file.
 */
export interface ClassLevelMinimal {
    id: string;
    name: string;
    display_name?: string;
}

// ──────────────────────────────────────────────────────────────────────────
// Lightweight / Select Shapes
// ──────────────────────────────────────────────────────────────────────────

/**
 * Lightweight shape returned by the options() endpoint.
 * Used in SectionPicker, AsyncSelect, and any dropdown component.
 *
 * Matches SchoolSectionController::options() map output:
 *   { id, name, short_code, label, value }
 */
export interface SchoolSectionOption {
    id: string;
    name: string;          // display_name ?? name from model
    short_code: string;
    label: string;         // "{display_name} ({short_code})" — for PrimeVue Select
    value: string;         // same as id — for PrimeVue option-value binding
}

/** Convenience alias for arrays of options */
export type SchoolSectionOptions = SchoolSectionOption[];

// ──────────────────────────────────────────────────────────────────────────
// Form Shapes
// ──────────────────────────────────────────────────────────────────────────

/**
 * Form data shape for create/edit modal.
 *
 * Mirrors StoreSchoolSectionRequest and UpdateSchoolSectionRequest validated fields.
 * school_id and source are NEVER included — backend controls both.
 *
 * sort_order is optional — backend Observer auto-assigns when absent.
 * is_active is optional on create (defaults to true server-side).
 */
export interface SchoolSectionFormData {
    name: string;
    display_name: string;
    short_code: string;
    description?: string | null;
    sort_order?: number | null;
    is_active?: boolean;
}

/**
 * Validation error shape for SchoolSectionFormData.
 * Keys match form field names. Values are the first error message string.
 * Sourced from Inertia form.errors or axios 422 response.
 */
export type SchoolSectionFormErrors = Partial<Record<keyof SchoolSectionFormData, string>>;

// ──────────────────────────────────────────────────────────────────────────
// Template Shapes
// ──────────────────────────────────────────────────────────────────────────

/**
 * Shape of a single template from the templates() endpoint.
 *
 * Mirrors SchoolSectionController::templates() response item:
 *   { key, name, display_name, short_code, description, sort_order, available }
 *
 * available: false = this template already exists for this school.
 * The modal disables (or hides) unavailable templates.
 */
export interface SchoolSectionTemplate {
    key: string;           // config key e.g. 'primary', 'jss'
    name: string;          // machine name stored in DB e.g. 'Primary'
    display_name: string;
    short_code: string;
    description: string;
    sort_order: number;
    available: boolean;    // false = already created for this school
}

/**
 * Shape of the full templates() endpoint response.
 */
export interface SchoolSectionTemplatesResponse {
    templates: SchoolSectionTemplate[];
    available_count: number;
}

/**
 * Payload sent to storeFromTemplates().
 * Only template keys — backend resolves all other data from config.
 */
export interface StoreFromTemplatesPayload {
    keys: string[];        // e.g. ['primary', 'jss', 'sss']
}

// ──────────────────────────────────────────────────────────────────────────
// Bulk Operation Shapes
// ──────────────────────────────────────────────────────────────────────────

/**
 * Action values accepted by BulkSchoolSectionRequest.
 */
export type BulkSectionAction = 'toggle' | 'delete' | 'restore';

/**
 * Payload sent for bulk operations (destroy, restore, bulkToggle).
 * Maps to BulkSchoolSectionRequest validation rules.
 */
export interface BulkSectionPayload {
    action: BulkSectionAction;
    ids: string[];
    is_active?: boolean;   // required only when action = 'toggle'
}

/**
 * JSON response shape from bulk operation endpoints
 * (destroy, restore, forceDestroy, bulkToggle, reorder).
 */
export interface BulkSectionResponse {
    message: string;
    count?: number;        // affected row count
    updated?: number;      // for reorder response
}

// ──────────────────────────────────────────────────────────────────────────
// Page Props
// ──────────────────────────────────────────────────────────────────────────

/**
 * Inertia props passed to Settings/Sections/Index.vue.
 *
 * Mirrors SchoolSectionController::index() Inertia::render() props.
 * Columns come from HasTableQuery — same shape as other DataTable pages.
 */
export interface SchoolSectionsPageProps {
    initialData: SchoolSection[];
    totalRecords: number;
    columns: ColumnDefinition<SchoolSection>[];
    globalFilterables: string[];
}

/**
 * Inertia props passed to Settings/Sections/Show.vue.
 */
export interface SchoolSectionShowPageProps {
    section: SchoolSection;
}

// ──────────────────────────────────────────────────────────────────────────
// Modal Props
// ──────────────────────────────────────────────────────────────────────────

/**
 * Props for the SectionFormModal (create / edit single section).
 */
export interface SectionFormModalProps {
    /** Existing section for edit mode. Null or absent = create mode. */
    section?: SchoolSection | null;
    mode: 'create' | 'edit';
}

/**
 * Props for the SectionFromTemplatesModal (bulk create from templates).
 */
export interface SectionFromTemplatesModalProps {
    /** Pre-loaded templates from templates() endpoint. */
    templates: SchoolSectionTemplate[];
    available_count: number;
}

// ──────────────────────────────────────────────────────────────────────────
// Utility / Guard
// ──────────────────────────────────────────────────────────────────────────

/**
 * Type guard — checks if a value is a full SchoolSection (not minimal).
 * Use when a component accepts both full and minimal shapes.
 */
export function isFullSchoolSection(
    section: SchoolSection | SchoolSectionOption
): section is SchoolSection {
    return 'source' in section && 'is_active' in section;
}

/**
 * Derives whether a section is currently in the trash.
 * Avoids spreading deleted_at !== null checks across templates.
 */
export function isTrashed(section: SchoolSection): boolean {
    return section.deleted_at !== null;
}

/**
 * Derives whether a section was created from a config template.
 */
export function isFromTemplate(section: SchoolSection): boolean {
    return section.source === 'template';
}

/**
 * Derives whether a section has any children (class levels or students).
 * Used to show a warning before delete attempts.
 */
export function hasDependents(section: SchoolSection): boolean {
    return (section.class_levels_count ?? 0) > 0
        || (section.students_count ?? 0) > 0;
}
