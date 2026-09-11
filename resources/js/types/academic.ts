/**
 * Academic Calendar Type Definitions
 *
 * Phase 1: lifecycle authority is Spatie state machine values, not is_current / status enums.
 */

export type AcademicSessionState = 'draft' | 'planned' | 'active' | 'paused' | 'closed';
export type TermState = 'planned' | 'active' | 'closed';

export interface AcademicSession {
    id: number | string;
    school_id: number;

    name: string;
    slug?: string;

    start_date: string;
    end_date: string;

    /** Authoritative lifecycle state */
    state: AcademicSessionState;
    state_label?: string;
    /** Derived convenience: true when state === 'active' */
    is_active?: boolean;

    created_at: string;
    updated_at: string;
    deleted_at?: string | null;

    terms?: Term[];
    terms_count?: number;

    has_active_terms?: boolean;
    progress_percentage?: number;
}

export interface Term {
    id: number | string;
    academic_session_id: number;

    name: string;
    display_name?: string;
    short_name?: string | null;

    start_date: string;
    end_date: string;

    /** Authoritative lifecycle state */
    state: TermState;
    state_label?: string;
    is_active?: boolean;
    is_closed?: boolean;

    color?: string;
    ordinal_number?: number;

    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
    closed_at?: string | null;

    classes_count?: number;
    assessments_count?: number;
}

export interface AcademicSessionFormData {
    name: string;
    start_date: string | null;
    end_date: string | null;
    state?: AcademicSessionState;
}

export interface TermFormData {
    academic_session_id: number | string;
    name: string;
    start_date: string | null;
    end_date: string | null;
    color?: string;
    ordinal_number?: number;
    state?: TermState;
}

export interface SessionOption {
    id: number | string;
    name: string;
    start_date: string;
    end_date: string;
    state: AcademicSessionState;
    is_current?: boolean;
}

export interface TermOption {
    id: number | string;
    name: string;
    display_name?: string;
    academic_session_id: number | string;
    start_date: string;
    end_date: string;
    state: TermState;
    color?: string;
}

export const SESSION_STATE_CONFIG = {
    draft:   { label: 'Draft',   severity: 'secondary' as const },
    planned: { label: 'Planned', severity: 'info' as const },
    active:  { label: 'Active',  severity: 'success' as const },
    paused:  { label: 'Paused',  severity: 'warning' as const },
    closed:  { label: 'Closed',  severity: 'danger' as const },
} as const;

export const TERM_STATE_CONFIG = {
    planned: { label: 'Planned',     severity: 'info' as const },
    active:  { label: 'In Progress', severity: 'success' as const },
    closed:  { label: 'Completed',   severity: 'warning' as const },
} as const;

export interface AcademicSessionWithTerms extends AcademicSession {
    terms: Term[];
}

export interface TermWithSession extends Term {
    session: Pick<AcademicSession, 'id' | 'name' | 'start_date' | 'end_date' | 'state'>;
}

export interface CurrentSessionInfo {
    id: number | string;
    name: string;
    start_date: string;
    end_date: string;
    state: AcademicSessionState;
    is_active?: boolean;
}
