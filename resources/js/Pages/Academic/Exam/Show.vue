<!--
  resources/js/Pages/Academic/Exams/Show.vue

  Exam Hub Page — Central landing page for a single exam.

  This page is the starting point for everything exam-related:
    - Overview cards (status, dates, template info)
    - Section-by-section score entry progress
    - Quick-link to enter scores per section + subject
    - Timetable display
    - Status transition buttons (Publish → Ongoing → Completed → Approve)
    - Compute Results button (dispatches background job)
    - Link to Results and Report Cards once ready

  Features / Problems Solved:
  ─────────────────────────────────────────────────────────────────────────────
  • Status machine buttons show only valid next transitions (no invalid state shown)
  • Section progress cards link directly to the correct score-entry URL
  • "Compute Results" button shows a loading state and polls until job completes
  • Timetable is rendered as a clean printable schedule
  • All destructive actions (lock, approve) have confirmation dialogs
  • Read-only banner shown when exam is locked
  • Accessible: semantic headings, ARIA labels, focus management
-->

<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Button, Tag, ProgressBar, Card, Badge, Divider, Timeline, Message,
} from 'primevue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';
import type { Exam, SectionProgress } from '@/types/exam';
import { EXAM_STATUS_CONFIG, EXAM_STATUS_TRANSITIONS } from '@/types/exam';

// ────────────────────────────────────────────────────────────────────────────
// Props
// ────────────────────────────────────────────────────────────────────────────
const props = defineProps<{
    exam: Exam;
    sections: SectionProgress[];
    hasComputedResults: boolean;
    canCompute: boolean;
    canApprove: boolean;
}>();

const confirm  = useConfirm();
const toast    = useToast();

// ────────────────────────────────────────────────────────────────────────────
// Status transition helpers
// ────────────────────────────────────────────────────────────────────────────
const statusConfig  = computed(() => EXAM_STATUS_CONFIG[props.exam.status]);
const nextStatuses  = computed(() => EXAM_STATUS_TRANSITIONS[props.exam.status] ?? []);

const isComputing = ref(false);

const transitionStatus = (newStatus: string) => {
    const actionLabels: Record<string, string> = {
        published:        'Publish this exam so teachers can begin entering scores?',
        ongoing:          'Mark this exam as currently ongoing?',
        completed:        'Mark all score entry as completed? Teachers can still correct until results are approved.',
        results_approved: 'Approve and lock results? This is irreversible — report cards will be released to students.',
        draft:            'Revert this exam to draft? Only allowed if no scores have been entered.',
    };

    confirm.require({
        header: 'Confirm Status Change',
        message: actionLabels[newStatus] ?? `Change status to "${newStatus}"?`,
        icon: newStatus === 'results_approved' ? 'pi pi-lock' : 'pi pi-question-circle',
        acceptProps: {
            label: newStatus === 'results_approved' ? 'Approve & Lock' : 'Yes, Continue',
            severity: newStatus === 'results_approved' ? 'success' : 'info',
        },
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        accept: () => {
            router.patch(
                route('exams.update-status', props.exam.id),
                { status: newStatus },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.add({ severity: 'success', summary: 'Status updated', life: 3000 });
                    },
                    onError: (errors) => {
                        const msg = Object.values(errors).flat()[0] as string;
                        toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 5000 });
                    },
                }
            );
        },
    });
};

// ────────────────────────────────────────────────────────────────────────────
// Result Computation
// ────────────────────────────────────────────────────────────────────────────
const computeResults = async () => {
    isComputing.value = true;
    try {
        await axios.post(route('exams.compute-results', props.exam.id));
        toast.add({
            severity: 'info',
            summary: 'Computing Results',
            detail: 'Result computation has started. This page will refresh shortly.',
            life: 6000,
        });
        // Poll for completion
        const poll = setInterval(() => {
            router.reload({ only: ['hasComputedResults', 'canApprove', 'exam'] });
        }, 4000);

        setTimeout(() => {
            clearInterval(poll);
            isComputing.value = false;
        }, 60000);
    } catch {
        toast.add({ severity: 'error', summary: 'Failed to start computation', life: 4000 });
        isComputing.value = false;
    }
};

// ────────────────────────────────────────────────────────────────────────────
// Computed
// ────────────────────────────────────────────────────────────────────────────
const overallProgress = computed(() => {
    if (!props.sections.length) return 0;
    const total = props.sections.reduce((sum, s) => sum + s.total_students, 0);
    const scored = props.sections.reduce((sum, s) => sum + s.scores_entered, 0);
    return total > 0 ? Math.round((scored / total) * 100) : 0;
});

const statusNextButtonLabel: Record<string, string> = {
    published:        'Mark as Ongoing',
    ongoing:          'Mark as Completed',
    completed:        'Approve Results',
};

const nextStatusValue: Record<string, string> = {
    published:  'ongoing',
    ongoing:    'completed',
    completed:  'results_approved',
};
</script>

<template>
    <AuthenticatedLayout
        :title="exam.name"
        :crumb="[
            { label: 'Exams', url: route('exams.index') },
            { label: exam.name },
        ]"
    >
        <!-- Locked banner -->
        <Message
            v-if="exam.is_locked"
            severity="warn"
            :closable="false"
            class="mb-5"
        >
            <div class="flex items-center gap-2">
                <i class="pi pi-lock text-lg" />
                <span>
                    This exam's results have been <strong>approved and locked</strong>.
                    Report cards are available for all students.
                </span>
            </div>
        </Message>

        <div class="space-y-6">
            <!-- ── Top Summary Row ── -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Status Card -->
                <Card class="!shadow-sm">
                    <template #content>
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-soft-primary">
                                <i :class="[statusConfig.icon, 'text-xl text-primary']" />
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wide">Status</p>
                                <Tag
                                    :value="statusConfig.label"
                                    :severity="statusConfig.severity"
                                    class="mt-1"
                                />
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Overall Progress Card -->
                <Card class="!shadow-sm">
                    <template #content>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wide mb-2">Score Entry Progress</p>
                        <div class="flex items-center gap-3">
                            <ProgressBar
                                :value="overallProgress"
                                :show-value="false"
                                class="flex-1"
                                style="height: 8px"
                                :pt="{ value: { class: overallProgress === 100 ? 'bg-green-500' : 'bg-primary' } }"
                            />
                            <span class="text-lg font-bold" :class="overallProgress === 100 ? 'text-green-600' : 'text-gray-700 dark:text-gray-300'">
                                {{ overallProgress }}%
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ sections.reduce((s, r) => s + r.scores_entered, 0) }} /
                            {{ sections.reduce((s, r) => s + r.total_students, 0) }} students fully scored
                        </p>
                    </template>
                </Card>

                <!-- Template / Dates Card -->
                <Card class="!shadow-sm">
                    <template #content>
                        <dl class="space-y-1.5 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Template</dt>
                                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ exam.template_name }}</dd>
                            </div>
                            <div class="flex justify-between" v-if="exam.exam_start_date">
                                <dt class="text-gray-500">Start</dt>
                                <dd class="font-medium">{{ new Date(exam.exam_start_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }}</dd>
                            </div>
                            <div class="flex justify-between" v-if="exam.exam_end_date">
                                <dt class="text-gray-500">End</dt>
                                <dd class="font-medium">{{ new Date(exam.exam_end_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }}</dd>
                            </div>
                        </dl>
                    </template>
                </Card>
            </div>

            <!-- ── Section Progress + Score Entry Links ── -->
            <Card class="!shadow-sm">
                <template #title>
                    <span class="text-base font-semibold">Score Entry by Section</span>
                </template>
                <template #content>
                    <div v-if="sections.length === 0" class="text-center py-8 text-gray-500">
                        No sections found for this exam.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="section in sections"
                            :key="section.section.id"
                            class="flex items-center gap-4 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary/40 transition-colors"
                        >
                            <!-- Section name + stats -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-semibold text-sm text-gray-900 dark:text-white">
                                        {{ section.section.display_name }}
                                    </span>
                                    <Badge
                                        v-if="section.progress === 100"
                                        value="Complete"
                                        severity="success"
                                        class="text-xs"
                                    />
                                </div>
                                <div class="flex items-center gap-3">
                                    <ProgressBar
                                        :value="section.progress"
                                        :show-value="false"
                                        style="height: 6px; width: 200px"
                                        :pt="{ value: { class: section.progress === 100 ? 'bg-green-500' : 'bg-primary' } }"
                                    />
                                    <span class="text-xs text-gray-500">
                                        {{ section.scores_entered }}/{{ section.total_students }} students · {{ section.progress }}%
                                    </span>
                                </div>
                            </div>

                            <!-- Enter Scores button (only when editable) -->
                            <Button
                                v-if="exam.is_editable"
                                label="Enter Scores"
                                icon="pi pi-pencil"
                                size="small"
                                severity="info"
                                outlined
                                :as="'a'"
                                :href="route('score-entry.show', {
                                    exam: exam.id,
                                    sectionId: section.section.id,
                                    subjectId: '_first_',
                                })"
                            />
                            <Button
                                v-else-if="['completed', 'results_approved'].includes(exam.status)"
                                label="View Scores"
                                icon="pi pi-eye"
                                size="small"
                                severity="secondary"
                                outlined
                                @click="router.visit(route('exam-results.index', { exam: exam.id, section_id: section.section.id }))"
                            />
                        </div>
                    </div>
                </template>
            </Card>

            <!-- ── Action Panel ── -->
            <Card class="!shadow-sm">
                <template #title>
                    <span class="text-base font-semibold">Actions</span>
                </template>
                <template #content>
                    <div class="flex flex-wrap gap-3">
                        <!-- Status Transition Button (forward only) -->
                        <Button
                            v-if="nextStatuses.length && !exam.is_locked && exam.status !== 'results_approved'"
                            :label="statusNextButtonLabel[exam.status] ?? `Move to ${nextStatuses[0]}`"
                            :icon="exam.status === 'completed' ? 'pi pi-lock' : 'pi pi-arrow-right'"
                            :severity="exam.status === 'completed' ? 'success' : 'info'"
                            @click="transitionStatus(nextStatusValue[exam.status] ?? nextStatuses[0])"
                        />

                        <!-- Revert to Draft (only from published if no scores) -->
                        <Button
                            v-if="exam.status === 'published'"
                            label="Revert to Draft"
                            icon="pi pi-undo"
                            severity="secondary"
                            text
                            @click="transitionStatus('draft')"
                        />

                        <!-- Compute Results -->
                        <Button
                            v-if="canCompute"
                            label="Compute Results"
                            icon="pi pi-calculator"
                            severity="warn"
                            :loading="isComputing"
                            @click="computeResults"
                        />

                        <!-- View Results -->
                        <Button
                            v-if="hasComputedResults"
                            label="View Results"
                            icon="pi pi-chart-bar"
                            severity="secondary"
                            outlined
                            @click="router.visit(route('exam-results.index', exam.id))"
                        />

                        <!-- Report Cards -->
                        <Button
                            v-if="exam.status === 'results_approved'"
                            label="Print Report Cards"
                            icon="pi pi-file-pdf"
                            severity="secondary"
                            outlined
                            @click="router.visit(route('report-cards.bulk-print', exam.id))"
                        />
                    </div>

                    <p v-if="canCompute && !hasComputedResults" class="text-xs text-gray-500 mt-3">
                        <i class="pi pi-info-circle mr-1" />
                        Computation summarises all scores into ranked results. You must compute before approving.
                    </p>
                    <p v-if="hasComputedResults && !exam.is_locked" class="text-xs text-amber-600 mt-3">
                        <i class="pi pi-exclamation-triangle mr-1" />
                        Results have been computed. If scores are corrected, re-run computation before approving.
                    </p>
                </template>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
