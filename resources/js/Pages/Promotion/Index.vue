<script setup lang="ts">
/**
 * Promotion/Index.vue — list promotion batches for the current school.
 */
import { ref, computed } from 'vue';
import { router, useForm, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Button, DataTable, Column, Tag, Dialog, InputText, Textarea, Select, Message,
} from 'primevue';
import { FilterMatchMode } from '@primevue/core/api';
import type { PromotionBatch } from '@/types/promotion';
import { PROMOTION_STATUS_CONFIG } from '@/types/promotion';

const props = defineProps<{
    batches: PromotionBatch[];
    academicSessions: { id: string; name: string; is_current?: boolean }[];
    can: { create: boolean };
}>();

const filters = ref({
    global: { value: null as string | null, matchMode: FilterMatchMode.CONTAINS },
});

const showCreate = ref(false);
const form = useForm({
    academic_session_id: props.academicSessions.find((s) => s.is_current)?.id ?? props.academicSessions[0]?.id ?? '',
    name: '',
    description: '',
});

const sessionOptions = computed(() =>
    props.academicSessions.map((s) => ({
        label: s.is_current ? `${s.name} (current)` : s.name,
        value: s.id,
    })),
);

const openCreate = () => {
    form.reset();
    form.academic_session_id =
        props.academicSessions.find((s) => s.is_current)?.id ?? props.academicSessions[0]?.id ?? '';
    form.clearErrors();
    showCreate.value = true;
};

const submitCreate = () => {
    form.post(route('promotions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreate.value = false;
        },
    });
};

const statusTag = (status: string) => {
    const cfg = PROMOTION_STATUS_CONFIG[status as keyof typeof PROMOTION_STATUS_CONFIG];
    return {
        value: cfg?.label ?? status,
        severity: cfg?.severity ?? 'secondary',
        icon: cfg?.icon,
    };
};

const goShow = (row: PromotionBatch) => router.visit(route('promotions.show', row.id));
const goReview = (row: PromotionBatch) => router.visit(route('promotions.review', row.id));
</script>

<template>
    <AuthenticatedLayout
        title="Student Promotions"
        :crumb="[{ label: 'Academic' }, { label: 'Promotions' }]"
    >
        <Head title="Student Promotions" />

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Student Promotions</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">
                    Manage end-of-session promotion batches, reviews, and outcomes.
                </p>
            </div>
            <Button
                v-if="can.create"
                label="New Batch"
                icon="pi pi-plus"
                @click="openCreate"
            />
        </div>

        <div class="card bg-white dark:bg-dark-bg-secondary rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <DataTable
                :value="batches"
                dataKey="id"
                paginator
                :rows="15"
                :rowsPerPageOptions="[10, 15, 25, 50]"
                v-model:filters="filters"
                :globalFilterFields="['name', 'status', 'academic_session.name', 'status_label']"
                filterDisplay="menu"
                size="small"
                responsiveLayout="scroll"
                stripedRows
                class="text-sm"
            >
                <template #header>
                    <div class="flex justify-end">
                        <InputText
                            v-model="filters.global.value"
                            placeholder="Search batches…"
                            class="w-full max-w-xs"
                        />
                    </div>
                </template>

                <template #empty>
                    <div class="text-center py-10 text-gray-500">
                        No promotion batches yet.
                        <span v-if="can.create"> Create one or close an academic session to auto-start.</span>
                    </div>
                </template>

                <Column field="name" header="Batch" sortable>
                    <template #body="{ data }">
                        <button
                            type="button"
                            class="text-primary font-medium hover:underline text-left"
                            @click="goShow(data)"
                        >
                            {{ data.name }}
                        </button>
                        <div v-if="data.description" class="text-xs text-gray-500 mt-0.5 line-clamp-1">
                            {{ data.description }}
                        </div>
                    </template>
                </Column>

                <Column field="academic_session.name" header="Session" sortable>
                    <template #body="{ data }">
                        {{ data.academic_session?.name ?? '—' }}
                    </template>
                </Column>

                <Column field="status" header="Status" sortable>
                    <template #body="{ data }">
                        <Tag
                            :value="statusTag(data.status).value"
                            :severity="statusTag(data.status).severity"
                            :icon="statusTag(data.status).icon"
                            class="text-xs"
                        />
                    </template>
                </Column>

                <Column header="Students" sortable field="total_students">
                    <template #body="{ data }">
                        <span class="tabular-nums">
                            {{ data.processed_students }}/{{ data.total_students }}
                        </span>
                        <span v-if="data.failed_students > 0" class="text-red-500 text-xs ml-1">
                            ({{ data.failed_students }} failed)
                        </span>
                    </template>
                </Column>

                <Column field="created_at" header="Created" sortable>
                    <template #body="{ data }">
                        {{ data.created_at ? new Date(data.created_at).toLocaleDateString() : '—' }}
                    </template>
                </Column>

                <Column header="Actions" :exportable="false" style="width: 10rem">
                    <template #body="{ data }">
                        <div class="flex gap-1">
                            <Button
                                icon="pi pi-eye"
                                severity="secondary"
                                text
                                rounded
                                size="small"
                                v-tooltip.top="'View'"
                                @click="goShow(data)"
                            />
                            <Button
                                icon="pi pi-list"
                                severity="info"
                                text
                                rounded
                                size="small"
                                v-tooltip.top="'Review students'"
                                @click="goReview(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog
            v-model:visible="showCreate"
            modal
            header="Create Promotion Batch"
            :style="{ width: '32rem' }"
            :closable="!form.processing"
        >
            <div class="flex flex-col gap-4 pt-2">
                <div>
                    <label class="block text-sm font-medium mb-1">Academic Session</label>
                    <Select
                        v-model="form.academic_session_id"
                        :options="sessionOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select session"
                        class="w-full"
                        :invalid="!!form.errors.academic_session_id"
                    />
                    <small v-if="form.errors.academic_session_id" class="text-red-500">
                        {{ form.errors.academic_session_id }}
                    </small>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Batch name</label>
                    <InputText
                        v-model="form.name"
                        class="w-full"
                        placeholder="e.g. 2025/2026 End-of-Year Promotion"
                        :invalid="!!form.errors.name"
                    />
                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Description (optional)</label>
                    <Textarea v-model="form.description" rows="3" class="w-full" />
                </div>
                <Message severity="info" :closable="false" class="text-sm">
                    Creating a batch queues background population of student recommendations.
                </Message>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" text :disabled="form.processing" @click="showCreate = false" />
                <Button label="Create Batch" icon="pi pi-check" :loading="form.processing" @click="submitCreate" />
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
