<script setup lang="ts">
import { computed, ref, toRef, watch } from 'vue';
import TimetableSlotCell from './TimetableSlotCell.vue';
import { useTimetableGrid } from '@/composables/useTimetableGrid';
import { DAY_NAMES, type PeriodScheduleDay, type TimetableSlot } from '@/types/timetable';

const props = withDefaults(
    defineProps<{
        slots: TimetableSlot[];
        periodSchedules: PeriodScheduleDay[];
        workingDays?: number[];
        classSectionId?: string | null;
        readOnly?: boolean;
        showDayHeaders?: boolean;
        compact?: boolean;
    }>(),
    {
        workingDays: () => [],
        classSectionId: null,
        readOnly: false,
        showDayHeaders: true,
        compact: false,
    },
);

const emit = defineEmits<{
    'slot-click': [slot: TimetableSlot | null, day: number, periodId: number];
    'slot-move': [payload: { slotId: string | number; fromDay: number; fromPeriodId: number; toDay: number; toPeriodId: number }];
    'slot-drop-empty': [payload: { day: number; periodId: number; slotId: string | number }];
}>();

const slotsRef = toRef(props, 'slots');
const schedulesRef = toRef(props, 'periodSchedules');
const sectionRef = computed(() => props.classSectionId);
const daysRef = computed(() => props.workingDays);

const {
    workingDays,
    allPeriods,
    grid,
    moveSlotOptimistic,
    setSlots,
    syncFromProps,
} = useTimetableGrid(slotsRef, schedulesRef, {
    classSectionId: sectionRef,
    workingDays: daysRef,
});

watch(
    () => props.slots,
    () => {
        setSlots(props.slots);
        syncFromProps();
    },
    { deep: true },
);

const dragOverKey = ref<string | null>(null);
const draggingSlotId = ref<string | number | null>(null);

const dayLabel = (day: number) => DAY_NAMES[day] ?? `Day ${day}`;

const onCellDragStart = (e: DragEvent, slot: TimetableSlot) => {
    if (props.readOnly) return;
    draggingSlotId.value = slot.id;
    e.dataTransfer?.setData(
        'application/json',
        JSON.stringify({
            slotId: slot.id,
            fromDay: slot.day_of_week,
            fromPeriodId: slot.period_id,
        }),
    );
    e.dataTransfer!.effectAllowed = 'move';
};

const onCellDragEnd = () => {
    dragOverKey.value = null;
    draggingSlotId.value = null;
};

const onCellDragOver = (day: number, periodId: number) => {
    if (props.readOnly) return;
    dragOverKey.value = `${day}:${periodId}`;
};

const onCellDrop = (e: DragEvent, day: number, periodId: number, isBreak: boolean) => {
    if (props.readOnly || isBreak) return;
    e.preventDefault();
    dragOverKey.value = null;

    let payload: { slotId: string | number; fromDay: number; fromPeriodId: number } | null = null;
    try {
        const raw = e.dataTransfer?.getData('application/json');
        if (raw) payload = JSON.parse(raw);
    } catch {
        /* ignore */
    }
    if (!payload?.slotId) return;

    if (payload.fromDay === day && Number(payload.fromPeriodId) === Number(periodId)) {
        return;
    }

    moveSlotOptimistic(payload.slotId, day, periodId);
    emit('slot-move', {
        slotId: payload.slotId,
        fromDay: payload.fromDay,
        fromPeriodId: payload.fromPeriodId,
        toDay: day,
        toPeriodId: periodId,
    });
    draggingSlotId.value = null;
};

const onCellClick = (slot: TimetableSlot | null, day: number, periodId: number) => {
    emit('slot-click', slot, day, periodId);
};

defineExpose({ moveSlotOptimistic, setSlots });
</script>

<template>
    <div class="timetable-grid w-full overflow-x-auto rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900">
        <table class="w-full border-collapse text-sm min-w-[640px]">
            <thead v-if="showDayHeaders">
                <tr class="bg-surface-50 dark:bg-surface-800">
                    <th
                        class="sticky left-0 z-10 bg-surface-50 dark:bg-surface-800 px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-color border-b border-r border-surface-200 dark:border-surface-700 w-28"
                    >
                        Period
                    </th>
                    <th
                        v-for="day in workingDays"
                        :key="day"
                        class="px-2 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-muted-color border-b border-surface-200 dark:border-surface-700 min-w-[120px]"
                    >
                        {{ dayLabel(day) }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, rowIdx) in grid"
                    :key="allPeriods[rowIdx]?.id ?? rowIdx"
                    class="border-b border-surface-100 dark:border-surface-800 last:border-b-0"
                >
                    <td
                        class="sticky left-0 z-10 bg-surface-0 dark:bg-surface-900 px-3 py-2 align-top border-r border-surface-200 dark:border-surface-700"
                    >
                        <div class="text-xs font-semibold text-color">
                            {{ allPeriods[rowIdx]?.name ?? `P${rowIdx + 1}` }}
                        </div>
                        <div
                            v-if="allPeriods[rowIdx] && !allPeriods[rowIdx].is_break"
                            class="text-[10px] text-muted-color mt-0.5"
                        >
                            {{ allPeriods[rowIdx].duration_minutes }}m
                        </div>
                        <div
                            v-else-if="allPeriods[rowIdx]?.is_break"
                            class="text-[10px] text-muted-color mt-0.5"
                        >
                            Break
                        </div>
                    </td>

                    <td
                        v-for="cell in row"
                        :key="`${cell.day}-${cell.periodId}`"
                        class="p-1 align-top"
                        :class="{ 'bg-surface-50/50 dark:bg-surface-800/30': cell.isBreak }"
                    >
                        <TimetableSlotCell
                            :slot="cell.slot"
                            :is-break="cell.isBreak"
                            :period-name="cell.period?.name"
                            :read-only="readOnly"
                            :has-conflict="cell.hasConflict"
                            :is-drag-over="dragOverKey === `${cell.day}:${cell.periodId}`"
                            @click="onCellClick($event, cell.day, cell.periodId)"
                            @dragstart="onCellDragStart"
                            @dragend="onCellDragEnd"
                            @dragover="onCellDragOver(cell.day, cell.periodId)"
                            @drop="onCellDrop($event, cell.day, cell.periodId, cell.isBreak)"
                        />
                    </td>
                </tr>
                <tr v-if="!allPeriods.length">
                    <td
                        :colspan="workingDays.length + 1"
                        class="px-4 py-12 text-center text-muted-color"
                    >
                        No period schedule configured for this timetable.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
