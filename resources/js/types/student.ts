// resources/js/types/student.ts
// Accurate TypeScript types for Student module
// Reflects: Student → Profile (1:1 via polymorphic), Custom Fields, School scoping

import type { CustomField, CustomFieldsApiResponse } from '@/types/form';

export type StudentStatus = 'active' | 'graduated' | 'withdrawn' | 'suspended' | 'transferred';
export type Gender = 'male' | 'female' | 'other';

/**
 * Core Student model — only contains data specific to being a student
 * All personal/human data lives in Profile (first_name, last_name, DOB, phone, etc.)
 */
export interface Student {
    id: string;
    school_id: string;
    school_section_id?: string | null;

    // Foreign keys & polymorphic
    profilable_id?: string;
    profilable_type?: string;

    // Student-specific fields (not in Profile)
    admission_number?: string | null;
    admission_date?: string | null; // date
    graduation_date?: string | null;
    status: StudentStatus;

    // Computed/Appended from Profile (always included in API responses)
    full_name: string;
    first_name: string;
    last_name: string;
    short_name: string;
    age: number | null;
    gender: Gender | null;
    phone: string | null;
    email?: string | null;
    date_of_birth?: string | null; // ISO date
    photo_url: string;

    // Current academic placement
    // (via currentClassSection accessor)
    current_class_level_name?: string | null; // e.g., "JSS 3"
    current_section_name?: string | null;     // e.g., "JSS 3A"
    current_class_display?: string | null;    // e.g., "JSS 3A" (combined)

    // Timestamps
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

/**
 * Full Student with all relations loaded (used in Show.vue, forms, promotions)
 */
export interface StudentWithRelations extends Student {
    // Direct relation to Profile (not polymorphic in practice — we load it directly)
    profile: {
        id: string;
        user_id: string;
        title?: string | null; // Mr, Mrs, Dr
        first_name: string;
        middle_name?: string | null;
        last_name: string;
        gender: Gender;
        date_of_birth?: string | null;
        phone?: string | null;
        address?: string | null;
        is_primary: boolean;
        photo_url?: string;
    } | null;

    // Guardians (via pivot or relationship)
    guardians?: Array<{
        id: string;
        full_name: string;
        relationship: string; // Father, Mother, Guardian
        phone: string;
        email?: string;
    }>;

    // Current class section details
    current_class_section?: {
        id: string;
        name: string;
        class_level: {
            id: string;
            name: string;
            display_name: string; // e.g., "Senior Secondary 2"
        };
    } | null;

    // Custom field responses (pre-filled values for edit forms)
    custom_field_responses?: Record<string, any>;

    // Promotion history (for profile timeline)
    promotion_history?: Array<{
        session: string;
        from_class: string;
        to_class: string;
        outcome: 'promoted' | 'repeated' | 'probated' | 'graduated';
        executed_at: string;
    }>;
}

/**
 * API Response when fetching a single student for Create/Edit form
 * Includes custom fields + pre-filled values
 */
export interface StudentFormApiResponse {
    student?: StudentWithRelations;
    customFields: CustomFieldsApiResponse;
}

/**
 * API Response for student list (Index page)
 */
export interface StudentIndexApiResponse {
    data: Student[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
}

/**
 * Props expected in Student Index page
 */
export interface StudentIndexPageProps {
    students: StudentIndexApiResponse;
    filters: Record<string, any>;
    can: {
        create: boolean;
        edit: boolean;
        delete: boolean;
        promote: boolean;
    };
    columns: Array<{
        field: string;
        header: string;
        sortable?: boolean;
        filterable?: boolean;
    }>;
}

/**
 * Props expected in Student Show page
 */
export interface StudentShowPageProps {
    student: StudentWithRelations;
    customFields: CustomField[];
    can: {
        edit: boolean;
        delete: boolean;
    };
}