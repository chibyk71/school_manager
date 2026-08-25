import { computed, ref, type Ref, type ComputedRef } from 'vue';
import type {
    ClassPeriodRef,
    GridCell,
    GridKey,
    PeriodScheduleDay,
    TimetableSlot,
} from '@/types/timetable';

/**
 * Transforms flat slot lists into a day × period 2D grid structure.
 * Supports optimistic slot moves for drag-and-drop in the builder.
 */
export function useTimetableGrid(
    slots: Ref<TimetableSlot[]> | ComputedRef<TimetableSlot[]>,
    periodSchedules: Ref<PeriodScheduleDay[]> | ComputedRef<PeriodScheduleDay[]>,
    options?: {
        /** Filter grid to a single class section (student/teacher view) */
        classSectionId?: Ref<string | null | undefined> | ComputedRef<string | null | undefined>;
        /** Working days override (defaults to days present in periodSchedules) */
        workingDays?: Ref<number[]> | ComputedRef<number[]>;
    },
) {
    const localSlots = ref<TimetableSlot[]>([]);
    const syncFromProps = () => {
        localSlots.value = [...(slots.value ?? [])];
    };
    // Initial sync
    syncFromProps();

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

    /** Periods for a given day (falls back to first schedule if day missing) */
    const periodsForDay = (day: number): ClassPeriodRef[] => {
        const match = (periodSchedules.value ?? []).find((d) => d.day_of_week === day);
        if (match?.periods?.length) {
            return [...match.periods].sort((a, b) => a.order - b.order);
        }
        const fallback = (periodSchedules.value ?? [])[0];
        return fallback?.periods ? [...fallback.periods].sort((a, b) => a.order - b.order) : [];
    };

    /** Union of all periods across days, keyed by order for header display */
    const allPeriods = computed(() => {
        const byOrder = new Map<number, ClassPeriodRef>();
        for (const day of workingDays.value) {
            for (const p of periodsForDay(day)) {
                if (!byOrder.has(p.order)) {
                    byOrder.set(p.order, p);
                }
            }
        }
        return Array.from(byOrder.values()).sort((a, b) => a.order - b.order);
    });

    const filteredSlots = computed(() => {
        const sectionId = options?.classSectionId?.value;
        if (!sectionId) return localSlots.value;
        return localSlots.value.filter((s) => String(s.class_section_id) === String(sectionId));
    });

    const slotMap = computed(() => {
        const map = new Map<GridKey, TimetableSlot>();
        for (const slot of filteredSlots.value) {
            if (slot.is_break) continue;
            const key = makeKey(slot.day_of_week, slot.period_id);
            map.set(key, slot);
        }
        return map;
    });

    const makeKey = (day: number, periodId: number | string): GridKey =>
        `${day}:${periodId}`;

    /**
     * 2D grid: rows = periods (by order), columns = working days.
     * Each cell holds the slot (if any) for that day/period.
     */
    const grid = computed<GridCell[][]>(() => {
        return allPeriods.value.map((period) => {
            return workingDays.value.map((day) => {
                const dayPeriods = periodsForDay(day);
                const dayPeriod =
                    dayPeriods.find((p) => p.id === period.id) ??
                    dayPeriods.find((p) => p.order === period.order) ??
                    period;
                const key = makeKey(day, dayPeriod.id);
                const slot = slotMap.value.get(key) ?? null;
                return {
                    day,
                    periodId: dayPeriod.id,
                    period: dayPeriod,
                    slot,
                    isBreak: !!dayPeriod.is_break,
                    hasConflict: !!(slot?.has_conflict),
                } satisfies GridCell;
            });
        });
    });

    /** Get slot at day/period */
    const getSlot = (day: number, periodId: number | string): TimetableSlot | null => {
        return slotMap.value.get(makeKey(day, periodId)) ?? null;
    };

    /**
     * Optimistic move: update local state immediately, return rollback fn.
     * Caller should persist via API and call rollback on failure.
     */
    const moveSlotOptimistic = (
        slotId: string | number,
        toDay: number,
        toPeriodId: number,
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
                if (i !== -1 && previous) {
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
            next[idx] = slot;
            localSlots.value = next;
        }
    };

    const removeSlot = (slotId: string | number) => {
        localSlots.value = localSlots.value.filter((s) => s.id !== slotId);
    };

    return {
        localSlots,
        workingDays,
        allPeriods,
        periodsForDay,
        grid,
        slotMap,
        filteredSlots,
        makeKey,
        getSlot,
        moveSlotOptimistic,
        setSlots,
        upsertSlot,
        removeSlot,
        syncFromProps,
    };
}
