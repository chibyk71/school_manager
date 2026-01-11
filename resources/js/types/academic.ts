// resources/js/types/academic-calendar.ts
/**
 * Academic Calendar Type Definitions
 * ──────────────────────────────────────────────────────────────────────────────
 *
 * Central, strongly-typed definitions for Academic Sessions and Terms.
 * Covers:
 *   - Full entity shapes (from Laravel)
 *   - Form payloads (create/update)
 *   - Minimal/select options (dropdowns, badges, quick views)
 *   - UI/status mappings (badges, colors, labels)
 *   - Combined/extended types (when relations are loaded)
 *
 * Goals:
 * - Strict typing across Inertia props, forms, modals, DataTables
 * - Single source of truth for status → label/severity mapping
 * - Clear separation: full entity vs form data vs UI-only
 * - Ready for both API responses and frontend usage
 *
 * Usage:
 *   import type { AcademicSession, Term, TermFormData, ... } from '@/types/academic-calendar'
 */

export interface AcademicSession {
    id: number | string;
    school_id: number;

    name: string;                    // e.g. "2025/2026"
    slug?: string;

    start_date: string;              // ISO date string "YYYY-MM-DD"
    end_date: string;

    is_current: boolean;
    status: 'pending' | 'active' | 'closed' | 'archived';

    // Timestamps
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;

    // Relations (loaded when needed)
    terms?: Term[];
    terms_count?: number;

    // Optional derived/authorization fields
    has_active_terms?: boolean;
    progress_percentage?: number;    // 0–100, optional
}

export interface Term {
    id: number | string;
    academic_session_id: number;

    name: string;                    // e.g. "First Term", "Harmattan"
    display_name?: string;           // optional formal name

    start_date: string;
    end_date: string;

    is_current: boolean;
    status: 'pending' | 'active' | 'closed' | 'archived';

    // UI/Visual
    color?: string;                  // HEX for calendars/timelines

    // Ordering
    ordinal_number?: number;         // 1,2,3... for sorting/display

    // Timestamps
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;

    // Optional counts
    classes_count?: number;
    assessments_count?: number;
}

/* ─────────────────────────────────────────────────────────────────────────────
   Form Payload Types (for create/update)
   ───────────────────────────────────────────────────────────────────────────── */

export interface AcademicSessionFormData {
    name: string;
    start_date: string | null;
    end_date: string | null;
    is_current?: boolean;
}

export interface TermFormData {
    academic_session_id: number | string;
    name: string;
    start_date: string | null;
    end_date: string | null;
    color?: string;
    ordinal_number?: number;
    is_current?: boolean;
}

/* ─────────────────────────────────────────────────────────────────────────────
   Minimal types for dropdowns, selects, badges, quick views
   ───────────────────────────────────────────────────────────────────────────── */

export interface SessionOption {
    id: number | string;
    name: string;
    start_date: string;
    end_date: string;
    is_current: boolean;
    status: AcademicSession['status'];
}

export interface TermOption {
    id: number | string;
    name: string;
    display_name?: string;
    academic_session_id: number | string;
    start_date: string;
    end_date: string;
    is_current: boolean;
    color?: string;
}

/* ─────────────────────────────────────────────────────────────────────────────
   Status → UI Mapping (badges, colors, labels)
   ───────────────────────────────────────────────────────────────────────────── */

export const SESSION_STATUS_CONFIG = {
    pending:  { label: 'Upcoming',  severity: 'info'    as const },
    active:   { label: 'Active',    severity: 'success' as const },
    closed:   { label: 'Closed',    severity: 'warning' as const },
    archived: { label: 'Archived',  severity: 'danger'  as const },
} as const;

export const TERM_STATUS_CONFIG = {
    pending:  { label: 'Upcoming',    severity: 'info'    as const },
    active:   { label: 'In Progress', severity: 'success' as const },
    closed:   { label: 'Completed',   severity: 'warning' as const },
    archived: { label: 'Archived',    severity: 'danger'  as const },
} as const;

export const SESSION_STATUS_LABEL = {
    pending:  'Upcoming',
    active:   'Active',
    closed:   'Closed',
    archived: 'Archived',
} as const;

export const TERM_STATUS_LABEL = {
    pending:  'Upcoming',
    active:   'In Progress',
    closed:   'Completed',
    archived: 'Archived',
} as const;

/* ─────────────────────────────────────────────────────────────────────────────
   Combined / Extended types (when relations are loaded)
   ───────────────────────────────────────────────────────────────────────────── */

export interface AcademicSessionWithTerms extends AcademicSession {
    terms: Term[];
}

export interface TermWithSession extends Term {
    session: Pick<AcademicSession, 'id' | 'name' | 'start_date' | 'end_date' | 'is_current'>;
}

/* ─────────────────────────────────────────────────────────────────────────────
   Very minimal version – useful for global current session banner
   ───────────────────────────────────────────────────────────────────────────── */

export interface CurrentSessionInfo {
    id: number | string;
    name: string;
    start_date: string;
    end_date: string;
    is_current: boolean;
    status: AcademicSession['status'];
}
