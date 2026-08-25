/** Timetable module TypeScript types — aligned with TimetableResource / TimetableSlotResource */

export type TimetableStatus = 'draft' | 'active' | 'archived';

export type ConflictType =
    | 'teacher_double_booked'
    | 'section_double_booked'
    | 'no_available_period'
    | 'no_teacher_assigned'
    | 'frequency_unmet';

export type ResolutionStrategy = 'use_suggestion' | 'manual' | 'skip';

export interface DaySchedule {
    day_of_week: number;
    day_name?: string;
    schedule_id: number | string;
    schedule_name?: string;
    periods?: ClassPeriodRef[];
}

export interface ClassPeriodRef {
    id: number;
    name: string;
    order: number;
    duration_minutes: number;
    is_break: boolean;
    start_time?: string | null;
    end_time?: string | null;
}

export interface Timetable {
    id: string;
    title: string;
    status: TimetableStatus;
    is_draft: boolean;
    is_active: boolean;
    is_archived: boolean;
    section_id: string;
    section_name: string;
    term_id: number | string;
    term_name: string;
    effective_from: string;
    effective_to: string | null;
    slot_count: number;
    unresolved_conflict_count: number;
    has_conflicts: boolean;
    can_activate: boolean;
    generated_at: string | null;
    generated_by_name: string | null;
    notes: string | null;
    options: Record<string, unknown>;
    working_days: number[];
    day_schedules: DaySchedule[];
    created_at: string;
    updated_at: string;
}

export interface TimetableSlot {
    id: string | number;
    timetable_id: string;
    day_of_week: number;
    day_name: string;
    class_section_id: string;
    class_section_name?: string | null;
    period_id: number;
    period_name?: string | null;
    period_order?: number | null;
    period_start_time?: string | null;
    period_end_time?: string | null;
    period_duration_min?: number | null;
    is_break?: boolean;
    subject_id?: number | string | null;
    subject_name?: string | null;
    subject_color?: string | null;
    teacher_id?: number | string | null;
    teacher_name?: string | null;
    teacher_full_name?: string | null;
    tcss_id?: number | null;
    is_manually_placed?: boolean;
    can_move?: boolean;
    has_conflict?: boolean;
    conflict_ids?: number[];
    notes?: string | null;
}

export interface ConflictSuggestion {
    day_of_week: number;
    class_period_id: number;
    period_name?: string;
    day_name?: string;
    score?: number;
    reason?: string;
}

export interface TimetableConflict {
    id: number;
    timetable_id: string;
    class_section_id?: string | null;
    class_section_name?: string | null;
    teacher_class_section_subject_id?: number | null;
    class_period_id?: number | null;
    day_of_week?: number | null;
    conflict_type: ConflictType;
    description: string;
    suggested_alternatives?: ConflictSuggestion[];
    resolved_at?: string | null;
    resolved_by?: number | null;
    resolution_notes?: string | null;
    subject_name?: string | null;
    teacher_name?: string | null;
}

export interface ClassSectionAssignment {
    id: number;
    subject_id: number | string;
    subject_name?: string | null;
    subject_color?: string | null;
    periods_per_week?: number | null;
    teacher_name?: string | null;
}

export interface BuilderClassSection {
    id: string;
    name: string;
    display_name: string;
    assignments: ClassSectionAssignment[];
}

export interface PeriodScheduleDay {
    day_of_week: number;
    schedule_id: number | string;
    schedule_name?: string;
    periods: ClassPeriodRef[];
}

/** Grid cell key helpers */
export type GridKey = string; // `${day}:${periodId}`

export interface GridCell {
    day: number;
    periodId: number;
    period?: ClassPeriodRef;
    slot: TimetableSlot | null;
    isBreak: boolean;
    hasConflict: boolean;
}

export interface PeriodFormItem {
    id?: number | string | null;
    tempId?: string;
    name: string;
    duration_minutes: number;
    is_break: boolean;
    order: number;
}

export const DAY_NAMES: Record<number, string> = {
    1: 'Monday',
    2: 'Tuesday',
    3: 'Wednesday',
    4: 'Thursday',
    5: 'Friday',
    6: 'Saturday',
    7: 'Sunday',
};

export const TIMETABLE_STATUS_CONFIG = {
    draft: { label: 'Draft', severity: 'secondary' as const },
    active: { label: 'Active', severity: 'success' as const },
    archived: { label: 'Archived', severity: 'warn' as const },
} as const;

export const CONFLICT_TYPE_LABELS: Record<ConflictType, string> = {
    teacher_double_booked: 'Teacher double-booked',
    section_double_booked: 'Section double-booked',
    no_available_period: 'No available period',
    no_teacher_assigned: 'No teacher assigned',
    frequency_unmet: 'Frequency unmet',
};
