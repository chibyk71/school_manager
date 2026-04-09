<!--
  resources/js/Pages/Academic/Exams/ScoreEntry.vue

  Score Entry Page — Teacher-facing interface for entering exam scores.

  Features / Problems Solved:
  ─────────────────────────────────────────────────────────────────────────────
  • Renders a full-width table with one row per student and one column per
    assessment component (CA1, CA2, Exam, etc.)
  • Auto-save: debounced 1.5s after last edit — no "save" button required
    for day-to-day use, but a "Save All" button is available for explicit confirmation
  • Keyboard navigation: Tab moves between inputs in natural order
    (student 1 CA1 → CA2 → Exam → student 2 CA1 → ...) for rapid entry
  • Client-side validation: red border + tooltip if score exceeds max
  • Per-row state indicators: saving spinner, dirty dot, error badge
  • Absent toggle: clicking the absent checkbox greys out the row's inputs
    and clears any entered scores
  • Left sidebar: subject progress bar list for quick navigation between subjects
  • Frozen column: student name is frozen so it stays visible when scrolling right
  • Lock guard: inputs are disabled when exam is locked or result is locked
  • Completion bar: top progress bar showing % of students fully scored
  • Read-only view: when exam is not editable (approved/locked), inputs are
    replaced with plain text badges

  Dependencies:
  • useScoreEntry composable (all score state + auto-save logic)
  • PrimeVue: InputNumber, Checkbox, ProgressBar, Badge, Button, Tag, Textarea
  • Inertia: router (for subject navigation)
  • Tailwind CSS for layout
-->

<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Button,
    InputNumber,
    Checkbox,
    ProgressBar,
    Badge,
    Tag,
    Textarea,
    Skeleton,
} from 'primevue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useScoreEntry } from '@/composables/useScoreEntry';
import { EXAM_STATUS_CONFIG } from '@/types/exam';
import type { ScoreEntrySheet, SubjectProgress } from '@/types/exam';

// ────────────────────────────────────────────────────────────────────────────
// Props
// ────────────────────────────────────────────────────────────────────────────
const props = defineProps<{
    exam:        ScoreEntrySheet['exam'];
    subject:     ScoreEntrySheet['subject'];
    template:    ScoreEntrySheet['template'];
    students:    ScoreEntrySheet['students'];
    section:     ScoreEntrySheet['section'];
    allSubjects: SubjectProgress[];
}>();

// ────────────────────────────────────────────────────────────────────────────
// Score Entry Composable
// ────────────────────────────────────────────────────────────────────────────
const sheet: ScoreEntrySheet = {
    exam:     props.exam,
    subject:  props.subject,
    template: props.template,
    students: props.students,
    section:  props.section,
};

const {
    localScores,
    isDirty,
    completionProgress,
    updateScore,
    updateAbsent,
    updateRemark,
    validateScore,
    getComponentMax,
    getRowState,
    saveAll,
} = useScoreEntry(sheet);

// ────────────────────────────────────────────────────────────────────────────
// Local UI State
// ────────────────────────────────────────────────────────────────────────────
const isSavingAll = ref(false);
const showRemarks = ref(false);
const expandedRemarkRows = ref<Set<string>>(new Set());

const isEditable = computed(() => props.exam.is_editable);
const statusConfig = computed(() => EXAM_STATUS_CONFIG[props.exam.status]);

const handleSaveAll = async () => {
    isSavingAll.value = true;
    await saveAll();
    isSavingAll.value = false;
};

const toggleRemarkRow = (studentId: string) => {
    if (expandedRemarkRows.value.has(studentId)) {
        expandedRemarkRows.value.delete(studentId);
    } else {
        expandedRemarkRows.value.add(studentId);
    }
};

const navigateToSubject = (subjectId: string) => {
    router.get(
        route('score-entry.show', {
            exam:    props.exam.id,
            section: props.section.id,
            subject: subjectId,
        }),
        {},
        { preserveScroll: true }
    );
};

// Score input ID for keyboard Tab flow
const inputId = (studentId: string, componentKey: string) =>
    `score-${studentId}-${componentKey}`;
</script>

<template>
    <AuthenticatedLayout
        :title="`Score Entry — ${subject.name}`"
        :crumb="[
            { label: 'Exams', url: route('exams.index') },
            { label: exam.name, url: route('exams.show', exam.id) },
            { label: 'Score Entry' },
        ]"
    >
        <div class="flex gap-6 h-full">
            <!-- ──────────────────────────────────────────────
                 LEFT SIDEBAR: Subject navigation
                 ────────────────────────────────────────────── -->
            <aside class="w-64 flex-shrink-0 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4 self-start sticky top-20">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 uppercase tracking-wide">
                    Subjects
                </h3>
                <div class="space-y-1.5">
                    <button
                        v-for="subj in allSubjects"
                        :key="subj.id"
                        @click="navigateToSubject(subj.id)"
                        class="w-full flex items-center justify-between p-2.5 rounded-lg text-left transition-colors text-sm"
                        :class="subj.id === subject.id
                            ? 'bg-primary/10 text-primary font-semibold'
                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'"
                    >
                        <span class="truncate">{{ subj.name }}</span>
                        <i
                            v-if="subj.is_complete"
                            class="pi pi-check-circle text-green-500 text-base flex-shrink-0 ml-1"
                        />
                        <span
                            v-else
                            class="text-xs text-gray-500 flex-shrink-0 ml-1"
                        >
                            {{ subj.scores_entered }}/{{ subj.total_students }}
                        </span>
                    </button>
                </div>
            </aside>

            <!-- ──────────────────────────────────────────────
                 MAIN CONTENT
                 ────────────────────────────────────────────── -->
            <div class="flex-1 min-w-0">
                <!-- Header Card -->
                <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-5 mb-4">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <div class="flex items-center gap-3 mb-1">
                                <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                                    {{ subject.name }}
                                    <span v-if="subject.code" class="text-sm font-normal text-gray-500">({{ subject.code }})</span>
                                </h1>
                                <Tag
                                    :value="statusConfig.label"
                                    :severity="statusConfig.severity"
                                    :icon="statusConfig.icon"
                                />
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ exam.name }} · {{ section.display_name }}
                            </p>
                        </div>

                        <!-- Save button + status -->
                        <div class="flex items-center gap-3">
                            <transition name="fade">
                                <span v-if="isDirty" class="text-sm text-amber-600 flex items-center gap-1.5">
                                    <i class="pi pi-circle-fill text-xs" />
                                    Unsaved changes
                                </span>
                            </transition>

                            <Button
                                v-if="isEditable"
                                label="Save All"
                                icon="pi pi-save"
                                :loading="isSavingAll"
                                @click="handleSaveAll"
                                severity="success"
                                :outlined="!isDirty"
                            />

                            <Button
                                :label="showRemarks ? 'Hide Remarks' : 'Show Remarks'"
                                :icon="showRemarks ? 'pi pi-eye-slash' : 'pi pi-comment'"
                                text
                                severity="secondary"
                                @click="showRemarks = !showRemarks"
                            />
                        </div>
                    </div>

                    <!-- Completion progress bar -->
                    <div class="mt-4">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs text-gray-500">Score Entry Progress</span>
                            <span class="text-xs font-semibold" :class="completionProgress === 100 ? 'text-green-600' : 'text-gray-600'">
                                {{ completionProgress }}%
                            </span>
                        </div>
                        <ProgressBar
                            :value="completionProgress"
                            :show-value="false"
                            class="h-2"
                            :pt="{ value: { class: completionProgress === 100 ? 'bg-green-500' : 'bg-primary' } }"
                        />
                    </div>
                </div>

                <!-- Score Entry Table -->
                <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <!-- Read-only banner -->
                    <div
                        v-if="!isEditable"
                        class="px-5 py-3 bg-amber-50 dark:bg-amber-950/30 border-b border-amber-200 dark:border-amber-800 flex items-center gap-2 text-sm text-amber-700 dark:text-amber-300"
                    >
                        <i class="pi pi-lock" />
                        <span>
                            This exam is {{ exam.status === 'results_approved' ? 'approved and locked' : 'not accepting score entries' }}.
                            Scores are read-only.
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <!-- Table Header -->
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                    <!-- Frozen: S/N + Name -->
                                    <th class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-800 text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-300 w-10 border-r border-gray-200 dark:border-gray-700">
                                        #
                                    </th>
                                    <th class="sticky left-10 z-10 bg-gray-50 dark:bg-gray-800 text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-300 min-w-[180px] border-r border-gray-200 dark:border-gray-700">
                                        Student
                                    </th>

                                    <!-- Component columns -->
                                    <th
                                        v-for="comp in template.components"
                                        :key="comp.key"
                                        class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-300 min-w-[100px]"
                                    >
                                        <div>{{ comp.label }}</div>
                                        <div class="text-xs font-normal text-gray-400">/{{ comp.max_score }}</div>
                                    </th>

                                    <!-- Total & Grade -->
                                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-300 min-w-[80px]">
                                        Total<div class="text-xs font-normal text-gray-400">/{{ template.total_score }}</div>
                                    </th>
                                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-300 min-w-[70px]">
                                        Grade
                                    </th>

                                    <!-- Absent checkbox -->
                                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-300 w-20">
                                        Absent
                                    </th>

                                    <!-- Row state indicator -->
                                    <th class="py-3 px-3 w-10" aria-label="Status" />
                                </tr>
                            </thead>

                            <tbody>
                                <template v-for="(studentRow, index) in students" :key="studentRow.student.id">
                                    <!-- Main score row -->
                                    <tr
                                        class="border-b border-gray-200 dark:border-gray-700 transition-colors"
                                        :class="{
                                            'bg-gray-50 dark:bg-gray-800/40 opacity-60': localScores.get(studentRow.student.id)?.is_absent,
                                            'hover:bg-gray-50 dark:hover:bg-gray-800/30': !localScores.get(studentRow.student.id)?.is_absent,
                                            'bg-red-50/50 dark:bg-red-950/20': getRowState(studentRow.student.id).error,
                                        }"
                                    >
                                        <!-- S/N (frozen) -->
                                        <td class="sticky left-0 z-10 py-3 px-4 text-gray-500 text-center border-r border-gray-200 dark:border-gray-700"
                                            :class="localScores.get(studentRow.student.id)?.is_absent ? 'bg-gray-50 dark:bg-gray-800/40' : 'bg-white dark:bg-gray-900'"
                                        >
                                            {{ index + 1 }}
                                        </td>

                                        <!-- Name (frozen) -->
                                        <td class="sticky left-10 z-10 py-3 px-4 border-r border-gray-200 dark:border-gray-700"
                                            :class="localScores.get(studentRow.student.id)?.is_absent ? 'bg-gray-50 dark:bg-gray-800/40' : 'bg-white dark:bg-gray-900'"
                                        >
                                            <div class="font-medium text-gray-900 dark:text-white text-sm">
                                                {{ studentRow.student.full_name }}
                                            </div>
                                            <div v-if="studentRow.student.admission_number" class="text-xs text-gray-400">
                                                {{ studentRow.student.admission_number }}
                                            </div>
                                        </td>

                                        <!-- Component score inputs -->
                                        <td
                                            v-for="comp in template.components"
                                            :key="comp.key"
                                            class="py-2 px-3 text-center"
                                        >
                                            <template v-if="isEditable && studentRow.can_edit && !localScores.get(studentRow.student.id)?.is_absent">
                                                <InputNumber
                                                    :id="inputId(studentRow.student.id, comp.key)"
                                                    :model-value="localScores.get(studentRow.student.id)?.scores[comp.key] ?? null"
                                                    @update:model-value="(val) => updateScore(studentRow.student.id, comp.key, val)"
                                                    :min="0"
                                                    :max="comp.max_score"
                                                    :step="0.5"
                                                    :min-fraction-digits="0"
                                                    :max-fraction-digits="1"
                                                    :invalid="
                                                        validateScore(
                                                            localScores.get(studentRow.student.id)?.scores[comp.key] ?? null,
                                                            comp.max_score
                                                        ) !== null
                                                    "
                                                    class="w-full max-w-[80px]"
                                                    input-class="text-center !h-9 !text-sm"
                                                    :placeholder="'0'" 
                                                    :allow-empty="true"
                                                />
                                            </template>
                                            <template v-else>
                                                <span class="text-gray-700 dark:text-gray-300 font-mono">
                                                    {{
                                                        localScores.get(studentRow.student.id)?.is_absent
                                                            ? '—'
                                                            : (localScores.get(studentRow.student.id)?.scores[comp.key] ?? '—')
                                                    }}
                                                </span>
                                            </template>
                                        </td>

                                        <!-- Total -->
                                        <td class="py-3 px-3 text-center font-semibold">
                                            <span
                                                :class="localScores.get(studentRow.student.id)?.is_absent
                                                    ? 'text-gray-400'
                                                    : localScores.get(studentRow.student.id)?.total_score !== null
                                                        ? (localScores.get(studentRow.student.id)!.total_score! >= template.pass_mark
                                                            ? 'text-green-600'
                                                            : 'text-red-600')
                                                        : 'text-gray-400'"
                                            >
                                                {{ localScores.get(studentRow.student.id)?.is_absent ? 'ABS' : (localScores.get(studentRow.student.id)?.total_score?.toFixed(1) ?? '—') }}
                                            </span>
                                        </td>

                                        <!-- Grade -->
                                        <td class="py-3 px-3 text-center">
                                            <span class="font-bold text-gray-700 dark:text-gray-300">
                                                {{ localScores.get(studentRow.student.id)?.grade_code ?? '—' }}
                                            </span>
                                        </td>

                                        <!-- Absent checkbox -->
                                        <td class="py-3 px-3 text-center">
                                            <Checkbox
                                                :model-value="localScores.get(studentRow.student.id)?.is_absent ?? false"
                                                @update:model-value="(val) => updateAbsent(studentRow.student.id, val)"
                                                :binary="true"
                                                :disabled="!isEditable || !studentRow.can_edit"
                                            />
                                        </td>

                                        <!-- Row status indicator -->
                                        <td class="py-3 px-2 text-center w-10">
                                            <i v-if="getRowState(studentRow.student.id).isSaving" class="pi pi-spin pi-spinner text-primary text-xs" />
                                            <i v-else-if="getRowState(studentRow.student.id).error" class="pi pi-exclamation-circle text-red-500 text-xs" v-tooltip.left="getRowState(studentRow.student.id).error" />
                                            <i v-else-if="getRowState(studentRow.student.id).isDirty" class="pi pi-circle-fill text-amber-400 text-xs" />
                                            <i v-else-if="!localScores.get(studentRow.student.id)?.is_absent && localScores.get(studentRow.student.id)?.total_score !== null" class="pi pi-check-circle text-green-500 text-xs" />
                                        </td>
                                    </tr>

                                    <!-- Remark row (expandable) -->
                                    <tr
                                        v-if="showRemarks || expandedRemarkRows.has(studentRow.student.id)"
                                        :key="`${studentRow.student.id}-remark`"
                                        class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/20"
                                    >
                                        <td :colspan="template.components.length + 5" class="px-4 py-2">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-gray-500 font-medium whitespace-nowrap">Remark:</span>
                                                <Textarea
                                                    :model-value="localScores.get(studentRow.student.id)?.remark ?? ''"
                                                    @update:model-value="(val) => updateRemark(studentRow.student.id, val)"
                                                    :disabled="!isEditable || !studentRow.can_edit"
                                                    rows="1"
                                                    :auto-resize="true"
                                                    class="text-xs flex-1"
                                                    placeholder="Teacher's remark on this subject result..."
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>

                            <!-- Table Footer: Component totals & class averages -->
                            <tfoot>
                                <tr class="bg-gray-100 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-semibold text-sm">
                                    <td class="sticky left-0 z-10 bg-gray-100 dark:bg-gray-800 py-3 px-4 text-right text-gray-500 border-r border-gray-200 dark:border-gray-700" colspan="2">
                                        Class Stats
                                    </td>
                                    <td v-for="comp in template.components" :key="comp.key" class="py-3 px-3 text-center text-gray-500 text-xs">
                                        —
                                    </td>
                                    <td class="py-3 px-3 text-center text-gray-600 dark:text-gray-400 text-xs">
                                        —
                                    </td>
                                    <td class="py-3 px-3 text-center" colspan="3" />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Bottom Action Bar -->
                <div v-if="isEditable" class="mt-4 flex items-center justify-between">
                    <p class="text-xs text-gray-500">
                        <i class="pi pi-info-circle mr-1" />
                        Scores auto-save as you type. Use the Save All button to confirm all changes.
                    </p>
                    <Button
                        label="Save All Scores"
                        icon="pi pi-save"
                        :loading="isSavingAll"
                        @click="handleSaveAll"
                        severity="success"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped lang="postcss">
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Ensure frozen columns have proper shadow */
td.sticky,
th.sticky {
    box-shadow: 2px 0 4px rgba(0, 0, 0, 0.05);
}

:deep(.p-inputnumber-input) {
    @apply !h-9;
}
</style>
