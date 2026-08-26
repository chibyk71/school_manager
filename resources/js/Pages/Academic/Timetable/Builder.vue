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
    type RawTimetableResource,
    type SlotMovePayload,
    type TimetableConflict,
    type TimetableDaySchedule,
    type TimetableSlot,
    type UUID,
} from '@/types/timetable';

const props = defineProps<{
    timetable: RawTimetableResource;
    slots: TimetableSlot[];
    classSections?: BuilderClassSection[];
    periodSchedules?: TimetableDaySchedule[];
    conflicts?: TimetableConflict[];
}>();

const toast = useToast();

/** Single source of truth for slots — grid is a pure view. */
const slots = ref<TimetableSlot[]>([...(props.slots ?? [])]);

const querySection = (() => {
    try {
        const q = new URLSearchParams(window.location.search).get('class_section_id');
        return q || null;
    } catch {
        return null;
    }
})();

const selectedSectionId = ref<UUID | null>(
    (querySection as UUID | null) ??
        (props.classSections?.length === 1 ? props.classSections[0].id : null),
);

const generating = ref(false);
const activating = ref(false);
const moving = ref(false);
const showConflicts = ref(true);

const tt = computed(() => normalizeTimetable(props.timetable));
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

// Only sync from Inertia props when not mid-mutation
watch(
    () => props.slots,
    (v) => {
        if (moving.value) return;
        slots.value = [...(v ?? [])];
    },
);

const sectionOptions = computed(() =>
    (props.classSections ?? []).map((s) => ({
        label: s.display_name || s.name,
        value: s.id,
    })),
);

/** Parent filters; grid receives already-filtered slots. */
const filteredSlots = computed(() => {
    if (!selectedSectionId.value) return slots.value;
    return slots.value.filter((s) => s.class_section_id === selectedSectionId.value);
});

const statusCfg = computed(
    () => TIMETABLE_STATUS_CONFIG[tt.value.status] ?? TIMETABLE_STATUS_CONFIG.draft,
);

const onSectionChange = (id: UUID | null) => {
    selectedSectionId.value = id;
    try {
        const url = new URL(window.location.href);
        if (id) url.searchParams.set('class_section_id', id);
        else url.searchParams.delete('class_section_id');
        window.history.replaceState({}, '', url.toString());
    } catch {
        /* ignore */
    }
};

/**
 * Persist slot move. Backend expects class_period_id + day_of_week.
 * Optimistic update lives here (single source of truth); concurrent moves blocked by `moving`.
 */
const onSlotMove = async (payload: SlotMovePayload) => {
    if (moving.value) return;
    moving.value = true;

    const previous = slots.value.map((s) => ({ ...s }));
    const idx = slots.value.findIndex((s) => s.id === payload.slotId);
    if (idx === -1) {
        moving.value = false;
        return;
    }

    const next = [...slots.value];
    next[idx] = {
        ...next[idx],
        day_of_week: payload.toDay,
        period_id: payload.toPeriodId,
        is_manually_placed: true,
    };
    slots.value = next;

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
            const i = slots.value.findIndex((s) => s.id === updated.id);
            if (i !== -1) {
                const reconciled = [...slots.value];
                reconciled[i] = { ...reconciled[i], ...updated };
                slots.value = reconciled;
            }
        }
        toast.add({ severity: 'success', summary: 'Slot moved', life: 2000 });
        fetchConflicts();
    } catch (err: unknown) {
        slots.value = previous;
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
    } else if (payload.strategy !== 'skip') {
        router.reload({ only: ['slots', 'timetable'] });
    }
};

/** Stable fingerprint so regeneration with the same slot count still detects change. */
const slotFingerprint = (list: TimetableSlot[]): string =>
    [...list]
        .map(
            (s) =>
                `${s.id}:${s.day_of_week}:${s.period_id}:${s.subject_id ?? ''}:${s.teacher_id ?? ''}`,
        )
        .sort()
        .join('|');

/**
 * Poll Inertia props until generation is reflected, or attempts are exhausted.
 * Completion = slot fingerprint change and/or timetable generated_at / updated_at change
 * (count alone misses same-size regenerations).
 * Keeps `generating` true the whole time so a second Generate cannot be started.
 */
const pollAfterGenerate = (baseline: {
    fingerprint: string;
    generatedAt: string | null;
    updatedAt: string;
}) => {
    let attempts = 0;
    const maxAttempts = 10;
    const intervalMs = 3000;

    const finishPolling = () => {
        generating.value = false;
    };

    const isComplete = (): boolean => {
        const fpChanged = slotFingerprint(slots.value) !== baseline.fingerprint;
        const generatedChanged = (tt.value.generated_at ?? null) !== baseline.generatedAt;
        const updatedChanged = (tt.value.updated_at ?? '') !== baseline.updatedAt;
        return fpChanged || generatedChanged || updatedChanged;
    };

    const tick = () => {
        attempts += 1;
        router.reload({
            only: ['slots', 'timetable', 'conflicts'],
            preserveScroll: true,
            onFinish: () => {
                if (isComplete()) {
                    toast.add({
                        severity: 'success',
                        summary: 'Timetable updated',
                        detail: 'Generated slots are now visible.',
                        life: 3000,
                    });
                    fetchConflicts();
                    finishPolling();
                    return;
                }
                if (attempts >= maxAttempts) {
                    toast.add({
                        severity: 'info',
                        summary: 'Still generating',
                        detail: 'Refresh the page if new slots do not appear.',
                        life: 6000,
                    });
                    finishPolling();
                    return;
                }
                window.setTimeout(tick, intervalMs);
            },
        });
    };

    window.setTimeout(tick, intervalMs);
};

const runGenerate = () => {
    if (generating.value) return;
    generating.value = true;
    const baseline = {
        fingerprint: slotFingerprint(slots.value),
        generatedAt: tt.value.generated_at ?? null,
        updatedAt: tt.value.updated_at ?? '',
    };
    router.post(
        `/timetables/${tt.value.id}/generate`,
        {},
        {
            preserveScroll: true,
            onError: () => {
                generating.value = false;
            },
            onSuccess: () => {
                toast.add({
                    severity: 'info',
                    summary: 'Generation queued',
                    detail: 'Waiting for slots to appear — this page will refresh automatically.',
                    life: 4000,
                });
                pollAfterGenerate(baseline);
            },
            // Do not clear `generating` onFinish — polling owns that until complete/timeout.
        },
    );
};

const runActivate = () => {
    if (unresolvedCount.value > 0) {
        toast.add({
            severity: 'warn',
            summary: 'Cannot activate',
            detail: `Resolve ${unresolvedCount.value} conflict(s) before activating.`,
            life: 4000,
        });
        showConflicts.value = true;
        return;
    }
    if (!tt.value.can_activate) {
        toast.add({
            severity: 'warn',
            summary: 'Cannot activate',
            detail: 'This timetable does not meet activation requirements (slots, schedule, or policy).',
            life: 5000,
        });
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
                disabled: generating,
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
                            :model-value="selectedSectionId"
                            :options="sectionOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Select class section"
                            class="w-56"
                            show-clear
                            @update:model-value="onSectionChange"
                        />
                        <span v-if="moving" class="text-xs text-muted-color">
                            <i class="ti ti-loader animate-spin" /> Saving…
                        </span>
                    </div>

                    <div
                        v-if="!selectedSectionId && sectionOptions.length > 1"
                        class="mb-4 rounded-md border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 px-3 py-2 text-sm text-muted-color"
                    >
                        Select a class section to edit its timetable.
                    </div>

                    <TimetableGrid
                        v-else
                        :slots="filteredSlots"
                        :period-schedules="periodSchedules ?? tt.day_schedules ?? []"
                        :working-days="tt.working_days"
                        :read-only="false"
                        :disabled="moving"
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
