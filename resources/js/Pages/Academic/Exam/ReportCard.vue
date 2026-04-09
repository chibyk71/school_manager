<!--
  resources/js/Pages/Academic/Exams/ReportCard.vue

  Individual Student Report Card

  This page renders a print-ready report card for one student.

  Features:
  ─────────────────────────────────────────────────────────────────────────────
  • School branding: logo, name, address, motto
  • Student info: name, admission number, class, session/term
  • Subject table: all components + total + grade + remark per subject
  • Summary: average, position, class size, subjects passed/failed
  • Teacher remark and principal remark (editable in place if permitted)
  • Promotion status badge
  • Print button triggers window.print() — page uses @media print CSS
  • @media print: hides layout chrome (sidebar, header, buttons), keeps only the card

  Data comes from computed_results.subject_breakdown — the frozen snapshot.
  No live joins. The report card is identical whether viewed today or in 5 years.
  ─────────────────────────────────────────────────────────────────────────────
-->

<script setup lang="ts">
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button, Tag, Message, Textarea, OverlayPanel } from 'primevue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import type { AssessmentComponent } from '@/types/exam';

// ────────────────────────────────────────────────────────────────────────────
// Props (from ReportCardController::show)
// ────────────────────────────────────────────────────────────────────────────
const props = defineProps<{
    result: {
        id: string;
        total_score_obtained: number;
        total_score_possible: number;
        average_score: number;
        position_in_class: number | null;
        class_size: number | null;
        subjects_passed: number;
        subjects_failed: number;
        subject_breakdown: Array<{
            subject_id: string;
            subject_name: string;
            subject_code?: string;
            scores: Record<string, { score: number; max: number }>;
            total_score: number;
            grade_code: string;
            remark?: string;
            position_in_subject?: number | null;
        }>;
        class_teacher_remark?: string | null;
        principal_remark?: string | null;
        promotion_status?: string | null;
        is_final: boolean;
    };
    student: {
        id: string;
        full_name: string;
        admission_number: string;
        photo_url?: string | null;
        class_name?: string | null;
        level_name?: string | null;
    };
    exam: {
        id: string;
        name: string;
        session_name: string;
        term_name?: string | null;
        status: string;
    };
    school: {
        name?: string | null;
        logo?: string | null;
        address?: string | null;
        phone?: string | null;
        email?: string | null;
        motto?: string | null;
    };
    template: {
        components: AssessmentComponent[];
        total_score: number;
        pass_mark: number;
    };
    can_edit_remarks: boolean;
}>();

// ────────────────────────────────────────────────────────────────────────────
// Remark editing
// ────────────────────────────────────────────────────────────────────────────
const remarkOp   = ref<InstanceType<typeof OverlayPanel> | null>(null);
const remarkForm = ref({ type: '' as 'class_teacher_remark' | 'principal_remark', text: '' });
const remarkSaving = ref(false);

const openRemark = (event: Event, type: typeof remarkForm.value.type) => {
    remarkForm.value = {
        type,
        text: props.result[type] ?? '',
    };
    remarkOp.value?.toggle(event);
};

const saveRemark = async () => {
    remarkSaving.value = true;
    try {
        await axios.patch(
            route('exam-results.update-remark', { exam: props.exam.id, result: props.result.id }),
            { [remarkForm.value.type]: remarkForm.value.text }
        );
        remarkOp.value?.hide();
        router.reload({ only: ['result'] });
    } finally {
        remarkSaving.value = false;
    }
};

// ────────────────────────────────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────────────────────────────────
const ordinal = (n: number | null): string => {
    if (!n) return '—';
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
};

const gradeClass = (code: string) => {
    const map: Record<string, string> = {
        A: 'text-emerald-700 font-bold',
        B: 'text-blue-600 font-semibold',
        C: 'text-sky-500',
        D: 'text-amber-600',
        F: 'text-red-600 font-bold',
    };
    return map[code?.toUpperCase()?.[0]] ?? 'text-gray-600';
};

const promotionLabel: Record<string, { label: string; sev: string }> = {
    promoted: { label: 'Promoted', sev: 'success' },
    held_back: { label: 'Held Back', sev: 'danger' },
    pending:   { label: 'Pending', sev: 'warn' },
};
</script>

<template>
    <AuthenticatedLayout
        :title="`Report Card — ${student.full_name}`"
        :crumb="[
            { label: 'Exams', url: route('exams.index') },
            { label: exam.name, url: route('exams.show', exam.id) },
            { label: 'Results', url: route('exam-results.index', exam.id) },
            { label: student.full_name },
        ]"
        :buttons="[
            { label: 'Print', icon: 'pi pi-print', onClick: () => window.print(), severity: 'secondary', outlined: true },
            { label: 'All Results', icon: 'pi pi-arrow-left', onClick: () => router.visit(route('exam-results.index', exam.id)), severity: 'secondary', text: true },
        ]"
    >
        <!-- Print area: all content inside this div is what gets printed -->
        <div id="report-card-print-area" class="max-w-4xl mx-auto">

            <!-- ── Report Card Sheet ── -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden print:shadow-none print:rounded-none print:border-0">

                <!-- School Header -->
                <div class="bg-gradient-to-r from-primary/10 to-primary/5 dark:from-primary/20 dark:to-primary/10 px-8 py-6 border-b border-gray-200 dark:border-gray-700 print:bg-white">
                    <div class="flex items-center gap-5">
                        <img
                            v-if="school.logo"
                            :src="school.logo"
                            alt="School Logo"
                            class="w-20 h-20 object-contain rounded-lg"
                        />
                        <div class="w-20 h-20 rounded-lg bg-primary/20 flex items-center justify-center" v-else>
                            <i class="pi pi-building text-4xl text-primary" />
                        </div>
                        <div class="flex-1 text-center">
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white uppercase tracking-wide">
                                {{ school.name ?? 'School Name' }}
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-0.5">{{ school.address }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span v-if="school.phone">📞 {{ school.phone }}</span>
                                <span v-if="school.email" class="ml-3">✉️ {{ school.email }}</span>
                            </p>
                            <p v-if="school.motto" class="text-xs italic text-primary mt-1 font-medium">
                                "{{ school.motto }}"
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 text-center">
                        <h2 class="text-lg font-bold text-primary uppercase tracking-widest">
                            {{ exam.name }}
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ exam.session_name }}
                            <span v-if="exam.term_name"> · {{ exam.term_name }}</span>
                        </p>
                    </div>
                </div>

                <!-- Student Info Bar -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-0 border-b border-gray-200 dark:border-gray-700 divide-x divide-gray-200 dark:divide-gray-700">
                    <div v-for="(val, label) in {
                        'Student Name': student.full_name,
                        'Admission No.': student.admission_number,
                        'Class': student.class_name ?? student.level_name ?? '—',
                        'Term': exam.term_name ?? exam.session_name,
                    }" :key="label" class="px-5 py-3">
                        <p class="text-xs text-gray-500 uppercase font-medium tracking-wide">{{ label }}</p>
                        <p class="font-semibold text-gray-900 dark:text-white text-sm mt-0.5">{{ val }}</p>
                    </div>
                </div>

                <!-- Subject Scores Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="text-left px-5 py-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wide border-b border-gray-200 dark:border-gray-700 w-40">
                                    Subject
                                </th>
                                <th
                                    v-for="comp in template.components"
                                    :key="comp.key"
                                    class="text-center px-3 py-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wide border-b border-gray-200 dark:border-gray-700"
                                >
                                    {{ comp.label }}<br />
                                    <span class="text-gray-400 font-normal">/{{ comp.max_score }}</span>
                                </th>
                                <th class="text-center px-3 py-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wide border-b border-gray-200 dark:border-gray-700">
                                    Total<br /><span class="text-gray-400 font-normal">/{{ template.total_score }}</span>
                                </th>
                                <th class="text-center px-3 py-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wide border-b border-gray-200 dark:border-gray-700">
                                    Grade
                                </th>
                                <th class="text-left px-3 py-3 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wide border-b border-gray-200 dark:border-gray-700">
                                    Remark
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(subj, i) in result.subject_breakdown"
                                :key="subj.subject_id"
                                class="border-b border-gray-100 dark:border-gray-800"
                                :class="[
                                    i % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50/50 dark:bg-gray-900/20',
                                    subj.total_score < template.pass_mark ? 'print:bg-red-50' : '',
                                ]"
                            >
                                <td class="px-5 py-2.5 font-medium text-gray-800 dark:text-gray-200">
                                    {{ subj.subject_name }}
                                    <span v-if="subj.subject_code" class="text-xs text-gray-400 ml-1">({{ subj.subject_code }})</span>
                                </td>
                                <td
                                    v-for="comp in template.components"
                                    :key="comp.key"
                                    class="text-center px-3 py-2.5 text-gray-700 dark:text-gray-300"
                                >
                                    {{ subj.scores?.[comp.key]?.score ?? '—' }}
                                </td>
                                <td
                                    class="text-center px-3 py-2.5 font-bold"
                                    :class="subj.total_score >= template.pass_mark ? 'text-green-700 dark:text-green-400' : 'text-red-600'"
                                >
                                    {{ subj.total_score?.toFixed(1) }}
                                </td>
                                <td class="text-center px-3 py-2.5 font-bold" :class="gradeClass(subj.grade_code)">
                                    {{ subj.grade_code }}
                                </td>
                                <td class="px-3 py-2.5 text-gray-500 text-xs italic">{{ subj.remark ?? '' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Summary Footer -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-0 divide-x divide-gray-200 dark:divide-gray-700 border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20">
                    <div v-for="(val, label) in {
                        'Total Score': `${result.total_score_obtained.toFixed(0)} / ${result.total_score_possible}`,
                        'Average': `${result.average_score.toFixed(1)}%`,
                        'Position': `${ordinal(result.position_in_class)} of ${result.class_size ?? '?'}`,
                        'Passed': result.subjects_passed + ' subjects',
                        'Failed': result.subjects_failed + ' subjects',
                    }" :key="label" class="px-5 py-3 text-center">
                        <p class="text-xs text-gray-500 uppercase font-medium tracking-wide">{{ label }}</p>
                        <p
                            class="font-bold text-sm mt-0.5"
                            :class="label === 'Failed' && result.subjects_failed > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white'"
                        >
                            {{ val }}
                        </p>
                    </div>
                </div>

                <!-- Remarks Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-0 divide-x divide-gray-200 dark:divide-gray-700 border-t border-gray-200 dark:border-gray-700">
                    <!-- Class Teacher Remark -->
                    <div class="px-6 py-5">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs uppercase font-semibold text-gray-500 tracking-wide">Class Teacher's Remark</p>
                            <button
                                v-if="can_edit_remarks"
                                class="text-gray-400 hover:text-primary transition-colors print:hidden"
                                @click="openRemark($event, 'class_teacher_remark')"
                            >
                                <i class="pi pi-pencil text-xs" />
                            </button>
                        </div>
                        <p class="text-sm italic text-gray-700 dark:text-gray-300 min-h-[40px] border-b border-dashed border-gray-300 dark:border-gray-600 pb-2">
                            {{ result.class_teacher_remark || '&nbsp;' }}
                        </p>
                        <p class="text-xs text-gray-400 mt-3">Signature: ________________________</p>
                    </div>

                    <!-- Principal Remark -->
                    <div class="px-6 py-5">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs uppercase font-semibold text-gray-500 tracking-wide">Principal's Remark</p>
                            <button
                                v-if="can_edit_remarks"
                                class="text-gray-400 hover:text-primary transition-colors print:hidden"
                                @click="openRemark($event, 'principal_remark')"
                            >
                                <i class="pi pi-pencil text-xs" />
                            </button>
                        </div>
                        <p class="text-sm italic text-gray-700 dark:text-gray-300 min-h-[40px] border-b border-dashed border-gray-300 dark:border-gray-600 pb-2">
                            {{ result.principal_remark || '&nbsp;' }}
                        </p>
                        <p class="text-xs text-gray-400 mt-3">Signature: ________________________</p>
                    </div>
                </div>

                <!-- Promotion Status -->
                <div v-if="result.promotion_status" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between print:hidden">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Promotion Status</span>
                    <Tag
                        :value="promotionLabel[result.promotion_status]?.label ?? result.promotion_status"
                        :severity="promotionLabel[result.promotion_status]?.sev ?? 'info'"
                    />
                </div>

                <!-- Approval watermark for final results -->
                <div v-if="result.is_final" class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-green-50 dark:bg-green-900/20 text-center print:block">
                    <p class="text-xs text-green-700 dark:text-green-400 font-medium">
                        <i class="pi pi-check-circle mr-1" /> Results approved and certified
                    </p>
                </div>
            </div>
        </div>

        <!-- Remark Overlay -->
        <OverlayPanel ref="remarkOp" class="w-80 print:hidden">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                {{ remarkForm.type === 'class_teacher_remark' ? "Class Teacher's Remark" : "Principal's Remark" }}
            </p>
            <Textarea v-model="remarkForm.text" rows="3" class="w-full text-sm" auto-resize />
            <div class="flex justify-end gap-2 mt-3">
                <Button label="Cancel" severity="secondary" size="small" text @click="remarkOp?.hide()" />
                <Button label="Save" size="small" :loading="remarkSaving" @click="saveRemark" />
            </div>
        </OverlayPanel>
    </AuthenticatedLayout>
</template>

<style>
@media print {
    /* Hide everything outside the report card */
    body > *:not(#app) { display: none; }
    [data-sidebar], [data-topbar], [data-breadcrumb], .print\:hidden { display: none !important; }
    #report-card-print-area { max-width: 100%; margin: 0; }
    .bg-white { background: white !important; }
    @page { margin: 10mm 15mm; size: A4; }
}
</style>
