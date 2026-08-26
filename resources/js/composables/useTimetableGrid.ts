import { computed, ref, type ComputedRef, type Ref } from 'vue';
import type {
    ClassPeriodRef,
    GridCell,
    GridKey,
    NumericId,
    TimetableDaySchedule,
    TimetableSlot,
    UUID,
} from '@/types/timetable';

/**
 * Day-specific timetable grid.
 *
 * Rules:
 * - A cell is (day_of_week, class_period_id) for THAT day's PeriodSchedule.
 * - Visual rows align by period `order` only - not canonical domain IDs.
 * - Days without a schedule -> hasSchedule: false (never invent a schedule).
 * - Multiple slots in one cell -> extraSlots + isDuplicate (never silent overwrite).
 */
export function useTimetableGrid(
    slots: Ref<TimetableSlot[]> | ComputedRef<TimetableSlot[]>,
    periodSchedules: Ref<TimetableDaySchedule[]> | ComputedRef<TimetableDaySchedule[]>,
    options?: {
        classSectionId?: Ref<UUID | null | undefined> | ComputedRef<UUID | null | undefined>;
        workingDays?: Ref<number[]> | ComputedRef<number[]>;
    },
) {
    const localSlots = ref<TimetableSlot[]>([...(slots.value ?? [])]);

    const syncFromProps = () => {
        localSlots.value = [...(slots.value ?? [])];
    };

    const workingDays = computed(() => {
        if (options?.workingDays?.value?.length) {
            return [...options.workingDays.value].sort((a, b) => a - b);
        }
        const fromSchedules = (periodSchedules.value ?? []).map((d) => d.day_of_week);
        if (fromSchedules.length) {
            return [...new Set(fromSchedules)].sort((a, b) => a - b);
        }
        return [1, 2, 3, 4, 5];
    });

    /** Periods for a day - empty array if no schedule (do not invent). */
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

    const rowLabel = (order: number): { name: string; duration?: number; isBreak: boolean } => {
        for (const day of workingDays.value) {
            const p = periodsForDay(day).find((x) => x.order === order);
            if (p) {
                return { name: p.name, duration: p.duration_minutes, isBreak: !!p.is_break };
            }
        }
        return { name: `P${order}`, isBreak: false };
    };

    const filteredSlots = computed(() => {
        const sectionId = options?.classSectionId?.value;
        if (!sectionId) return localSlots.value;
        return localSlots.value.filter((s) => s.class_section_id === sectionId);
    });

    const makeKey = (day: number, periodId: NumericId): GridKey => `${day}:${periodId}`;

    /** key -> all slots at cell (preserves duplicates). */
    const slotsByCell = computed(() => {
        const map = new Map<GridKey, TimetableSlot[]>();
        for (const slot of filteredSlots.value) {
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
                        periodId: 0 as NumericId,
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
                    hasConflict: !!(primary?.has_conflict) || rest.length > 0,
                    isDuplicate: rest.length > 0,
                } satisfies GridCell;
            });
        });
    });

    const getSlotsAt = (day: number, periodId: NumericId): TimetableSlot[] =>
        slotsByCell.value.get(makeKey(day, periodId)) ?? [];

    /**
     * Optimistic move. Caller MUST invoke rollback on API failure.
     */
    const moveSlotOptimistic = (
        slotId: UUID,
        toDay: number,
        toPeriodId: NumericId,
    ): { previous: TimetableSlot | null; rollback: () => void } => {
        const idx = localSlots.value.findIndex((s) => s.id === slotId);
        if (idx === -1) {
            return { previous: null, rollback: () => {} };
        }
        const previous = { ...localSlots.value[idx] };
        const next = [...localSlots.value];
        next[idx] = {
            ...next[idx],
            day_of_week: toDay,
            period_id: toPeriodId,
            is_manually_placed: true,
        };
        localSlots.value = next;
        return {
            previous,
            rollback: () => {
                const i = localSlots.value.findIndex((s) => s.id === slotId);
                if (i !== -1) {
                    const restored = [...localSlots.value];
                    restored[i] = previous;
                    localSlots.value = restored;
                }
            },
        };
    };

    const setSlots = (next: TimetableSlot[]) => {
        localSlots.value = [...next];
    };

    const upsertSlot = (slot: TimetableSlot) => {
        const idx = localSlots.value.findIndex((s) => s.id === slot.id);
        if (idx === -1) {
            localSlots.value = [...localSlots.value, slot];
        } else {
            const next = [...localSlots.value];
            next[idx] = { ...next[idx], ...slot };
            localSlots.value = next;
        }
    };

    const removeSlot = (slotId: UUID) => {
        localSlots.value = localSlots.value.filter((s) => s.id !== slotId);
    };

    const reconcileSlots = (updates: TimetableSlot[]) => {
        if (!updates.length) return;
        let next = [...localSlots.value];
        for (const slot of updates) {
            const idx = next.findIndex((s) => s.id === slot.id);
            if (idx === -1) next.push(slot);
            else next[idx] = { ...next[idx], ...slot };
        }
        localSlots.value = next;
    };

    return {
        localSlots,
        workingDays,
        periodRows,
        rowLabel,
        periodsForDay,
        grid,
        slotsByCell,
        duplicateKeys,
        filteredSlots,
        makeKey,
        getSlotsAt,
        moveSlotOptimistic,
        setSlots,
        upsertSlot,
        removeSlot,
        reconcileSlots,
        syncFromProps,
    };
}
