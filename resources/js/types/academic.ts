// resources/js/types/academic-calendar.ts
/**
 * Academic Calendar Type Definitions
 * ──────────────────────────────────────────────────────────────────────────────
 *
 * Central place for all type definitions related to:
 *   - Academic Sessions (school years)
 *   - Academic Terms (semesters/trimesters within sessions)
 *
 * Main goals:
 * - Strong typing for Inertia props, forms, modals & DataTables
 * - Clear separation between full entity, form payloads & minimal options
 * - Support for common relationships (session ↔ terms)
 * - Status & state discrimination
 * - Ready for both Laravel API responses and frontend usage
 *
 * Usage recommendations:
 *   - Import as: import type { AcademicSession, Term, ... } from '@/types/academic-calendar'
 *   - Use AcademicSessionFormData / TermFormData for forms
 *   - Use SessionOption / TermOption for dropdowns/selects
 */

export interface AcademicSession {
    id: number | string;
    school_id: number;

    name: string;                    // e.g. "2025/2026", "2024/2025 Second Session"
    slug?: string;

    start_date: string;              // ISO: "2025-09-01"
    end_date: string;                // ISO: "2026-08-31"

    is_current: boolean;
    status: 'pending' | 'active' | 'closed' | 'archived';

    // Timestamps
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;

    // Relations (loaded when needed)
    terms?: Term[];
    terms_count?: number;

    // Optional authorization/derived fields (often added by backend)
    can_activate?: boolean;
    can_close?: boolean;
    can_delete?: boolean;
    has_active_terms?: boolean;
    progress_percentage?: number;    // 0–100, optional frontend/backend calc
}

export interface Term {
    id: number | string;
    academic_session_id: number;

    name: string;                    // "First Term", "Second Semester", "Harmattan"
    display_name?: string;           // optional more formal name

    start_date: string;
    end_date: string;

    is_current: boolean;
    status: 'pending' | 'active' | 'closed' | 'archived';

    // Optional visual / UI related fields
    color?: string;                  // HEX color for calendar/timeline
    ordinal_number?: number;         // 1,2,3... for sorting/display

    // Timestamps
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;

    // Optional relations / counts
    classes_count?: number;
    assessments_count?: number;
}

/* ─────────────────────────────────────────────────────────────────────────────
   Form Payload Types (for create/update)
   ───────────────────────────────────────────────────────────────────────────── */

export interface AcademicSessionFormData {
    name: string;
    start_date: string | null;       // usually string after DatePicker submit
    end_date: string | null;
    is_current?: boolean;            // often false on create
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
   Minimal types for dropdowns, selects, badges, etc.
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
    pending: { label: 'Upcoming', severity: 'info' as const },
    active: { label: 'Active', severity: 'success' as const },
    closed: { label: 'Closed', severity: 'warning' as const },
    archived: { label: 'Archived', severity: 'danger' as const },
} as const;

export const TERM_STATUS_CONFIG = {
    pending: { label: 'Upcoming', severity: 'info' as const },
    active: { label: 'In Progress', severity: 'success' as const },
    closed: { label: 'Completed', severity: 'warning' as const },
    archived: { label: 'Archived', severity: 'danger' as const },
} as const;

/* ─────────────────────────────────────────────────────────────────────────────
   Combined / Extended types (when you need both in one view)
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
    is_current: true;
    status: 'active';
}
