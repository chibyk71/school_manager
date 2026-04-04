/**
 * resources/js/types/subject.ts – v1.0
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT IT IMPLEMENTS
 * ─────────────────────────────────────────────────────────────────────────────
 * Single source of truth for all TypeScript types related to the Subject module.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FEATURES / PROBLEMS SOLVED
 * ─────────────────────────────────────────────────────────────────────────────
 * • Strict typing prevents runtime errors when consuming Inertia props / API responses
 * • Mirrors SubjectResource output exactly for full type safety in components
 * • SubjectFormData matches StoreSubjectRequest / UpdateSubjectRequest
 * • Enum-like literal types for `type` and `category` fields
 * • DropdownOption generic reused for section/class-level selects
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FITS INTO THE MODULE
 * ─────────────────────────────────────────────────────────────────────────────
 * • Imported in Settings/Academic/Subjects.vue and SubjectModal.vue
 * • Used for Inertia page props, form v-model, and DataTable row types
 */

import type { ColumnDefinition, TableQueryProps } from "./datatables";
import type { DynamicEnumOption } from "./dynamic-enums";

export type SubjectType     = 'core' | 'elective' | 'optional';
export type SubjectCategory = 'sciences' | 'arts' | 'commerce' | 'languages' | 'technical' | 'general';

/** Shape of a single Subject row as returned by SubjectResource */
export interface Subject {
    id: string;
    name: string;
    code: string;
    description: string | null;
    type: SubjectType;
    type_label: string;
    category: SubjectCategory;
    category_label: string;
    is_active: boolean;
    pass_mark: number | null;
    credit_hours: number | null;
    color: string | null;
    sort: number;

    // Relations (loaded when needed)
    school_sections?: Array<{ id: string; name: string; short_code: string | null }>;
    school_section_ids?: string[];
    school_section_names?: string;

    class_levels?: Array<{ id: string; name: string; display_name: string }>;
    class_level_ids?: string[];
    class_level_names?: string;

    teachers?: Array<{ id: string; full_name: string }>;
    teacher_count: number;
    student_count: number;

    // Timestamps
    created_at: string | null;
    deleted_at: string | null;
    created_at_raw: string | null;
    deleted_at_raw: string | null;
}

/** Form payload for create / edit modal */
export interface SubjectFormData {
    name: string;
    code: string;
    description: string | null;
    type: SubjectType;
    category: SubjectCategory;
    is_active: boolean;
    pass_mark: number | null;
    credit_hours: number | null;
    color: string | null;
    sort: number;
    school_section_ids: string[];
    class_level_ids: string[];
}

/** Empty form state for "Create" mode */
export const emptySubjectForm = (): SubjectFormData => ({
    name: '',
    code: '',
    description: null,
    type: 'core',
    category: 'general',
    is_active: true,
    pass_mark: 40,
    credit_hours: null,
    color: null,
    sort: 0,
    school_section_ids: [],
    class_level_ids: [],
});

/** Generic select option */
export interface SelectOption {
    value: string;
    label: string;
    section?: string; // for class levels grouped by section
}

/** Inertia page props for Settings/Academic/Subjects.vue */
export interface SubjectsPageProps extends TableQueryProps<Subject> {
    initialData: Subject[];
    totalRecords: number;
    columns: ColumnDefinition<Subject>[];
    globalFilterables: string[];
    schoolSections: SelectOption[];
    classLevels: SelectOption[];
    subjectTypes: DynamicEnumOption[];
    subjectCategories: DynamicEnumOption[];
    crumbs: Array<{ label: string; href?: string }>;
    error?: string;
}
