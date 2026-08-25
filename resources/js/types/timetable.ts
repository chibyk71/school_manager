/**
 * Timetable module types — aligned with TimetableResource / TimetableSlotResource.
 *
 * Domain hierarchy (backend):
 *   School → SchoolSection (Nursery/Primary/Secondary) → Timetable (per term)
 *     ├── day_schedules: day_of_week → PeriodSchedule → ClassPeriod[]
 *     └── slots: class_section × day × class_period → TCSS assignment
 *
 * ID conventions from backend:
 *   - Timetable, SchoolSection, ClassSection → UUID (string)
 *   - Term, ClassPeriod, TCSS, Conflict → integer (number)
 */

export type UUID = string;
export type NumericId = number;

export type TimetableStatus = 'draft' | 'active' | 'archived';

export type ConflictType =
    | 'teacher_double_booked'
    | 'section_double_booked'
    | 'no_available_period'
    | 'no_teacher_assigned'
    | 'frequency_unmet';

/** Backend resolution strategies (ResolveTimetableConflictRequest) */
export type ResolutionStrategy = 'use_suggestion' | 'manual' | 'skip';

export interface ClassPeriodRef {
    id: NumericId;
    name: string;
    order: number;
    duration_minutes: number;
    is_break: boolean;
    start_time?: string | null;
    end_time?: string | null;
}

export interface DaySchedule {
    day_of_week: number;
    day_name?: string;
    schedule_id: NumericId;
    schedule_name?: string;
    periods?: ClassPeriodRef[];
}

export interface PeriodScheduleDay {
    day_of_week: number;
    schedule_id: NumericId | string;
    schedule_name?: string;
    periods: ClassPeriodRef[];
}

/**
 * Timetable header (TimetableResource).
 * `school_section_id` is the academic division (JSS/SSS), NOT a class arm.
 * Class arms appear on slots as `class_section_id`.
 */
export interface Timetable {
    id: UUID;
    title: string;
    status: TimetableStatus;
    is_draft: boolean;
    is_active: boolean;
    is_archived: boolean;
    school_section_id: UUID;
    school_section_name: string;
    section_id?: UUID;
    section_name?: string;
    term_id: NumericId;
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

/**
 * One placed lesson (TimetableSlotResource).
 * `period_id` is class_period_id from the backend resource.
 * Breaks are never slots — only ClassPeriod.is_break.
 */
export interface TimetableSlot {
    id: UUID;
    timetable_id: UUID;
    day_of_week: number;
    day_name: string;
    class_section_id: UUID;
    class_section_name?: string | null;
    period_id: NumericId;
    period_name?: string | null;
    period_order?: number | null;
    period_start_time?: string | null;
    period_end_time?: string | null;
    period_duration_min?: number | null;
    subject_id?: NumericId | null;
    subject_name?: string | null;
    subject_color?: string | null;
    teacher_id?: NumericId | null;
    teacher_name?: string | null;
    teacher_full_name?: string | null;
    tcss_id?: NumericId | null;
    is_manually_placed?: boolean;
    can_move?: boolean;
    has_conflict?: boolean;
    conflict_ids?: NumericId[];
    notes?: string | null;
}

export interface ConflictSuggestion {
    day_of_week: number;
    class_period_id: NumericId;
    period_name?: string;
    day_name?: string;
    score?: number;
    reason?: string;
}

export interface TimetableConflict {
    id: NumericId;
    timetable_id: UUID;
    class_section_id?: UUID | null;
    class_section_name?: string | null;
    teacher_class_section_subject_id?: NumericId | null;
    class_period_id?: NumericId | null;
    day_of_week?: number | null;
    conflict_type: ConflictType;
    description: string;
    suggested_alternatives?: ConflictSuggestion[];
    resolved_at?: string | null;
    resolved_by?: NumericId | null;
    resolution_notes?: string | null;
    subject_name?: string | null;
    teacher_name?: string | null;
}

export interface ClassSectionAssignment {
    id: NumericId;
    subject_id: NumericId;
    subject_name?: string | null;
    subject_color?: string | null;
    periods_per_week?: number | null;
    teacher_name?: string | null;
}

export interface BuilderClassSection {
    id: UUID;
    name: string;
    display_name: string;
    assignments: ClassSectionAssignment[];
}

export type GridKey = `${number}:${number}`;

export interface GridCell {
    day: number;
    periodId: NumericId;
    period: ClassPeriodRef | null;
    hasSchedule: boolean;
    slot: TimetableSlot | null;
    extraSlots: TimetableSlot[];
    isBreak: boolean;
    hasConflict: boolean;
    isDuplicate: boolean;
}

export interface PeriodFormItem {
    id?: NumericId | null;
    tempId?: string;
    name: string;
    duration_minutes: number;
    is_break: boolean;
    order: number;
}

export interface SlotMovePayload {
    slotId: UUID;
    fromDay: number;
    fromPeriodId: NumericId;
    toDay: number;
    toPeriodId: NumericId;
    rollback: () => void;
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

export function normalizeTimetable(raw: Timetable & Record<string, unknown>): Timetable {
    return {
        ...raw,
        school_section_id: (raw.school_section_id ?? raw.section_id) as UUID,
        school_section_name: (raw.school_section_name ?? raw.section_name ?? '') as string,
    };
}
