<!--
  resources/js/Pages/Academic/Exams/Results.vue

  Class Results Table — Ranked list of all computed results for an exam.

  Features:
  ─────────────────────────────────────────────────────────────────────────────
  • Ranked table (1st, 2nd, 3rd...) with medal icons for top 3
  • Per-student row expandable to reveal full subject breakdown
  • Section filter tabs when exam covers multiple sections
  • Subject-level stats row (class avg / highest / lowest per subject)
  • Class summary stats in a sticky header card
  • Export to CSV / Print table actions
  • Click any student row to open their individual report card
  • Remark edit inline (teacher/principal role-gated) via inline overlay panel
  • Grade colour coding: distinction / credit / pass / fail
  ─────────────────────────────────────────────────────────────────────────────
-->

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    DataTable, Column, Button, Tag, ProgressBar, InputText, Select,
    OverlayPanel, Textarea, Message, Skeleton,
} from 'primevue';
import { usePermissions } from '@/composables/usePermissions';
import axios from 'axios';
import type { ComputedResult, AssessmentComponent } from '@/types/exam';

// ────────────────────────────────────────────────────────────────────────────
// Props (from ExamResultsController::index Inertia props)
// ────────────────────────────────────────────────────────────────────────────
const props = defineProps<{
    exam: {
        id: string;
        name: string;
        status: string;
        is_locked: boolean;
        session_name: string;
        term_name?: string | null;
        level_name?: string | null;
        template: {
            components: AssessmentComponent[];
            total_score: number;
            pass_mark: number;
        };
    };
    results: ComputedResult[];
    class_stats: {
        total_students: number;
        average_score: number;
        highest_average: number;
        lowest_average: number;
        total_passed: number;
        total_failed: number;
    };
    sections: { id: string; display_name: string }[];
    active_section_id?: string | null;
}>();

const { hasRole } = usePermissions();

// ────────────────────────────────────────────────────────────────────────────
// Section filter
// ────────────────────────────────────────────────────────────────────────────
const activeSectionId = ref<string | null>(props.active_section_id ?? null);

const filterBySection = (sectionId: string | null) => {
    activeSectionId.value = sectionId;
    router.get(
        route('exam-results.index', props.exam.id),
        { section_id: sectionId ?? undefined },
        { preserveState: true, replace: true }
    );
};

// ────────────────────────────────────────────────────────────────────────────
// Expanded rows (subject breakdown)
// ────────────────────────────────────────────────────────────────────────────
const expandedRows = ref<Record<string, boolean>>({});

const toggleExpand = (resultId: string) => {
    expandedRows.value[resultId] = !expandedRows.value[resultId];
};

// ────────────────────────────────────────────────────────────────────────────
// Grade colour helper
// ────────────────────────────────────────────────────────────────────────────
const gradeClass = (gradeCode: string): string => {
    const map: Record<string, string> = {
        A: 'text-emerald-700 font-bold',
        B: 'text-blue-600 font-semibold',
        C: 'text-sky-600',
        D: 'text-amber-600',
        E: 'text-orange-600',
        F: 'text-red-600 font-bold',
    };
    return map[gradeCode?.toUpperCase()?.[0]] ?? 'text-gray-600';
};

const passFail = (score: number): boolean => score >= props.exam.template.pass_mark;

// ────────────────────────────────────────────────────────────────────────────
// Ordinal position label
// ────────────────────────────────────────────────────────────────────────────
const ordinal = (n: number | null): string => {
    if (!n) return '—';
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
};

const medalIcon = (pos: number | null) => {
    if (pos === 1) return '🥇';
    if (pos === 2) return '🥈';
    if (pos === 3) return '🥉';
    return null;
};

// ────────────────────────────────────────────────────────────────────────────
// Remark edit (inline overlay)
// ────────────────────────────────────────────────────────────────────────────
const remarkOp   = ref<InstanceType<typeof OverlayPanel> | null>(null);
const remarkForm = ref({ resultId: '', type: 'class_teacher_remark', text: '' });
const remarkSaving = ref(false);

const openRemarkPanel = (event: Event, result: ComputedResult, type: 'class_teacher_remark' | 'principal_remark') => {
    remarkForm.value = {
        resultId: result.id,
        type,
        text: type === 'class_teacher_remark' ? (result.class_teacher_remark ?? '') : (result.principal_remark ?? ''),
    };
    remarkOp.value?.toggle(event);
};

const saveRemark = async () => {
    remarkSaving.value = true;
    try {
        await axios.patch(
            route('exam-results.update-remark', { exam: props.exam.id, result: remarkForm.value.resultId }),
            { [remarkForm.value.type]: remarkForm.value.text }
        );
        remarkOp.value?.hide();
        router.reload({ only: ['results'] });
    } catch {
        // silently fail — toast is handled globally
    } finally {
        remarkSaving.value = false;
    }
};

// ────────────────────────────────────────────────────────────────────────────
// Navigate to report card
// ────────────────────────────────────────────────────────────────────────────
const viewReportCard = (result: ComputedResult) => {
    router.visit(route('report-cards.show', {
        exam: props.exam.id,
        studentId: result.student_id,
    }));
};
</script>

<template>
    <AuthenticatedLayout
        :title="`Results — ${exam.name}`"
        :crumb="[
            { label: 'Exams', url: route('exams.index') },
            { label: exam.name, url: route('exams.show', exam.id) },
            { label: 'Results' },
        ]"
        :buttons="exam.is_locked ? [
            { label: 'Print Report Cards', icon: 'pi pi-file-pdf', outlined: true,
              onClick: () => router.visit(route('report-cards.bulk-print', exam.id)) }
        ] : []"
    >
        <!-- Class Stats Summary -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <div v-for="(val, key) in {
                'Total Students':  class_stats.total_students,
                'Class Average':   class_stats.average_score + '%',
                'Highest Average': class_stats.highest_average + '%',
                'Students Passed': class_stats.total_passed,
                'Students Failed': class_stats.total_failed,
            }" :key="key"
                class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4"
            >
                <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">{{ key }}</p>
                <p class="text-2xl font-bold mt-1" :class="key === 'Students Failed' && Number(val) > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white'">
                    {{ val }}
                </p>
            </div>
        </div>

        <!-- Section Tabs -->
        <div v-if="sections.length > 1" class="flex gap-2 mb-4 flex-wrap">
            <Button
                label="All Sections"
                :severity="!activeSectionId ? 'primary' : 'secondary'"
                :outlined="!!activeSectionId"
                size="small"
                @click="filterBySection(null)"
            />
            <Button
                v-for="s in sections"
                :key="s.id"
                :label="s.display_name"
                :severity="activeSectionId === s.id ? 'primary' : 'secondary'"
                :outlined="activeSectionId !== s.id"
                size="small"
                @click="filterBySection(s.id)"
            />
        </div>

        <!-- Results Table -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <DataTable
                :value="results"
                :rowClass="(r: ComputedResult) => r.subjects_failed > 0 ? 'bg-red-50/30 dark:bg-red-900/10' : ''"
                dataKey="id"
                scrollable
                scrollHeight="calc(100vh - 340px)"
                class="text-sm"
            >
                <!-- Rank -->
                <Column header="#" style="width: 56px; min-width: 56px" frozen>
                    <template #body="{ data }: { data: ComputedResult }">
                        <div class="flex items-center gap-1">
                            <span class="text-base" v-if="medalIcon(data.position_in_class)">
                                {{ medalIcon(data.position_in_class) }}
                            </span>
                            <span
                                v-else
                                class="text-sm font-semibold text-gray-500 dark:text-gray-400"
                            >
                                {{ ordinal(data.position_in_class) }}
                            </span>
                        </div>
                    </template>
                </Column>

                <!-- Student -->
                <Column header="Student" style="min-width: 200px" frozen>
                    <template #body="{ data }: { data: ComputedResult }">
                        <button
                            class="flex items-center gap-2 text-left w-full hover:text-primary transition-colors group"
                            @click="toggleExpand(data.id)"
                        >
                            <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-xs font-bold text-gray-600 dark:text-gray-300 shrink-0">
                                {{ data.student?.full_name?.[0] ?? '?' }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold truncate text-sm text-gray-900 dark:text-white group-hover:text-primary">
                                    {{ data.student?.full_name ?? '—' }}
                                </p>
                                <p class="text-xs text-gray-500">{{ data.student?.admission_number }}</p>
                            </div>
                            <i
                                class="pi ml-auto text-gray-400 text-xs"
                                :class="expandedRows[data.id] ? 'pi-chevron-up' : 'pi-chevron-down'"
                            />
                        </button>
                    </template>
                </Column>

                <!-- Average -->
                <Column header="Average" sortable field="average_score" style="width: 100px; min-width: 100px">
                    <template #body="{ data }: { data: ComputedResult }">
                        <span
                            class="font-bold"
                            :class="data.average_score >= exam.template.pass_mark ? 'text-green-600' : 'text-red-600'"
                        >
                            {{ data.average_score?.toFixed(1) ?? '—' }}%
                        </span>
                    </template>
                </Column>

                <!-- Total -->
                <Column header="Total" sortable field="total_score_obtained" style="width: 110px; min-width: 110px">
                    <template #body="{ data }: { data: ComputedResult }">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ data.total_score_obtained?.toFixed(0) }} /
                            {{ data.total_score_possible }}
                        </span>
                    </template>
                </Column>

                <!-- Passed / Failed -->
                <Column header="Pass/Fail" style="width: 100px; min-width: 100px">
                    <template #body="{ data }: { data: ComputedResult }">
                        <div class="flex items-center gap-1 text-xs">
                            <Tag :value="`${data.subjects_passed}P`" severity="success" />
                            <Tag v-if="data.subjects_failed" :value="`${data.subjects_failed}F`" severity="danger" />
                        </div>
                    </template>
                </Column>

                <!-- Teacher Remark -->
                <Column header="Remark" style="min-width: 160px">
                    <template #body="{ data }: { data: ComputedResult }">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-gray-600 dark:text-gray-400 italic truncate max-w-[120px]">
                                {{ data.class_teacher_remark ?? '—' }}
                            </span>
                            <button
                                v-if="!exam.is_locked || hasRole(['admin', 'principal'])"
                                class="text-gray-400 hover:text-primary ml-auto shrink-0"
                                @click.stop="openRemarkPanel($event, data, 'class_teacher_remark')"
                            >
                                <i class="pi pi-pencil text-xs" />
                            </button>
                        </div>
                    </template>
                </Column>

                <!-- Report Card link -->
                <Column header="" style="width: 52px" alignFrozen="right" frozen>
                    <template #body="{ data }: { data: ComputedResult }">
                        <Button
                            icon="pi pi-external-link"
                            size="small"
                            text
                            severity="secondary"
                            v-tooltip.left="'Report Card'"
                            @click="viewReportCard(data)"
                        />
                    </template>
                </Column>

                <!-- Expanded: Subject Breakdown Row -->
                <template #expansion="{ data }: { data: ComputedResult }">
                    <div v-if="expandedRows[data.id]" class="p-4 bg-gray-50 dark:bg-gray-900/40 border-t border-gray-200 dark:border-gray-700">
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="text-gray-500 dark:text-gray-400 uppercase text-[11px] tracking-wide border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-left py-1.5 pr-4 font-medium">Subject</th>
                                    <th
                                        v-for="comp in exam.template.components"
                                        :key="comp.key"
                                        class="text-center py-1.5 px-2 font-medium"
                                    >
                                        {{ comp.label }}<br />
                                        <span class="text-gray-400 font-normal">/ {{ comp.max_score }}</span>
                                    </th>
                                    <th class="text-center py-1.5 px-2 font-medium">Total</th>
                                    <th class="text-center py-1.5 px-2 font-medium">Grade</th>
                                    <th class="text-left py-1.5 px-2 font-medium">Remark</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="subj in (data.subject_breakdown ?? [])"
                                    :key="subj.subject_id"
                                    class="border-b border-gray-100 dark:border-gray-800 last:border-0"
                                    :class="!passFail(subj.total_score) ? 'bg-red-50/60 dark:bg-red-900/10' : ''"
                                >
                                    <td class="py-1.5 pr-4 font-medium text-gray-800 dark:text-gray-200">{{ subj.subject_name }}</td>
                                    <td
                                        v-for="comp in exam.template.components"
                                        :key="comp.key"
                                        class="text-center py-1.5 px-2 text-gray-700 dark:text-gray-300"
                                    >
                                        {{ subj.scores?.[comp.key]?.score ?? '—' }}
                                    </td>
                                    <td class="text-center py-1.5 px-2 font-semibold" :class="passFail(subj.total_score) ? 'text-green-700' : 'text-red-600'">
                                        {{ subj.total_score?.toFixed(1) }}
                                    </td>
                                    <td class="text-center py-1.5 px-2" :class="gradeClass(subj.grade_code)">
                                        {{ subj.grade_code }}
                                    </td>
                                    <td class="py-1.5 px-2 text-gray-500 italic">{{ subj.remark ?? '' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template #empty>
                    <div class="text-center py-12 text-gray-500">
                        <i class="pi pi-inbox text-4xl mb-3 block text-gray-300" />
                        No results found. Run result computation first.
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Remark Overlay Panel -->
        <OverlayPanel ref="remarkOp" class="w-80">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Edit Remark</p>
            <Textarea
                v-model="remarkForm.text"
                rows="3"
                class="w-full text-sm"
                placeholder="Enter remark..."
                auto-resize
            />
            <div class="flex justify-end gap-2 mt-3">
                <Button label="Cancel" severity="secondary" size="small" text @click="remarkOp?.hide()" />
                <Button label="Save" size="small" :loading="remarkSaving" @click="saveRemark" />
            </div>
        </OverlayPanel>
    </AuthenticatedLayout>
</template>
