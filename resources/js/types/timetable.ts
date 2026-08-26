/**
 * Timetable module types — aligned with TimetableResource / TimetableSlotResource.
 *
 * Domain:
 *   School → SchoolSection (academic division) → Timetable (per term)
 *     ├── day_schedules: day_of_week → PeriodSchedule → ClassPeriod[]
 *     └── slots: class_section × day × class_period → TCSS assignment
 *
 * IDs: Timetable/ClassSection UUID; Term/ClassPeriod/TCSS/Conflict number.
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

/**
 * One working day's period schedule on a timetable.
 * Used for both resource day_schedules and Builder periodSchedules props.
 */
export interface TimetableDaySchedule {
    day_of_week: number;
    day_name?: string;
    /** PeriodSchedule id (numeric in current schema) */
    schedule_id: NumericId;
    schedule_name?: string;
    periods: ClassPeriodRef[];
}

/** @deprecated Prefer TimetableDaySchedule */
export type DaySchedule = TimetableDaySchedule;
/** @deprecated Prefer TimetableDaySchedule */
export type PeriodScheduleDay = TimetableDaySchedule;

export interface Timetable {
    id: UUID;
    title: string;
    status: TimetableStatus;
    is_draft: boolean;
    is_active: boolean;
    is_archived: boolean;
    /** Academic division (JSS/SSS), NOT a class arm */
    school_section_id: UUID;
    school_section_name: string;
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
    day_schedules: TimetableDaySchedule[];
    created_at: string;
    updated_at: string;
}

/**
 * TimetableSlotResource. period_id = class_periods.id.
 * Breaks are ClassPeriod only — never slots.
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

/**
 * One grid cell. periodId is the concrete ClassPeriod for THAT day, or null
 * when the day has no period at this visual row.
 * Visual rows align by order only — they are not domain entities.
 */
export interface GridCell {
    day: number;
    periodId: NumericId | null;
    period: ClassPeriodRef | null;
    hasSchedule: boolean;
    slot: TimetableSlot | null;
    extraSlots: TimetableSlot[];
    isBreak: boolean;
    /** Backend scheduling conflict on the primary slot */
    hasConflict: boolean;
    /** More than one slot assigned to this cell (data integrity) */
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

/**
 * Drag/drop move intent. Parent owns slot state and performs optimistic update + rollback.
 */
export interface SlotMovePayload {
    slotId: UUID;
    fromDay: number;
    fromPeriodId: NumericId;
    toDay: number;
    toPeriodId: NumericId;
}

/** Shape accepted at the API/Inertia boundary before normalizeTimetable. */
export interface RawTimetableResource {
    id: UUID;
    title?: string;
    status?: TimetableStatus;
    section_id?: UUID;
    school_section_id?: UUID;
    section_name?: string;
    school_section_name?: string;
    term_id?: NumericId;
    term_name?: string;
    effective_from?: string;
    effective_to?: string | null;
    slot_count?: number;
    unresolved_conflict_count?: number;
    has_conflicts?: boolean;
    can_activate?: boolean;
    generated_at?: string | null;
    generated_by_name?: string | null;
    notes?: string | null;
    options?: Record<string, unknown>;
    working_days?: number[];
    day_schedules?: TimetableDaySchedule[];
    created_at?: string;
    updated_at?: string;
    is_draft?: boolean;
    is_active?: boolean;
    is_archived?: boolean;
    [key: string]: unknown;
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

/**
 * Adapter: TimetableResource may emit section_id / section_name.
 * Only this function should know about aliases.
 */
export function normalizeTimetable(raw: RawTimetableResource): Timetable {
    const sectionId = (raw.school_section_id ?? raw.section_id) as UUID;
    const sectionName = String(raw.school_section_name ?? raw.section_name ?? '');
    const daySchedules = (raw.day_schedules ?? []).map((d) => ({
        ...d,
        schedule_id: Number(d.schedule_id) as NumericId,
    }));
    return {
        id: raw.id,
        title: String(raw.title ?? ''),
        status: (raw.status ?? 'draft') as TimetableStatus,
        is_draft: Boolean(raw.is_draft ?? raw.status === 'draft'),
        is_active: Boolean(raw.is_active ?? raw.status === 'active'),
        is_archived: Boolean(raw.is_archived ?? raw.status === 'archived'),
        school_section_id: sectionId,
        school_section_name: sectionName,
        term_id: Number(raw.term_id) as NumericId,
        term_name: String(raw.term_name ?? ''),
        effective_from: String(raw.effective_from ?? ''),
        effective_to: (raw.effective_to as string | null) ?? null,
        slot_count: Number(raw.slot_count ?? 0),
        unresolved_conflict_count: Number(raw.unresolved_conflict_count ?? 0),
        has_conflicts: Boolean(raw.has_conflicts),
        can_activate: Boolean(raw.can_activate),
        generated_at: (raw.generated_at as string | null) ?? null,
        generated_by_name: (raw.generated_by_name as string | null) ?? null,
        notes: (raw.notes as string | null) ?? null,
        options: (raw.options as Record<string, unknown>) ?? {},
        working_days: Array.isArray(raw.working_days) ? [...raw.working_days] : [],
        day_schedules: daySchedules,
        created_at: String(raw.created_at ?? ''),
        updated_at: String(raw.updated_at ?? ''),
    };
}
