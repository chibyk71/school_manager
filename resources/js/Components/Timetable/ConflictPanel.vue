<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button, Card, Message, Select, Textarea, Tag } from 'primevue';
import {
    CONFLICT_TYPE_LABELS,
    DAY_NAMES,
    type ConflictSuggestion,
    type ResolutionStrategy,
    type TimetableConflict,
} from '@/types/timetable';

const props = withDefaults(
    defineProps<{
        conflicts: TimetableConflict[];
        loading?: boolean;
        resolvingId?: number | null;
        lastError?: string | null;
    }>(),
    {
        loading: false,
        resolvingId: null,
        lastError: null,
    },
);

const emit = defineEmits<{
    resolve: [
        payload: {
            conflictId: number;
            strategy: ResolutionStrategy;
            suggestionIndex?: number;
            dayOfWeek?: number;
            classPeriodId?: number;
            notes?: string;
        },
    ];
    refresh: [];
}>();

const expandedId = ref<number | null>(null);
const skipNotes = ref<Record<number, string>>({});
const selectedSuggestion = ref<Record<number, number>>({});

const typeLabel = (t: string) =>
    CONFLICT_TYPE_LABELS[t as keyof typeof CONFLICT_TYPE_LABELS] ?? t;

const dayLabel = (d?: number | null) =>
    d != null ? (DAY_NAMES[d] ?? `Day ${d}`) : '—';

const suggestionOptions = (c: TimetableConflict) =>
    (c.suggested_alternatives ?? []).map((s: ConflictSuggestion, i: number) => ({
        label: [
            DAY_NAMES[s.day_of_week] ?? `Day ${s.day_of_week}`,
            s.period_name ?? `Period ${s.class_period_id}`,
            s.reason ? `(${s.reason})` : null,
        ]
            .filter(Boolean)
            .join(' · '),
        value: i,
    }));

const isResolving = (id: number) => props.resolvingId === id;

const toggle = (id: number) => {
    expandedId.value = expandedId.value === id ? null : id;
};

const onUseSuggestion = (c: TimetableConflict) => {
    const idx = selectedSuggestion.value[c.id] ?? 0;
    emit('resolve', {
        conflictId: c.id,
        strategy: 'use_suggestion',
        suggestionIndex: idx,
    });
};

const onSkip = (c: TimetableConflict) => {
    const notes = (skipNotes.value[c.id] ?? '').trim();
    if (!notes) return;
    emit('resolve', {
        conflictId: c.id,
        strategy: 'skip',
        notes,
    });
};

const count = computed(() => props.conflicts?.length ?? 0);
</script>

<template>
    <Card class="conflict-panel border border-surface-200 dark:border-surface-700">
        <template #title>
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-semibold">
                    Conflicts
                    <Tag
                        v-if="count"
                        :value="String(count)"
                        severity="danger"
                        class="ml-1 text-xs"
                    />
                </span>
                <Button
                    icon="ti ti-refresh"
                    text
                    rounded
                    size="small"
                    :loading="loading"
                    aria-label="Refresh conflicts"
                    @click="emit('refresh')"
                />
            </div>
        </template>
        <template #content>
            <Message
                v-if="lastError"
                severity="error"
                :closable="false"
                class="mb-3 text-sm"
            >
                {{ lastError }}
            </Message>

            <div v-if="loading && !conflicts.length" class="py-8 text-center text-sm text-muted-color">
                <i class="ti ti-loader animate-spin" /> Loading…
            </div>

            <div
                v-else-if="!conflicts.length"
                class="py-8 text-center text-sm text-muted-color"
            >
                <i class="ti ti-check text-green-500 text-lg block mb-1" />
                No unresolved conflicts
            </div>

            <ul v-else class="space-y-2 max-h-[60vh] overflow-y-auto pr-1">
                <li
                    v-for="c in conflicts"
                    :key="c.id"
                    class="rounded-md border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900"
                >
                    <button
                        type="button"
                        class="w-full text-left px-3 py-2 flex items-start gap-2 hover:bg-surface-50 dark:hover:bg-surface-800/50"
                        @click="toggle(c.id)"
                    >
                        <i
                            class="ti text-red-500 mt-0.5"
                            :class="
                                expandedId === c.id
                                    ? 'ti-chevron-down'
                                    : 'ti-chevron-right'
                            "
                        />
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-semibold text-color line-clamp-2">
                                {{ typeLabel(c.conflict_type) }}
                            </div>
                            <div class="text-[11px] text-muted-color mt-0.5 line-clamp-2">
                                {{ c.description }}
                            </div>
                            <div class="flex flex-wrap gap-1 mt-1">
                                <Tag
                                    v-if="c.subject_name"
                                    :value="c.subject_name"
                                    severity="secondary"
                                    class="text-[10px]"
                                />
                                <Tag
                                    v-if="c.teacher_name"
                                    :value="c.teacher_name"
                                    severity="info"
                                    class="text-[10px]"
                                />
                                <Tag
                                    v-if="c.class_section_name"
                                    :value="c.class_section_name"
                                    severity="contrast"
                                    class="text-[10px]"
                                />
                            </div>
                        </div>
                    </button>

                    <div
                        v-if="expandedId === c.id"
                        class="px-3 pb-3 pt-1 border-t border-surface-100 dark:border-surface-800 space-y-3"
                    >
                        <div class="text-[11px] text-muted-color">
                            <span v-if="c.day_of_week != null">
                                {{ dayLabel(c.day_of_week) }}
                            </span>
                            <span v-if="c.class_period_id != null">
                                · Period #{{ c.class_period_id }}
                            </span>
                        </div>

                        <!-- Use suggestion -->
                        <div
                            v-if="(c.suggested_alternatives?.length ?? 0) > 0"
                            class="space-y-2"
                        >
                            <label class="text-xs font-medium text-muted-color">
                                Suggested placement
                            </label>
                            <Select
                                v-model="selectedSuggestion[c.id]"
                                :options="suggestionOptions(c)"
                                option-label="label"
                                option-value="value"
                                placeholder="Choose suggestion"
                                class="w-full text-sm"
                                :disabled="isResolving(c.id)"
                            />
                            <Button
                                label="Use suggestion"
                                icon="ti ti-check"
                                size="small"
                                class="w-full"
                                :loading="isResolving(c.id)"
                                @click="onUseSuggestion(c)"
                            />
                        </div>

                        <!-- Skip without placing -->
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-muted-color">
                                Skip without placing
                            </label>
                            <Textarea
                                v-model="skipNotes[c.id]"
                                rows="2"
                                class="w-full text-sm"
                                placeholder="Reason required to skip…"
                                :disabled="isResolving(c.id)"
                            />
                            <Button
                                label="Skip"
                                icon="ti ti-player-skip-forward"
                                severity="secondary"
                                size="small"
                                outlined
                                class="w-full"
                                :disabled="!(skipNotes[c.id] || '').trim()"
                                :loading="isResolving(c.id)"
                                @click="onSkip(c)"
                            />
                        </div>
                    </div>
                </li>
            </ul>
        </template>
    </Card>
</template>
