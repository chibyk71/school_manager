<script setup lang="ts">
import { computed, ref, toRef, watch } from 'vue';
import TimetableSlotCell from './TimetableSlotCell.vue';
import { useTimetableGrid } from '@/composables/useTimetableGrid';
import {
    DAY_NAMES,
    type SlotMovePayload,
    type TimetableDaySchedule,
    type TimetableSlot,
    type UUID,
} from '@/types/timetable';

const props = withDefaults(
    defineProps<{
        slots: TimetableSlot[];
        periodSchedules: TimetableDaySchedule[];
        workingDays?: number[];
        classSectionId?: UUID | null;
        readOnly?: boolean;
        showDayHeaders?: boolean;
    }>(),
    {
        workingDays: () => [],
        classSectionId: null,
        readOnly: false,
        showDayHeaders: true,
    },
);

const emit = defineEmits<{
    'slot-click': [slot: TimetableSlot | null, day: number, periodId: number];
    'slot-move': [payload: SlotMovePayload];
}>();

const slotsRef = toRef(props, 'slots');
const schedulesRef = toRef(props, 'periodSchedules');
const sectionRef = computed(() => props.classSectionId);
const daysRef = computed(() => props.workingDays);

const {
    workingDays,
    periodRows,
    rowLabel,
    grid,
    duplicateKeys,
    moveSlotOptimistic,
    setSlots,
    reconcileSlots,
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

const dayLabel = (day: number) => DAY_NAMES[day] ?? `Day ${day}`;

const onCellDragStart = (e: DragEvent, slot: TimetableSlot) => {
    if (props.readOnly) return;
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
};

const onCellDragOver = (day: number, periodId: number) => {
    if (props.readOnly || !periodId) return;
    dragOverKey.value = `${day}:${periodId}`;
};

const onCellDrop = (
    e: DragEvent,
    day: number,
    periodId: number,
    isBreak: boolean,
    hasSchedule: boolean,
) => {
    if (props.readOnly || isBreak || !hasSchedule || !periodId) return;
    e.preventDefault();
    dragOverKey.value = null;

    let data: { slotId: string; fromDay: number; fromPeriodId: number } | null = null;
    try {
        const raw = e.dataTransfer?.getData('application/json');
        if (raw) data = JSON.parse(raw);
    } catch {
        /* ignore */
    }
    if (!data?.slotId) return;

    if (data.fromDay === day && Number(data.fromPeriodId) === Number(periodId)) {
        return;
    }

    const { rollback } = moveSlotOptimistic(data.slotId as UUID, day, periodId);

    emit('slot-move', {
        slotId: data.slotId as UUID,
        fromDay: data.fromDay,
        fromPeriodId: data.fromPeriodId,
        toDay: day,
        toPeriodId: periodId,
        rollback,
    });
};

const onCellClick = (slot: TimetableSlot | null, day: number, periodId: number) => {
    if (!periodId) return;
    emit('slot-click', slot, day, periodId);
};

defineExpose({
    moveSlotOptimistic,
    setSlots,
    reconcileSlots,
});
</script>

<template>
    <div class="timetable-grid w-full overflow-x-auto rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900">
        <div
            v-if="duplicateKeys.length"
            class="px-3 py-2 text-xs bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300 border-b border-red-200 dark:border-red-800"
        >
            <i class="ti ti-alert-triangle" />
            {{ duplicateKeys.length }} cell(s) have duplicate slots - data integrity issue.
        </div>
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
                    :key="periodRows[rowIdx]"
                    class="border-b border-surface-100 dark:border-surface-800 last:border-b-0"
                >
                    <td
                        class="sticky left-0 z-10 bg-surface-0 dark:bg-surface-900 px-3 py-2 align-top border-r border-surface-200 dark:border-surface-700"
                    >
                        <div class="text-xs font-semibold text-color">
                            {{ rowLabel(periodRows[rowIdx]).name }}
                        </div>
                        <div
                            v-if="!rowLabel(periodRows[rowIdx]).isBreak && rowLabel(periodRows[rowIdx]).duration"
                            class="text-[10px] text-muted-color mt-0.5"
                        >
                            {{ rowLabel(periodRows[rowIdx]).duration }}m
                        </div>
                        <div
                            v-else-if="rowLabel(periodRows[rowIdx]).isBreak"
                            class="text-[10px] text-muted-color mt-0.5"
                        >
                            Break
                        </div>
                    </td>

                    <td
                        v-for="cell in row"
                        :key="`${cell.day}-${cell.periodId}-${periodRows[rowIdx]}`"
                        class="p-1 align-top"
                        :class="{
                            'bg-surface-50/50 dark:bg-surface-800/30': cell.isBreak,
                            'bg-surface-100/30 dark:bg-surface-900/20': !cell.hasSchedule || !cell.period,
                        }"
                    >
                        <TimetableSlotCell
                            :slot="cell.slot"
                            :period="cell.period"
                            :read-only="readOnly"
                            :has-conflict="cell.hasConflict"
                            :is-duplicate="cell.isDuplicate"
                            :unavailable="!cell.hasSchedule || !cell.period"
                            :is-drag-over="dragOverKey === `${cell.day}:${cell.periodId}`"
                            @click="onCellClick($event, cell.day, cell.periodId)"
                            @dragstart="onCellDragStart"
                            @dragend="onCellDragEnd"
                            @dragover="onCellDragOver(cell.day, cell.periodId)"
                            @drop="onCellDrop($event, cell.day, cell.periodId, cell.isBreak, cell.hasSchedule)"
                        />
                    </td>
                </tr>
                <tr v-if="!periodRows.length">
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
