/**
 * Grade-related TypeScript types & interfaces
 *
 * This file serves as the single source of truth for Grade data shapes across the frontend.
 * It ensures type safety, autocompletion, and catch errors early when:
 *   - Passing grade data as props/components
 *   - Handling API responses (GradeResource shape)
 *   - Working with DataTable rows / selection
 *   - Managing form state in modals (create/update)
 *
 * Main goals / problems solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Strong typing for all Grade-related data (prevents runtime errors)
 * • Matches backend Grade model + GradeResource output exactly
 * • Supports many-to-many schoolSections relationship
 * • Includes computed/derived fields (range, is_used) from backend
 * • Clear separation: base Grade vs form payload vs list item
 * • Reusable across components (DataTable, GradeModal, detail views)
 * • Future-proof: easy to add fields (weight, color, gpa_points, etc.)
 * • Works perfectly with Inertia props, PrimeVue DataTable, and form libraries
 *
 * Naming conventions:
 *   - Grade           → full shape from API (with relations)
 *   - GradeFormData   → shape sent to backend (create/update payload)
 *   - GradeListItem   → minimal shape optimized for DataTable rows
 *
 * Usage examples:
 *
 *   // In GradeModal.vue
 *   defineProps<{
 *     grade?: Grade | null
 *   }>()
 *
 *   // In DataTable
 *   const columns = ref<ColumnProps<GradeListItem>[]>([...])
 *
 *   // In form submission
 *   const form = ref<GradeFormData>({ ... })
 *
 * Integration points:
 *   - Inertia shared props / page props
 *   - Axios responses (response.data.grade as Grade)
 *   - PrimeVue DataTable :value / selection
 *   - useModal() payloads
 */

export interface SchoolSectionMinimal {
    id: string
    name: string
}

/**
 * Full Grade shape – matches backend GradeResource output
 * Used for: detail views, modal pre-fill, single resource responses
 */
export interface Grade {
    id: string
    name: string
    code: string
    min_score: number
    max_score: number
    range: string              // computed: "80 – 100"
    remark: string | null
    is_used: boolean           // true if referenced in any ExamResult
    school_sections: SchoolSectionMinimal[]
    created_at: string         // ISO string
    updated_at: string         // ISO string
    deleted_at: string | null  // ISO string or null
}

/**
 * Shape of data sent to backend when creating/updating a grade
 * Matches StoreGradeRequest / UpdateGradeRequest payload
 * Used in: form state, axios.post/patch
 */
export interface GradeFormData {
    name: string
    code: string
    min_score: number
    max_score: number
    remark?: string | null
    school_section_ids?: string[]   // array of section IDs (many-to-many)
}

/**
 * Lightweight shape optimized for DataTable rows
 * Used in: Grades.vue DataTable, selection, bulk actions
 * Contains only fields needed for display + actions
 */
export interface GradeListItem {
    id: string
    name: string
    code: string
    range: string
    remark: string | null
    is_used: boolean
    school_section_names: string    // comma-separated joined names
    created_at: string
    updated_at: string
    deleted_at: string | null
    // Optional: for bulk actions / selection tracking
    _rowChecked?: boolean
}

/**
 * Shape for bulk actions payload (destroy)
 * Sent to /grades/destroy endpoint
 */
export interface BulkDeletePayload {
    ids: string[]
    force?: boolean               // permanent delete
}

/**
 * Union type for grade-related props (flexible component usage)
 */
export type GradeProp = Grade | GradeListItem | null
