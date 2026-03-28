/**
 * resources/js/types/class-section.ts
 *
 * ── Single Source of Truth ────────────────────────────────────────────────────
 * All TypeScript type definitions for the ClassSection module.
 *
 * Mirrors backend shapes exactly:
 *   ClassSection                  ↔ ClassSectionResource::toArray()
 *   ClassSectionMinimal           ↔ ClassSectionMinimalResource::toArray()
 *   TeacherSubjectAssignment      ↔ nested array in ClassSectionResource
 *   ClassSectionFormData          ↔ StoreClassSectionRequest / UpdateClassSectionRequest
 *   BulkGeneratePayload           ↔ BulkGenerateClassSectionRequest
 *   NamingPreset                  ↔ ClassSectionNamePresets::toFrontendArray()
 *   ClassSectionsPageProps        ↔ ClassSectionController::index() Inertia props
 *
 * ── Hierarchy Context ─────────────────────────────────────────────────────────
 *   School → SchoolSection → ClassLevel → ClassSection
 *   e.g.,   School → JSS   → JSS 1     → JSS 1A
 *
 * ── Naming Fields ─────────────────────────────────────────────────────────────
 *   name              The arm label only: "A", "B", "Diamond"
 *   display_name      The full label: "JSS 1A" — stored, overridable by admin
 *   display_name_stored  The raw DB value — null if using auto-computed
 *
 * This distinction matters for the edit modal:
 * - display_name_stored === null  → name was auto-generated; show "restore default" hint
 * - display_name_stored !== null  → admin customised it; editing name won't auto-update
 *
 * ── Usage ─────────────────────────────────────────────────────────────────────
 *   import type { ClassSection, ClassSectionFormData, ... } from '@/types/class-section'
 */

import type { ColumnDefinition } from './datatables';

// ──────────────────────────────────────────────────────────────────────────────
// Core Model Shape
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Full ClassSection shape — mirrors ClassSectionResource::toArray().
 * Used for DataTable rows, edit modal pre-fill, and detail views.
 */
export interface ClassSection {
    // ── Identity ──────────────────────────────────────────────────────────
    id: string; // UUID

    // ── Naming ────────────────────────────────────────────────────────────
    /** Arm label only: "A", "B", "Diamond" */
    name: string;

    /** Full display label: "JSS 1A", "Primary 2 Diamond" */
    display_name: string;

    /**
     * The raw stored value in DB. null = auto-computed (never manually set).
     * Frontend uses this to show/hide the "restore default name" option in edit modal.
     */
    display_name_stored: string | null;

    // ── Parent context ─────────────────────────────────────────────────────
    class_level_id: string;

    /** Included when classLevel relation is eager-loaded */
    class_level?: {
        id: string;
        name: string;
        display_name: string | null;
        school_section?: {
            id: string;
            name: string;
            display_name: string;
        } | null;
    } | null;

    // ── Physical room ──────────────────────────────────────────────────────
    room: string | null;

    // ── Capacity & enrollment ──────────────────────────────────────────────
    /** 0 = uncapped */
    capacity: number;
    is_uncapped: boolean;

    /** Only present when pre-loaded via withCount('students') */
    students_count?: number;
    is_at_capacity: boolean;
    /** null when uncapped */
    remaining_capacity: number | null;

    // ── Form teacher ───────────────────────────────────────────────────────
    form_teacher_id: string | null;
    form_teacher?: {
        id: string;
        full_name: string;
        staff_id_number: string | null;
    } | null;

    // ── Subject assignments ────────────────────────────────────────────────
    /** Only present on detail responses — not included in list/DataTable */
    teacher_subject_assignments?: TeacherSubjectAssignment[];

    /** Only present when pre-loaded via withCount */
    teacher_subject_assignments_count?: number;

    // ── Display order & status ────────────────────────────────────────────
    sort_order: number;
    status: ClassSectionStatus;
    is_active: boolean;

    // ── Soft delete ────────────────────────────────────────────────────────
    deleted_at: string | null;
    deleted_at_for_humans: string | null;
    is_trashed: boolean;

    // ── Timestamps ────────────────────────────────────────────────────────
    created_at: string | null;
    created_at_iso: string | null;
    updated_at: string | null;
    updated_at_iso: string | null;
}

export type ClassSectionStatus = 'active' | 'inactive';

// ──────────────────────────────────────────────────────────────────────────────
// Teacher-Subject Assignment
// ──────────────────────────────────────────────────────────────────────────────

/**
 * A single teacher-subject assignment within a class section.
 * Returned nested inside ClassSection.teacher_subject_assignments on detail views.
 */
export interface TeacherSubjectAssignment {
    id: number;
    teacher_id: string;
    subject_id: string;
    role: string | null;
    /** Human-readable role label computed by backend */
    role_label: string;
    teacher: {
        id: string;
        full_name: string;
    } | null;
    subject: {
        id: string;
        name: string;
        code: string | null;
    } | null;
}

// ──────────────────────────────────────────────────────────────────────────────
// Minimal / Dropdown Shape
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Lightweight shape for dropdowns, AsyncSelect, and option lists.
 * Mirrors ClassSectionMinimalResource::toArray().
 *
 * Includes `label` and `value` for direct PrimeVue Select compatibility.
 */
export interface ClassSectionOption {
    id: string;
    name: string;
    display_name: string;
    class_level_id: string;
    class_level_name?: string;

    // PrimeVue Select compatibility
    label: string;   // === display_name
    value: string;   // === id

    // Enrollment state (for smart dropdowns that disable full sections)
    status: ClassSectionStatus;
    is_active: boolean;
    capacity: number;
    is_uncapped: boolean;
    students_count?: number;
    is_at_capacity: boolean;
}

export type ClassSectionOptions = ClassSectionOption[];

// ──────────────────────────────────────────────────────────────────────────────
// Form Data Shapes
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Payload for creating a single class section manually.
 * Maps to StoreClassSectionRequest validated fields.
 */
export interface ClassSectionFormData {
    class_level_id: string;
    name: string;
    display_name?: string | null;
    room?: string | null;
    capacity?: number;
    form_teacher_id?: string | null;
    sort_order?: number | null;
    status?: ClassSectionStatus;
}

/**
 * Payload for updating an existing section.
 * All fields are optional (PATCH semantics — send only what changed).
 */
export type ClassSectionUpdateData = Partial<Omit<ClassSectionFormData, 'class_level_id'>>;

/**
 * Form errors shape — keys match ClassSectionFormData field names.
 */
export type ClassSectionFormErrors = Partial<Record<keyof ClassSectionFormData, string>>;

// ──────────────────────────────────────────────────────────────────────────────
// Bulk Generate
// ──────────────────────────────────────────────────────────────────────────────

/**
 * All available naming style keys (must match ClassSectionNamePresets PHP class).
 */
export type NamingStyleKey = 'alphabetic' | 'numeric' | 'precious' | 'virtues' | 'colours' | 'custom';

/**
 * A single naming preset definition — from ClassSectionNamePresets::toFrontendArray().
 */
export interface NamingPreset {
    label: string;
    description: string;
    /** All available arm labels in order (for live preview as count changes) */
    arms: string[];
    max_count: number;
}

/**
 * Map of all available naming presets keyed by NamingStyleKey.
 * Used to populate the naming style selector in BulkGenerateModal.
 */
export type NamingPresetsMap = Record<NamingStyleKey, NamingPreset>;

/**
 * Payload sent to POST /class-sections/bulk-generate.
 * Maps to BulkGenerateClassSectionRequest validated fields.
 */
export interface BulkGeneratePayload {
    class_level_ids: string[];
    naming_style: NamingStyleKey;
    /** Required when naming_style !== 'custom' */
    arm_count?: number;
    /** Required when naming_style === 'custom' */
    custom_arms?: string[];
    defaults?: {
        capacity?: number;
        status?: ClassSectionStatus;
    };
}

/**
 * Response from POST /class-sections/bulk-generate.
 */
export interface BulkGenerateResult {
    message: string;
    total_created: number;
    total_skipped: number;
    per_level: Array<{
        level: string;
        created: number;
        skipped: number;
        sections: ClassSection[];
    }>;
}

// ──────────────────────────────────────────────────────────────────────────────
// Subject Assignment Form Data
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Payload for POST /class-sections/{section}/subjects.
 */
export interface AssignSubjectPayload {
    teacher_id: string;
    subject_id: string;
    role?: string | null;
}

// ──────────────────────────────────────────────────────────────────────────────
// Bulk Operation Shapes
// ──────────────────────────────────────────────────────────────────────────────

export type BulkSectionAction = 'delete' | 'restore' | 'force-delete' | 'toggle' | 'reorder';

export interface BulkSectionPayload {
    action: BulkSectionAction;
    ids: string[];
    /** Required only when action = 'toggle' */
    is_active?: boolean;
}

export interface BulkSectionResponse {
    message: string;
    count?: number;
    updated?: number;
}

// ──────────────────────────────────────────────────────────────────────────────
// Page Props
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Inertia props passed to Settings/Academic/ClassSections/Index.vue.
 * Mirrors ClassSectionController::index() render call.
 */
export interface ClassSectionsPageProps {
    initialData: ClassSection[];
    totalRecords: number;
    columns: ColumnDefinition<ClassSection>[];
    namingPresets: NamingPresetsMap;
    showTrashed: boolean;
}

// ──────────────────────────────────────────────────────────────────────────────
// Utility Types & Guards
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Type guard — checks whether a section is in the trash.
 */
export function isTrashed(section: ClassSection): boolean {
    return section.deleted_at !== null;
}

/**
 * Type guard — checks whether a section has reached capacity.
 */
export function isAtCapacity(section: ClassSection): boolean {
    return section.is_at_capacity;
}

/**
 * Type guard — checks whether a section has been manually named.
 * (display_name_stored !== null means admin customised the display name)
 */
export function hasCustomDisplayName(section: ClassSection): boolean {
    return section.display_name_stored !== null;
}

/**
 * Known role values for teacher-subject assignments.
 * Stored as string (not enum) on the backend — this is for UI display only.
 */
export const ASSIGNMENT_ROLES = [
    { value: 'subject_teacher', label: 'Subject Teacher' },
    { value: 'co_teacher',      label: 'Co-Teacher' },
    { value: 'cover_teacher',   label: 'Cover Teacher' },
    { value: 'supervisor',      label: 'Supervisor' },
] as const;

export type AssignmentRoleValue = typeof ASSIGNMENT_ROLES[number]['value'];
