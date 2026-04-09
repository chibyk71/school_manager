<!--
  resources/js/Pages/Academic/Exams/ReportCardBulk.vue

  Bulk Report Card Print Page

  Renders all students' report cards stacked vertically, each on its own
  printed page via CSS `page-break-after: always`.

  Features:
  ─────────────────────────────────────────────────────────────────────────────
  • Print-ready: hits window.print() — every card is a separate page
  • Section selector: if exam has multiple sections, filter before printing
  • Student count + section shown in the print header
  • Each card is a self-contained block — school branding repeated per card
  • On-screen preview before printing: shows all cards scrollable
  • "Print All" button in header bar
  • Compact layout for screen preview (reduced padding vs individual card)
  ─────────────────────────────────────────────────────────────────────────────
-->

<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button, Tag, Select } from 'primevue';
import type { AssessmentComponent } from '@/types/exam';

// ────────────────────────────────────────────────────────────────────────────
// Props (from ReportCardController::bulkPrint)
// ────────────────────────────────────────────────────────────────────────────
const props = defineProps<{
    results: Array<{
        id: string;
        student: {
            id: string;
            full_name: string;
            admission_number: string;
            photo_url?: string | null;
            class_name?: string | null;
        };
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
            scores: Record<string, { score: number }>;
            total_score: number;
            grade_code: string;
            remark?: string;
        }>;
        class_teacher_remark?: string | null;
        principal_remark?: string | null;
        promotion_status?: string | null;
    }>;
    exam: { name: string; session_name: string; term_name?: string | null };
    school: { name?: string | null; logo?: string | null; phone?: string | null; email?: string | null; motto?: string | null };
    template: { components: AssessmentComponent[]; total_score: number; pass_mark: number };
    sections?: { id: string; display_name: string }[];
    active_section_id?: string | null;
}>();

// ────────────────────────────────────────────────────────────────────────────
// Section filter (for multi-section exams)
// ────────────────────────────────────────────────────────────────────────────
const activeSectionId = ref<string | null>(props.active_section_id ?? null);

const filterSection = (sectionId: string | null) => {
    activeSectionId.value = sectionId;
    router.get(
        route('report-cards.bulk-print', { exam: router.page.props.exam?.id }),
        sectionId ? { section_id: sectionId } : {},
        { preserveState: true, replace: true }
    );
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
</script>

<template>
    <AuthenticatedLayout
        :title="`Bulk Print — ${exam.name}`"
        :crumb="[
            { label: 'Exams', url: route('exams.index') },
            { label: exam.name },
            { label: 'Report Cards' },
        ]"
        :buttons="[
            { label: `Print All (${results.length})`, icon: 'pi pi-print', onClick: () => window.print() },
        ]"
    >
        <!-- Section Filter (screen only) -->
        <div v-if="(sections?.length ?? 0) > 1" class="flex gap-2 mb-5 print:hidden flex-wrap">
            <Button
                label="All Sections"
                :severity="!activeSectionId ? 'primary' : 'secondary'"
                :outlined="!!activeSectionId"
                size="small"
                @click="filterSection(null)"
            />
            <Button
                v-for="s in sections"
                :key="s.id"
                :label="s.display_name"
                :severity="activeSectionId === s.id ? 'primary' : 'secondary'"
                :outlined="activeSectionId !== s.id"
                size="small"
                @click="filterSection(s.id)"
            />
        </div>

        <!-- Preview Banner -->
        <div class="flex items-center justify-between mb-4 print:hidden">
            <p class="text-sm text-gray-500">
                <i class="pi pi-eye mr-1" />
                Previewing <strong>{{ results.length }}</strong> report cards.
                Use <strong>Print All</strong> to send to printer.
            </p>
        </div>

        <!-- ── All Report Cards ── -->
        <div id="bulk-print-area">
            <div
                v-for="(result, idx) in results"
                :key="result.id"
                class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-8 print:mb-0 print:rounded-none print:border-0 print:shadow-none"
                :style="{ pageBreakAfter: idx < results.length - 1 ? 'always' : 'auto' }"
            >
                <!-- School Header (compact for bulk) -->
                <div class="flex items-center gap-4 px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-primary/8 to-transparent print:bg-white">
                    <img v-if="school.logo" :src="school.logo" class="w-14 h-14 object-contain rounded" alt="Logo" />
                    <div class="flex-1 text-center">
                        <h2 class="text-lg font-bold uppercase tracking-wide text-gray-900">{{ school.name }}</h2>
                        <p class="text-xs font-bold text-primary uppercase tracking-widest mt-0.5">{{ exam.name }}</p>
                        <p class="text-xs text-gray-500">
                            {{ exam.session_name }}<span v-if="exam.term_name"> · {{ exam.term_name }}</span>
                        </p>
                    </div>
                </div>

                <!-- Student Info -->
                <div class="grid grid-cols-4 divide-x divide-gray-200 border-b border-gray-200 text-sm">
                    <div v-for="(val, lbl) in {
                        'Name': result.student.full_name,
                        'Adm. No.': result.student.admission_number,
                        'Class': result.student.class_name ?? '—',
                        'Term': exam.term_name ?? exam.session_name,
                    }" :key="lbl" class="px-4 py-2.5">
                        <p class="text-xs text-gray-400 uppercase font-medium">{{ lbl }}</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ val }}</p>
                    </div>
                </div>

                <!-- Subject Table -->
                <table class="w-full text-xs border-collapse">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-4 py-2 font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200 w-36">Subject</th>
                            <th
                                v-for="comp in template.components"
                                :key="comp.key"
                                class="text-center px-2 py-2 font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200"
                            >
                                {{ comp.label }}<br /><span class="font-normal text-gray-400">/{{ comp.max_score }}</span>
                            </th>
                            <th class="text-center px-2 py-2 font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200">
                                Total<br /><span class="font-normal text-gray-400">/{{ template.total_score }}</span>
                            </th>
                            <th class="text-center px-2 py-2 font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200">Grade</th>
                            <th class="text-left px-2 py-2 font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200">Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(subj, i) in result.subject_breakdown"
                            :key="subj.subject_id"
                            class="border-b border-gray-100 last:border-0"
                            :class="i % 2 === 1 ? 'bg-gray-50/50' : 'bg-white'"
                        >
                            <td class="px-4 py-1.5 font-medium text-gray-800">{{ subj.subject_name }}</td>
                            <td v-for="comp in template.components" :key="comp.key" class="text-center px-2 py-1.5 text-gray-700">
                                {{ subj.scores?.[comp.key]?.score ?? '—' }}
                            </td>
                            <td
                                class="text-center px-2 py-1.5 font-bold"
                                :class="subj.total_score >= template.pass_mark ? 'text-green-700' : 'text-red-600'"
                            >
                                {{ subj.total_score?.toFixed(1) }}
                            </td>
                            <td class="text-center px-2 py-1.5 font-bold" :class="gradeClass(subj.grade_code)">{{ subj.grade_code }}</td>
                            <td class="px-2 py-1.5 text-gray-500 italic">{{ subj.remark ?? '' }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Summary Bar -->
                <div class="grid grid-cols-5 divide-x divide-gray-200 border-t border-gray-200 bg-gray-50/60 text-xs text-center">
                    <div v-for="(val, lbl) in {
                        'Total': `${result.total_score_obtained.toFixed(0)}/${result.total_score_possible}`,
                        'Average': `${result.average_score.toFixed(1)}%`,
                        'Position': ordinal(result.position_in_class),
                        'Passed': result.subjects_passed,
                        'Failed': result.subjects_failed,
                    }" :key="lbl" class="py-2 px-2">
                        <div class="text-gray-400 uppercase font-medium">{{ lbl }}</div>
                        <div
                            class="font-bold mt-0.5"
                            :class="lbl === 'Failed' && Number(val) > 0 ? 'text-red-600' : 'text-gray-800'"
                        >{{ val }}</div>
                    </div>
                </div>

                <!-- Remarks -->
                <div class="grid grid-cols-2 divide-x divide-gray-200 border-t border-gray-200 text-xs px-0">
                    <div class="px-5 py-3">
                        <p class="text-gray-400 uppercase font-medium mb-1">Class Teacher's Remark</p>
                        <p class="italic text-gray-600 min-h-[28px] border-b border-dashed border-gray-300 pb-1">{{ result.class_teacher_remark ?? ' ' }}</p>
                        <p class="text-gray-400 mt-2">Signature: ______________________</p>
                    </div>
                    <div class="px-5 py-3">
                        <p class="text-gray-400 uppercase font-medium mb-1">Principal's Remark</p>
                        <p class="italic text-gray-600 min-h-[28px] border-b border-dashed border-gray-300 pb-1">{{ result.principal_remark ?? ' ' }}</p>
                        <p class="text-gray-400 mt-2">Signature: ______________________</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="results.length === 0" class="text-center py-16 text-gray-500">
            <i class="pi pi-file text-5xl text-gray-300 mb-4 block" />
            <p>No computed results found for this section.</p>
        </div>
    </AuthenticatedLayout>
</template>

<style>
@media print {
    [data-sidebar], [data-topbar], [data-breadcrumb], .print\:hidden { display: none !important; }
    #bulk-print-area .rounded-2xl { border-radius: 0; }
    body { background: white; }
    @page { margin: 8mm 12mm; size: A4; }
}
</style>
