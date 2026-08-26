<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Button, Card, Select, Tag, useToast } from 'primevue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TimetableGrid from '@/Components/Timetable/TimetableGrid.vue';
import ConflictPanel from '@/Components/Timetable/ConflictPanel.vue';
import { useTimetableConflicts } from '@/composables/useTimetableConflicts';
import {
    TIMETABLE_STATUS_CONFIG,
    normalizeTimetable,
    type BuilderClassSection,
    type SlotMovePayload,
    type Timetable,
    type TimetableConflict,
    type TimetableDaySchedule,
    type TimetableSlot,
    type UUID,
} from '@/types/timetable';

const props = defineProps<{
    timetable: Timetable;
    slots: TimetableSlot[];
    classSections?: BuilderClassSection[];
    periodSchedules?: TimetableDaySchedule[];
    conflicts?: TimetableConflict[];
}>();

const toast = useToast();
const gridRef = ref<InstanceType<typeof TimetableGrid> | null>(null);

const slots = ref<TimetableSlot[]>([...(props.slots ?? [])]);
const selectedSectionId = ref<UUID | null>(props.classSections?.[0]?.id ?? null);
const generating = ref(false);
const activating = ref(false);
const moving = ref(false);
const showConflicts = ref(true);

const tt = computed(() => normalizeTimetable(props.timetable as Timetable & Record<string, unknown>));
const timetableId = computed(() => tt.value?.id);

const {
    unresolved,
    unresolvedCount,
    resolvingId,
    loading: conflictsLoading,
    lastError: conflictsError,
    setConflicts,
    fetchConflicts,
    resolveConflict,
} = useTimetableConflicts(timetableId, props.conflicts ?? []);

onMounted(() => {
    if (props.conflicts?.length) {
        setConflicts(props.conflicts);
    } else {
        fetchConflicts();
    }
});

watch(
    () => props.slots,
    (v) => {
        slots.value = [...(v ?? [])];
    },
);

const sectionOptions = computed(() =>
    (props.classSections ?? []).map((s) => ({
        label: s.display_name || s.name,
        value: s.id,
    })),
);

const filteredSlots = computed(() => {
    if (!selectedSectionId.value) return slots.value;
    return slots.value.filter((s) => s.class_section_id === selectedSectionId.value);
});

const statusCfg = computed(
    () => TIMETABLE_STATUS_CONFIG[tt.value.status] ?? TIMETABLE_STATUS_CONFIG.draft,
);

/**
 * Persist slot move. Backend expects class_period_id + day_of_week.
 * On failure, invoke payload.rollback so the grid never sticks on a rejected move.
 */
const onSlotMove = async (payload: SlotMovePayload) => {
    moving.value = true;
    try {
        const { data } = await axios.put(
            `/timetables/${tt.value.id}/slots/${payload.slotId}`,
            {
                day_of_week: payload.toDay,
                class_period_id: payload.toPeriodId,
            },
            { headers: { Accept: 'application/json' } },
        );
        const updated = (data?.data ?? data?.slot ?? data) as TimetableSlot | undefined;
        if (updated?.id) {
            const idx = slots.value.findIndex((s) => s.id === updated.id);
            if (idx !== -1) {
                const next = [...slots.value];
                next[idx] = { ...next[idx], ...updated };
                slots.value = next;
            }
            gridRef.value?.reconcileSlots?.([updated]);
        }
        toast.add({ severity: 'success', summary: 'Slot moved', life: 2000 });
        fetchConflicts();
    } catch (err: unknown) {
        payload.rollback();
        const message =
            (err as { response?: { data?: { message?: string } } })?.response?.data
                ?.message ?? 'Could not move slot';
        toast.add({
            severity: 'error',
            summary: 'Move rejected',
            detail: message,
            life: 5000,
        });
    } finally {
        moving.value = false;
    }
};

const onConflictResolve = async (payload: {
    conflictId: number;
    strategy: 'use_suggestion' | 'manual' | 'skip';
    suggestionIndex?: number;
    dayOfWeek?: number;
    classPeriodId?: number;
    notes?: string;
}) => {
    const result = await resolveConflict(payload.conflictId, {
        resolution_strategy: payload.strategy,
        suggestion_index: payload.suggestionIndex,
        day_of_week: payload.dayOfWeek,
        class_period_id: payload.classPeriodId,
        resolution_notes: payload.notes,
    });
    if (!result.ok) return;

    if (result.slot) {
        const idx = slots.value.findIndex((s) => s.id === result.slot!.id);
        if (idx === -1) {
            slots.value = [...slots.value, result.slot];
        } else {
            const next = [...slots.value];
            next[idx] = { ...next[idx], ...result.slot };
            slots.value = next;
        }
        gridRef.value?.reconcileSlots?.([result.slot]);
    } else {
        router.reload({ only: ['slots', 'timetable'] });
    }
};

const runGenerate = () => {
    generating.value = true;
    router.post(
        `/timetables/${tt.value.id}/generate`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                generating.value = false;
            },
            onSuccess: () => {
                toast.add({
                    severity: 'info',
                    summary: 'Generation queued',
                    detail: 'Slots will appear when generation completes.',
                    life: 4000,
                });
            },
        },
    );
};

const runActivate = () => {
    if (unresolvedCount.value > 0 || !tt.value.can_activate) {
        toast.add({
            severity: 'warn',
            summary: 'Cannot activate',
            detail: 'Resolve all conflicts before activating this timetable.',
            life: 4000,
        });
        showConflicts.value = true;
        return;
    }
    activating.value = true;
    router.post(
        `/timetables/${tt.value.id}/activate`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                activating.value = false;
            },
        },
    );
};

const goBack = () => router.visit('/timetables');
</script>

<template>
    <AuthenticatedLayout
        :title="tt.title"
        :crumb="[
            { label: 'Dashboard', url: '/dashboard' },
            { label: 'Academic' },
            { label: 'Timetables', url: '/timetables' },
            { label: 'Builder' },
        ]"
        :buttons="[
            {
                label: 'Back',
                icon: 'ti ti-arrow-left',
                severity: 'secondary',
                onClick: goBack,
            },
            {
                label: 'Generate',
                icon: 'ti ti-wand',
                severity: 'info',
                loading: generating,
                onClick: runGenerate,
            },
            {
                label: 'Activate',
                icon: 'ti ti-player-play',
                severity: 'success',
                loading: activating,
                disabled: unresolvedCount > 0 || !tt.can_activate,
                onClick: runActivate,
            },
        ]"
    >
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <Tag :value="statusCfg.label" :severity="statusCfg.severity" />
            <span class="text-sm text-muted-color">
                {{ tt.school_section_name }} · {{ tt.term_name }}
            </span>
            <span v-if="tt.generated_at" class="text-xs text-muted-color">
                Generated {{ tt.generated_at }}
                <template v-if="tt.generated_by_name"> by {{ tt.generated_by_name }}</template>
            </span>
            <Button
                :label="showConflicts ? 'Hide conflicts' : `Conflicts (${unresolvedCount})`"
                :icon="showConflicts ? 'ti ti-panel-right-close' : 'ti ti-alert-triangle'"
                severity="secondary"
                size="small"
                outlined
                class="ml-auto"
                @click="showConflicts = !showConflicts"
            />
        </div>

        <div class="flex flex-col lg:flex-row gap-4 items-start">
            <Card class="flex-1 min-w-0 w-full">
                <template #content>
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <label class="text-xs font-medium text-muted-color">Class section</label>
                        <Select
                            v-model="selectedSectionId"
                            :options="sectionOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Select arm"
                            class="w-56"
                        />
                        <span v-if="moving" class="text-xs text-muted-color">
                            <i class="ti ti-loader animate-spin" /> Saving…
                        </span>
                    </div>

                    <TimetableGrid
                        ref="gridRef"
                        :slots="filteredSlots"
                        :period-schedules="periodSchedules ?? []"
                        :working-days="tt.working_days"
                        :class-section-id="selectedSectionId"
                        :read-only="false"
                        @slot-move="onSlotMove"
                    />
                </template>
            </Card>

            <div
                v-if="showConflicts"
                class="w-full lg:w-80 shrink-0 lg:sticky lg:top-4"
                style="max-height: calc(100vh - 8rem)"
            >
                <ConflictPanel
                    class="h-full max-h-[70vh]"
                    :conflicts="unresolved"
                    :loading="conflictsLoading"
                    :resolving-id="resolvingId"
                    :last-error="conflictsError"
                    @resolve="onConflictResolve"
                    @refresh="fetchConflicts"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
