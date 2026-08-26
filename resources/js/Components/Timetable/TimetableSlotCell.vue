<script setup lang="ts">
import { computed } from 'vue';
import type { ClassPeriodRef, TimetableSlot } from '@/types/timetable';

const props = withDefaults(
    defineProps<{
        slot?: TimetableSlot | null;
        extraSlots?: TimetableSlot[];
        period?: ClassPeriodRef | null;
        readOnly?: boolean;
        isDragOver?: boolean;
        hasConflict?: boolean;
        isDuplicate?: boolean;
        /** Day has no period at this visual row */
        unavailable?: boolean;
    }>(),
    {
        slot: null,
        extraSlots: () => [],
        period: null,
        readOnly: false,
        isDragOver: false,
        hasConflict: false,
        isDuplicate: false,
        unavailable: false,
    },
);

const emit = defineEmits<{
    click: [slot: TimetableSlot | null];
    dragstart: [event: DragEvent, slot: TimetableSlot];
    dragend: [event: DragEvent];
    drop: [event: DragEvent];
    dragover: [event: DragEvent];
}>();

const isBreak = computed(() => !!props.period?.is_break);
const subjectColor = computed(() => props.slot?.subject_color || '#6366f1');
const allSlots = computed(() => {
    const list: TimetableSlot[] = [];
    if (props.slot) list.push(props.slot);
    for (const s of props.extraSlots ?? []) list.push(s);
    return list;
});

const cellClasses = computed(() => {
    if (props.unavailable) {
        return 'bg-surface-100/40 dark:bg-surface-800/20 border-transparent';
    }
    if (isBreak.value) {
        return 'bg-surface-100 dark:bg-surface-800 text-muted-color border-dashed border-surface-300 dark:border-surface-600';
    }
    if (props.isDuplicate) {
        return 'bg-orange-50 dark:bg-orange-950/40 border-orange-400 dark:border-orange-600 ring-1 ring-orange-300 dark:ring-orange-700';
    }
    if (props.hasConflict || props.slot?.has_conflict) {
        return 'bg-red-50 dark:bg-red-950/40 border-red-400 dark:border-red-600 ring-1 ring-red-300 dark:ring-red-700';
    }
    if (props.slot) {
        return 'bg-white dark:bg-surface-900 border-surface-200 dark:border-surface-700 hover:shadow-md';
    }
    return 'bg-surface-50/50 dark:bg-surface-900/40 border-surface-200 dark:border-surface-700 border-dashed hover:bg-primary-50/30 dark:hover:bg-primary-900/20';
});

const onDragStart = (e: DragEvent) => {
    if (props.readOnly || !props.slot || isBreak.value || props.unavailable) {
        e.preventDefault();
        return;
    }
    emit('dragstart', e, props.slot);
};

const onDrop = (e: DragEvent) => {
    if (props.readOnly || isBreak.value || props.unavailable) return;
    e.preventDefault();
    emit('drop', e);
};

const onDragOver = (e: DragEvent) => {
    if (props.readOnly || isBreak.value || props.unavailable) return;
    e.preventDefault();
    emit('dragover', e);
};
</script>

<template>
    <div
        class="timetable-slot-cell relative min-h-[4.5rem] rounded-md border p-1.5 text-left transition-all duration-150 select-none"
        :class="[
            cellClasses,
            {
                'cursor-grab active:cursor-grabbing':
                    slot && !readOnly && !isBreak && !unavailable,
                'cursor-default': readOnly || isBreak || unavailable,
                'ring-2 ring-primary-400 ring-offset-1': isDragOver && !isBreak && !unavailable,
                'opacity-60': isBreak,
            },
        ]"
        :draggable="!!slot && !readOnly && !isBreak && !unavailable"
        @click="emit('click', slot ?? null)"
        @dragstart="onDragStart"
        @dragend="emit('dragend', $event)"
        @drop="onDrop"
        @dragover="onDragOver"
    >
        <template v-if="unavailable">
            <div
                class="flex h-full min-h-[3.5rem] items-center justify-center text-[10px] text-muted-color/50"
            >
                Not scheduled
            </div>
        </template>

        <template v-else-if="isBreak">
            <div class="flex h-full items-center justify-center text-xs font-medium text-muted-color">
                {{ period?.name || 'Break' }}
            </div>
        </template>

        <template v-else-if="slot">
            <div
                class="absolute left-0 top-0 bottom-0 w-1 rounded-l-md"
                :style="{ backgroundColor: subjectColor }"
            />
            <div class="pl-1.5 space-y-1">
                <div
                    v-for="(s, i) in allSlots"
                    :key="s.id"
                    :class="i > 0 ? 'pt-1 border-t border-orange-200 dark:border-orange-800' : ''"
                >
                    <div class="text-xs font-semibold leading-tight text-color line-clamp-2">
                        {{ s.subject_name || 'Subject' }}
                    </div>
                    <div
                        v-if="s.teacher_name"
                        class="mt-0.5 text-[11px] text-muted-color truncate"
                        :title="s.teacher_full_name || s.teacher_name"
                    >
                        {{ s.teacher_name }}
                    </div>
                    <div
                        v-if="s.is_manually_placed"
                        class="mt-0.5 inline-flex items-center gap-0.5 text-[10px] text-primary-600 dark:text-primary-400"
                        title="Manually placed"
                    >
                        <i class="ti ti-hand-finger text-[10px]" />
                        Manual
                    </div>
                </div>
                <div
                    v-if="isDuplicate"
                    class="text-[10px] text-orange-700 dark:text-orange-300 font-medium"
                    title="Multiple slots in this cell"
                >
                    {{ allSlots.length }} assignments (duplicate)
                </div>
            </div>
            <div
                v-if="hasConflict || slot?.has_conflict"
                class="absolute right-1 top-1"
                title="Scheduling conflict"
            >
                <i class="ti ti-alert-triangle text-red-500 text-sm" />
            </div>
        </template>

        <template v-else>
            <div
                class="flex h-full min-h-[3.5rem] items-center justify-center text-[11px] text-muted-color/60"
            >
                —
            </div>
        </template>
    </div>
</template>
