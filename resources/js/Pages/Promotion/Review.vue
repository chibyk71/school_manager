<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button, DataTable, Column, Tag, Dialog, Textarea, Select, InputText, Message } from 'primevue';
import { FilterMatchMode } from '@primevue/core/api';
import type { PromotionBatch, PromotionStudent, PromotionDecision } from '@/types/promotion';
import { PROMOTION_STATUS_CONFIG, PROMOTION_DECISION_CONFIG } from '@/types/promotion';

const props = defineProps<{
    batch: PromotionBatch;
    students: PromotionStudent[];
    can: { review: boolean; approve: boolean; execute: boolean; cancel: boolean };
}>();

const filters = ref({ global: { value: null as string | null, matchMode: FilterMatchMode.CONTAINS } });
const canOverride = computed(() => props.can.review && (props.batch.status === 'pending' || props.batch.status === 'reviewing'));
const overrideVisible = ref(false);
const selected = ref<PromotionStudent | null>(null);
const overrideForm = useForm({ final_decision: 'promote' as PromotionDecision, override_reason: '' });
const decisionOptions = [
    { label: 'Promote', value: 'promote' },
    { label: 'Repeat', value: 'repeat' },
    { label: 'Graduate', value: 'graduate' },
];
const decisionTag = (d: string | null | undefined) => {
    if (!d) return { value: '—', severity: 'secondary' as const };
    const cfg = PROMOTION_DECISION_CONFIG[d as PromotionDecision];
    return { value: cfg?.label ?? d, severity: cfg?.severity ?? ('secondary' as const) };
};
const openOverride = (row: PromotionStudent) => {
    selected.value = row;
    overrideForm.final_decision = (row.final_decision ?? row.recommendation) as PromotionDecision;
    overrideForm.override_reason = row.override_reason ?? '';
    overrideForm.clearErrors();
    overrideVisible.value = true;
};
const submitOverride = () => {
    if (!selected.value) return;
    overrideForm.post(route('promotions.override', { batch: props.batch.id, student: selected.value.id }), {
        preserveScroll: true,
        onSuccess: () => { overrideVisible.value = false; selected.value = null; },
    });
};
const statusCfg = PROMOTION_STATUS_CONFIG[props.batch.status] ?? { label: props.batch.status_label, severity: 'secondary' as const, icon: 'pi pi-circle' };
const summary = computed(() => {
    const counts = { promote: 0, repeat: 0, graduate: 0, overridden: 0 };
    for (const s of props.students) {
        const o = (s.final_decision ?? s.recommendation) as PromotionDecision;
        if (o in counts) counts[o]++;
        if (s.is_overridden) counts.overridden++;
    }
    return counts;
});
</script>
<template>
    <AuthenticatedLayout title="Review Promotions" :crumb="[{ label: 'Promotions', url: route('promotions.index') }, { label: batch.name, url: route('promotions.show', batch.id) }, { label: 'Review' }]">
        <Head :title="`Review — ${batch.name}`" />
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Review Students</h1>
                    <Tag :value="statusCfg.label" :severity="statusCfg.severity" :icon="statusCfg.icon" />
                </div>
                <p class="text-gray-600 dark:text-gray-400 mt-1">{{ batch.name }} · {{ batch.academic_session?.name ?? '—' }}</p>
                <div class="flex flex-wrap gap-3 mt-3 text-sm">
                    <span class="text-green-700">Promote: {{ summary.promote }}</span>
                    <span class="text-amber-700">Repeat: {{ summary.repeat }}</span>
                    <span class="text-blue-700">Graduate: {{ summary.graduate }}</span>
                    <span class="text-gray-600">Overridden: {{ summary.overridden }}</span>
                </div>
            </div>
            <Button label="Batch details" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.visit(route('promotions.show', batch.id))" />
        </div>
        <Message v-if="!canOverride" severity="secondary" :closable="false" class="mb-4">Overrides are locked for this batch status. You can still browse recommendations.</Message>
        <div class="card bg-white dark:bg-dark-bg-secondary rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <DataTable :value="students" dataKey="id" paginator :rows="20" :rowsPerPageOptions="[10,20,50,100]" v-model:filters="filters" :globalFilterFields="['student.name','student.admission_number','current_class_section.name','recommendation','final_decision']" filterDisplay="menu" size="small" responsiveLayout="scroll" stripedRows class="text-sm">
                <template #header><div class="flex justify-end"><InputText v-model="filters.global.value" placeholder="Search students…" class="w-full max-w-xs" /></div></template>
                <template #empty><div class="text-center py-10 text-gray-500">No students in this batch yet.</div></template>
                <Column header="Student" sortable field="student.name"><template #body="{ data }"><div class="font-medium">{{ data.student?.name ?? '—' }}</div><div class="text-xs text-gray-500">{{ data.student?.admission_number ?? '' }}</div></template></Column>
                <Column header="Current class"><template #body="{ data }">{{ data.current_class_section?.name ?? '—' }}</template></Column>
                <Column header="Next class"><template #body="{ data }">{{ data.next_class_section?.name ?? '—' }}</template></Column>
                <Column header="Avg" sortable field="average_score"><template #body="{ data }"><span class="tabular-nums">{{ data.average_score != null ? Number(data.average_score).toFixed(1) : '—' }}</span></template></Column>
                <Column header="Failed"><template #body="{ data }"><span class="tabular-nums">{{ data.failed_subjects_count ?? 0 }}/{{ data.total_subjects_count ?? 0 }}</span></template></Column>
                <Column header="Attendance"><template #body="{ data }"><span class="tabular-nums">{{ data.attendance_percentage != null ? `${Number(data.attendance_percentage).toFixed(0)}%` : '—' }}</span></template></Column>
                <Column header="Recommendation"><template #body="{ data }"><Tag :value="decisionTag(data.recommendation).value" :severity="decisionTag(data.recommendation).severity" class="text-xs" /></template></Column>
                <Column header="Final"><template #body="{ data }"><div class="flex items-center gap-1"><Tag :value="decisionTag(data.final_decision ?? data.recommendation).value" :severity="decisionTag(data.final_decision ?? data.recommendation).severity" class="text-xs" /><i v-if="data.is_overridden" class="pi pi-user-edit text-amber-600 text-xs" /></div></template></Column>
                <Column header="" :exportable="false" style="width:5rem"><template #body="{ data }"><Button v-if="canOverride" icon="pi pi-pencil" text rounded size="small" severity="warn" @click="openOverride(data)" /></template></Column>
            </DataTable>
        </div>
        <Dialog v-model:visible="overrideVisible" modal header="Override student decision" :style="{ width: '30rem' }" :closable="!overrideForm.processing">
            <div v-if="selected" class="flex flex-col gap-4 pt-2">
                <div class="text-sm"><span class="font-medium">{{ selected.student?.name }}</span><span class="text-gray-500"> · System: <strong>{{ decisionTag(selected.recommendation).value }}</strong></span></div>
                <div><label class="block text-sm font-medium mb-1">Final decision</label><Select v-model="overrideForm.final_decision" :options="decisionOptions" optionLabel="label" optionValue="value" class="w-full" /></div>
                <div><label class="block text-sm font-medium mb-1">Reason (required)</label><Textarea v-model="overrideForm.override_reason" rows="3" class="w-full" :invalid="!!overrideForm.errors.override_reason" /><small v-if="overrideForm.errors.override_reason" class="text-red-500">{{ overrideForm.errors.override_reason }}</small></div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" text :disabled="overrideForm.processing" @click="overrideVisible = false" />
                <Button label="Save override" icon="pi pi-check" severity="warn" :loading="overrideForm.processing" @click="submitOverride" />
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
