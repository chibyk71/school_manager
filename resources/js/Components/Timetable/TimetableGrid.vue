<script setup lang="ts">
import { computed, ref, toRef } from 'vue';
import TimetableSlotCell from './TimetableSlotCell.vue';
import { useTimetableGrid } from '@/composables/useTimetableGrid';
import {
    DAY_NAMES,
    type NumericId,
    type SlotMovePayload,
    type TimetableDaySchedule,
    type TimetableSlot,
} from '@/types/timetable';

const props = withDefaults(
    defineProps<{
        slots: TimetableSlot[];
        periodSchedules: TimetableDaySchedule[];
        /** undefined = infer; [] = none; [n,…] = explicit */
        workingDays?: number[];
        readOnly?: boolean;
        /** Disable drag/drop (e.g. while a move is saving) */
        disabled?: boolean;
        showDayHeaders?: boolean;
    }>(),
    {
        workingDays: undefined,
        readOnly: false,
        disabled: false,
        showDayHeaders: true,
    },
);

const emit = defineEmits<{
    'slot-click': [slot: TimetableSlot | null, day: number, periodId: NumericId | null];
    'slot-move': [payload: SlotMovePayload];
}>();

const slotsRef = toRef(props, 'slots');
const schedulesRef = toRef(props, 'periodSchedules');
const daysRef = computed(() => props.workingDays);

const { workingDays, periodRows, rowLabel, grid, duplicateKeys } = useTimetableGrid(
    slotsRef,
    schedulesRef,
    { workingDays: daysRef },
);

const dragOverKey = ref<string | null>(null);

const dayLabel = (day: number) => DAY_NAMES[day] ?? `Day ${day}`;

const interactionLocked = computed(() => props.readOnly || props.disabled);

const onCellDragStart = (e: DragEvent, slot: TimetableSlot) => {
    if (interactionLocked.value) {
        e.preventDefault();
        return;
    }
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

const onCellDragOver = (day: number, periodId: NumericId | null) => {
    if (interactionLocked.value || periodId == null) return;
    dragOverKey.value = `${day}:${periodId}`;
};

const onCellDrop = (
    e: DragEvent,
    day: number,
    periodId: NumericId | null,
    isBreak: boolean,
    hasSchedule: boolean,
) => {
    if (interactionLocked.value || isBreak || !hasSchedule || periodId == null) return;
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

    // Parent owns optimistic update + rollback
    emit('slot-move', {
        slotId: data.slotId,
        fromDay: data.fromDay,
        fromPeriodId: data.fromPeriodId,
        toDay: day,
        toPeriodId: periodId,
    });
};

const onCellClick = (slot: TimetableSlot | null, day: number, periodId: NumericId | null) => {
    emit('slot-click', slot, day, periodId);
};
</script>

<template>
    <div
        class="timetable-grid w-full overflow-x-auto rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900"
        :class="{ 'opacity-70 pointer-events-none': disabled }"
    >
        <div
            v-if="duplicateKeys.length"
            class="px-3 py-2 text-xs bg-orange-50 dark:bg-orange-950/40 text-orange-800 dark:text-orange-200 border-b border-orange-200 dark:border-orange-800"
        >
            <i class="ti ti-alert-triangle" />
            {{ duplicateKeys.length }} cell(s) have duplicate slots — data integrity issue.
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
                            v-if="rowLabel(periodRows[rowIdx]).isBreak"
                            class="text-[10px] text-muted-color mt-0.5"
                        >
                            Break
                        </div>
                    </td>

                    <td
                        v-for="cell in row"
                        :key="`${cell.day}-${cell.periodId ?? 'x'}-${periodRows[rowIdx]}`"
                        class="p-1 align-top"
                        :class="{
                            'bg-surface-50/50 dark:bg-surface-800/30': cell.isBreak,
                            'bg-surface-100/30 dark:bg-surface-900/20': !cell.hasSchedule || !cell.period,
                        }"
                    >
                        <TimetableSlotCell
                            :slot="cell.slot"
                            :extra-slots="cell.extraSlots"
                            :period="cell.period"
                            :read-only="interactionLocked"
                            :has-conflict="cell.hasConflict"
                            :is-duplicate="cell.isDuplicate"
                            :unavailable="!cell.hasSchedule || !cell.period"
                            :is-drag-over="
                                cell.periodId != null &&
                                dragOverKey === `${cell.day}:${cell.periodId}`
                            "
                            @click="onCellClick($event, cell.day, cell.periodId)"
                            @dragstart="onCellDragStart"
                            @dragend="onCellDragEnd"
                            @dragover="onCellDragOver(cell.day, cell.periodId)"
                            @drop="
                                onCellDrop(
                                    $event,
                                    cell.day,
                                    cell.periodId,
                                    cell.isBreak,
                                    cell.hasSchedule,
                                )
                            "
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
