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
    const suggestion = (c.suggested_alternatives ?? [])[idx];
    if (!suggestion) return;
    emit('resolve', {
        conflictId: c.id,
        strategy: 'use_suggestion',
        suggestionIndex: idx,
        dayOfWeek: suggestion.day_of_week,
        classPeriodId: suggestion.class_period_id,
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

            <div v-if="loading && !count" class="py-8 text-center text-sm text-muted-color">
                Loading conflicts…
            </div>

            <div v-else-if="!count" class="py-8 text-center text-sm text-muted-color">
                No unresolved conflicts.
            </div>

            <ul v-else class="space-y-2 max-h-[60vh] overflow-y-auto">
                <li
                    v-for="c in conflicts"
                    :key="c.id"
                    class="rounded-md border border-surface-200 dark:border-surface-700 p-2.5"
                >
                    <button
                        type="button"
                        class="w-full text-left flex items-start gap-2"
                        @click="toggle(c.id)"
                    >
                        <i
                            class="ti mt-0.5"
                            :class="
                                expandedId === c.id
                                    ? 'ti-chevron-down'
                                    : 'ti-chevron-right'
                            "
                        />
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-semibold text-color">
                                {{ typeLabel(c.conflict_type) }}
                            </div>
                            <div class="text-[11px] text-muted-color mt-0.5 line-clamp-2">
                                {{ c.description }}
                            </div>
                            <div class="text-[10px] text-muted-color mt-1">
                                <span v-if="c.class_section_name">{{ c.class_section_name }} · </span>
                                <span v-if="c.subject_name">{{ c.subject_name }} · </span>
                                <span v-if="c.teacher_name">{{ c.teacher_name }} · </span>
                                {{ dayLabel(c.day_of_week) }}
                            </div>
                        </div>
                    </button>

                    <div v-if="expandedId === c.id" class="mt-3 space-y-3 pl-5">
                        <div v-if="(c.suggested_alternatives ?? []).length">
                            <label class="text-[11px] font-medium text-muted-color"
                                >Suggested placement</label
                            >
                            <Select
                                v-model="selectedSuggestion[c.id]"
                                :options="suggestionOptions(c)"
                                option-label="label"
                                option-value="value"
                                class="w-full mt-1"
                                :disabled="isResolving(c.id)"
                            />
                            <Button
                                label="Use suggestion"
                                icon="ti ti-check"
                                size="small"
                                class="mt-2 w-full"
                                :loading="isResolving(c.id)"
                                @click="onUseSuggestion(c)"
                            />
                        </div>

                        <div>
                            <label class="text-[11px] font-medium text-muted-color"
                                >Skip without placing</label
                            >
                            <p class="text-[10px] text-muted-color mt-0.5 mb-1">
                                Resolves this conflict but leaves the assignment unscheduled.
                            </p>
                            <Textarea
                                v-model="skipNotes[c.id]"
                                rows="2"
                                class="w-full text-sm"
                                placeholder="Reason required…"
                                :disabled="isResolving(c.id)"
                            />
                            <Button
                                label="Skip without placing"
                                icon="ti ti-player-skip-forward"
                                severity="secondary"
                                size="small"
                                class="mt-2 w-full"
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
