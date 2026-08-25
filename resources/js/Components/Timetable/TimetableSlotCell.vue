<script setup lang="ts">
import { computed } from 'vue';
import type { TimetableSlot } from '@/types/timetable';

const props = withDefaults(
    defineProps<{
        slot?: TimetableSlot | null;
        isBreak?: boolean;
        periodName?: string;
        readOnly?: boolean;
        isDragOver?: boolean;
        hasConflict?: boolean;
    }>(),
    {
        slot: null,
        isBreak: false,
        periodName: '',
        readOnly: false,
        isDragOver: false,
        hasConflict: false,
    },
);

const emit = defineEmits<{
    click: [slot: TimetableSlot | null];
    dragstart: [event: DragEvent, slot: TimetableSlot];
    dragend: [event: DragEvent];
    drop: [event: DragEvent];
    dragover: [event: DragEvent];
}>();

const isEmpty = computed(() => !props.slot && !props.isBreak);
const isConflict = computed(() => props.hasConflict || !!props.slot?.has_conflict);
const subjectColor = computed(() => props.slot?.subject_color || '#6366f1');

const cellClasses = computed(() => {
    if (props.isBreak) {
        return 'bg-surface-100 dark:bg-surface-800 text-muted-color border-dashed border-surface-300 dark:border-surface-600';
    }
    if (isConflict.value) {
        return 'bg-red-50 dark:bg-red-950/40 border-red-400 dark:border-red-600 ring-1 ring-red-300 dark:ring-red-700';
    }
    if (props.slot) {
        return 'bg-white dark:bg-surface-900 border-surface-200 dark:border-surface-700 hover:shadow-md';
    }
    return 'bg-surface-50/50 dark:bg-surface-900/40 border-surface-200 dark:border-surface-700 border-dashed hover:bg-primary-50/30 dark:hover:bg-primary-900/20';
});

const onDragStart = (e: DragEvent) => {
    if (props.readOnly || !props.slot || props.isBreak) {
        e.preventDefault();
        return;
    }
    emit('dragstart', e, props.slot);
};

const onDrop = (e: DragEvent) => {
    if (props.readOnly || props.isBreak) return;
    e.preventDefault();
    emit('drop', e);
};

const onDragOver = (e: DragEvent) => {
    if (props.readOnly || props.isBreak) return;
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
                'cursor-grab active:cursor-grabbing': slot && !readOnly && !isBreak,
                'cursor-default': readOnly || isBreak,
                'ring-2 ring-primary-400 ring-offset-1': isDragOver && !isBreak,
                'opacity-60': isBreak,
            },
        ]"
        :draggable="!!slot && !readOnly && !isBreak"
        @click="emit('click', slot ?? null)"
        @dragstart="onDragStart"
        @dragend="emit('dragend', $event)"
        @drop="onDrop"
        @dragover="onDragOver"
    >
        <template v-if="isBreak">
            <div class="flex h-full items-center justify-center text-xs font-medium text-muted-color">
                {{ periodName || 'Break' }}
            </div>
        </template>

        <template v-else-if="slot">
            <div
                class="absolute left-0 top-0 bottom-0 w-1 rounded-l-md"
                :style="{ backgroundColor: subjectColor }"
            />
            <div class="pl-1.5">
                <div class="text-xs font-semibold leading-tight text-color line-clamp-2">
                    {{ slot.subject_name || 'Subject' }}
                </div>
                <div
                    v-if="slot.teacher_name"
                    class="mt-0.5 text-[11px] text-muted-color truncate"
                    :title="slot.teacher_full_name || slot.teacher_name"
                >
                    {{ slot.teacher_name }}
                </div>
                <div
                    v-if="slot.is_manually_placed"
                    class="mt-1 inline-flex items-center gap-0.5 text-[10px] text-primary-600 dark:text-primary-400"
                    title="Manually placed"
                >
                    <i class="ti ti-hand-finger text-[10px]" />
                    Manual
                </div>
            </div>
            <div
                v-if="isConflict"
                class="absolute right-1 top-1"
                title="Conflict"
            >
                <i class="ti ti-alert-triangle text-red-500 text-sm" />
            </div>
        </template>

        <template v-else>
            <div class="flex h-full min-h-[3.5rem] items-center justify-center text-[11px] text-muted-color/60">
                —
            </div>
        </template>
    </div>
</template>
