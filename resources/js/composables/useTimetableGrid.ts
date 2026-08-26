import { computed, type ComputedRef, type Ref } from 'vue';
import type {
    ClassPeriodRef,
    GridCell,
    GridKey,
    NumericId,
    TimetableDaySchedule,
    TimetableSlot,
} from '@/types/timetable';

/**
 * Pure day-specific timetable grid view.
 *
 * Rules:
 * - Parent owns slot state (single source of truth). This composable never mutates slots.
 * - A cell is (day_of_week, class_period_id) for THAT day's PeriodSchedule.
 * - Visual rows align by period `order` only — not canonical domain IDs.
 * - Days without a schedule → hasSchedule: false (never invent a schedule).
 * - Multiple slots in one cell → extraSlots + isDuplicate (never silent overwrite).
 * - hasConflict is independent of isDuplicate.
 *
 * workingDays option:
 * - undefined → infer from periodSchedules (or Mon–Fri fallback)
 * - []        → no working days (empty grid)
 * - number[]  → use those days
 */
export function useTimetableGrid(
    slots: Ref<TimetableSlot[]> | ComputedRef<TimetableSlot[]>,
    periodSchedules: Ref<TimetableDaySchedule[]> | ComputedRef<TimetableDaySchedule[]>,
    options?: {
        /**
         * undefined = not provided (infer); [] = explicitly empty; [1,2,…] = explicit days.
         * Pass a Ref whose value may be undefined when the parent has no explicit list.
         */
        workingDays?: Ref<number[] | undefined> | ComputedRef<number[] | undefined>;
    },
) {
    const workingDays = computed(() => {
        const explicit = options?.workingDays?.value;
        if (explicit !== undefined) {
            return [...explicit].sort((a, b) => a - b);
        }
        const fromSchedules = (periodSchedules.value ?? []).map((d) => d.day_of_week);
        if (fromSchedules.length) {
            return [...new Set(fromSchedules)].sort((a, b) => a - b);
        }
        return [1, 2, 3, 4, 5];
    });

    /** Periods for a day — empty array if no schedule (do not invent). */
    const periodsForDay = (day: number): ClassPeriodRef[] => {
        const match = (periodSchedules.value ?? []).find((d) => d.day_of_week === day);
        if (!match?.periods?.length) return [];
        return [...match.periods].sort((a, b) => a.order - b.order);
    };

    /**
     * Visual row positions (by order). Not a list of canonical ClassPeriod entities.
     */
    const periodRows = computed(() => {
        const orders = new Set<number>();
        for (const day of workingDays.value) {
            for (const p of periodsForDay(day)) {
                orders.add(p.order);
            }
        }
        return [...orders].sort((a, b) => a - b);
    });

    /**
     * Neutral row header by order only.
     * Day-specific break vs teaching is shown on each cell, not the row label
     * (schedules can differ by day at the same visual order).
     */
    const rowLabel = (order: number): { name: string } => ({
        name: `P${order}`,
    });

    const makeKey = (day: number, periodId: NumericId): GridKey => `${day}:${periodId}`;

    /** key → all slots at cell (preserves duplicates). */
    const slotsByCell = computed(() => {
        const map = new Map<GridKey, TimetableSlot[]>();
        for (const slot of slots.value ?? []) {
            const key = makeKey(slot.day_of_week, slot.period_id);
            const list = map.get(key) ?? [];
            list.push(slot);
            map.set(key, list);
        }
        return map;
    });

    const duplicateKeys = computed(() => {
        const keys: GridKey[] = [];
        for (const [key, list] of slotsByCell.value) {
            if (list.length > 1) keys.push(key);
        }
        return keys;
    });

    /**
     * grid[rowIndex][dayIndex]
     * Each cell carries day-specific periodId; rows align by order only.
     */
    const grid = computed<GridCell[][]>(() => {
        return periodRows.value.map((order) => {
            return workingDays.value.map((day) => {
                const dayPeriods = periodsForDay(day);
                const hasSchedule = dayPeriods.length > 0;
                const period = dayPeriods.find((p) => p.order === order) ?? null;

                if (!period) {
                    return {
                        day,
                        periodId: null,
                        period: null,
                        hasSchedule,
                        slot: null,
                        extraSlots: [],
                        isBreak: false,
                        hasConflict: false,
                        isDuplicate: false,
                    } satisfies GridCell;
                }

                const atCell = slotsByCell.value.get(makeKey(day, period.id)) ?? [];
                const [primary, ...rest] = atCell;

                return {
                    day,
                    periodId: period.id,
                    period,
                    hasSchedule: true,
                    slot: primary ?? null,
                    extraSlots: rest,
                    isBreak: !!period.is_break,
                    hasConflict: !!(primary?.has_conflict),
                    isDuplicate: rest.length > 0,
                } satisfies GridCell;
            });
        });
    });

    const getSlotsAt = (day: number, periodId: NumericId): TimetableSlot[] =>
        slotsByCell.value.get(makeKey(day, periodId)) ?? [];

    return {
        workingDays,
        periodRows,
        rowLabel,
        periodsForDay,
        grid,
        slotsByCell,
        duplicateKeys,
        makeKey,
        getSlotsAt,
    };
}
