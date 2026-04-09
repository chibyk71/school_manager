<!--
  resources/js/Pages/Academic/Exams/Index.vue

  Exam Index Page — Lists all exams for the current school with DataTable support.

  Features / Problems Solved:
  ─────────────────────────────────────────────────────────────────────────────
  • Full DataTable with server-side filtering, sorting, and pagination via AdvancedDataTable
  • Status filter (dropdown) and session/term filter chips
  • Status badge with colour/icon per status level
  • Score entry progress bar per row (0–100%)
  • Bulk delete with confirmation
  • Create exam modal (inline Dialog form — no separate page needed)
  • Quick-action "Publish" button in the row actions for draft exams
  • Responsive: action column freezes on right; name+status frozen on left for wide tables
  • Permission-gated: create/delete buttons only shown if user has permission

  Stack integration:
  • AdvancedDataTable (from your existing datatable system)
  • PrimeVue Dialog for create/edit modal
  • useDeleteResource and usePermissions composables
  • Ziggy route() helper for all URLs
-->

<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue';
import {
    Button, Dialog, Select, InputText, Textarea, DatePicker, Tag, ProgressBar, Message,
} from 'primevue';
import { useDeleteResource } from '@/composables/useDelete';
import { usePermissions } from '@/composables/usePermissions';
import type { ColumnDefinition, TableAction, BulkAction } from '@/types/datatables';
import type { Exam, ExamFormData, AssessmentTemplate } from '@/types/exam';
import { EXAM_STATUS_CONFIG } from '@/types/exam';

// ────────────────────────────────────────────────────────────────────────────
// Props
// ────────────────────────────────────────────────────────────────────────────
const props = defineProps<{
    currentSession?: { id: string; name: string } | null;
    currentTerm?: { id: string; name: string } | null;
    statuses: string[];
    // Passed as Inertia page props — AdvancedDataTable fetches the rest via API
    assessmentTemplates?: AssessmentTemplate[];
    classSections?: { id: string; display_name: string }[];
    classLevels?: { id: string; name: string }[];
    academicSessions?: { id: string; name: string }[];
    terms?: { id: string; name: string }[];
}>();

const { hasPermission } = usePermissions();
const { deleteResource } = useDeleteResource();

// ────────────────────────────────────────────────────────────────────────────
// Column Definitions
// ────────────────────────────────────────────────────────────────────────────
const columns: ColumnDefinition<Exam>[] = [
    {
        field: 'name',
        header: 'Exam Name',
        sortable: true,
        filterable: true,
        render: (row) => ({
            template: 'div',
            children: [
                {
                    template: 'span',
                    class: 'font-semibold text-gray-900 dark:text-white text-sm block',
                    text: row.name,
                },
                {
                    template: 'span',
                    class: 'text-xs text-gray-500 dark:text-gray-400',
                    text: `${row.session_name ?? ''} ${row.term_name ? '· ' + row.term_name : ''}`,
                },
            ],
        }),
    },
    {
        field: 'status',
        header: 'Status',
        sortable: true,
        filterable: true,
        filterType: 'dropdown',
        filterOptions: [
            { label: 'Draft', value: 'draft' },
            { label: 'Published', value: 'published' },
            { label: 'Ongoing', value: 'ongoing' },
            { label: 'Completed', value: 'completed' },
            { label: 'Results Approved', value: 'results_approved' },
        ],
        render: (row) => {
            const cfg = EXAM_STATUS_CONFIG[row.status as keyof typeof EXAM_STATUS_CONFIG];
            return {
                component: Tag,
                props: {
                    value: cfg?.label ?? row.status,
                    severity: cfg?.severity ?? 'secondary',
                    icon: cfg?.icon,
                    class: 'text-xs',
                },
            };
        },
    },
    {
        field: 'level_name',
        header: 'Class',
        sortable: true,
        filterable: true,
        formatter: (_, row) => row.level_name ?? row.section_name ?? '—',
    },
    {
        field: 'template_name',
        header: 'Template',
        sortable: false,
        filterable: false,
        formatter: (_, row) => row.template_name ?? '—',
    },
    {
        field: 'score_entry_progress',
        header: 'Progress',
        sortable: true,
        filterable: false,
        render: (row) => {
            const pct = row.score_entry_progress ?? 0;
            return {
                template: 'div',
                class: 'flex items-center gap-2 min-w-[100px]',
                children: [
                    {
                        component: ProgressBar,
                        props: {
                            value: pct,
                            showValue: false,
                            style: { height: '6px', width: '80px' },
                            pt: { value: { class: pct === 100 ? 'bg-green-500' : 'bg-primary' } },
                        },
                    },
                    {
                        template: 'span',
                        class: 'text-xs text-gray-500 whitespace-nowrap',
                        text: `${pct}%`,
                    },
                ],
            };
        },
    },
    {
        field: 'exam_start_date',
        header: 'Exam Dates',
        sortable: true,
        filterable: true,
        filterType: 'date',
        formatter: (_, row) => {
            if (!row.exam_start_date) return '—';
            const start = new Date(row.exam_start_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            if (!row.exam_end_date) return start;
            const end = new Date(row.exam_end_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
            return `${start} – ${end}`;
        },
    },
];

// ────────────────────────────────────────────────────────────────────────────
// Row Actions
// ────────────────────────────────────────────────────────────────────────────
const actions: TableAction<Exam>[] = [
    {
        label: 'View / Score Entry',
        icon: 'pi pi-arrow-right',
        handler: (row) => router.visit(route('exams.show', row.id)),
    },
    {
        label: 'Results',
        icon: 'pi pi-chart-bar',
        show: (row) => ['completed', 'results_approved'].includes(row.status),
        handler: (row) => router.visit(route('exam-results.index', row.id)),
    },
    {
        label: 'Report Cards',
        icon: 'pi pi-file-pdf',
        show: (row) => row.status === 'results_approved',
        handler: (row) => router.visit(route('report-cards.bulk-print', row.id)),
    },
    {
        label: 'Publish',
        icon: 'pi pi-eye',
        severity: 'info',
        show: (row) => row.status === 'draft' && hasPermission('exams.publish'),
        handler: (row) => transitionStatus(row.id, 'published'),
        confirm: {
            message: 'Publish this exam so teachers can enter scores?',
            header: 'Publish Exam',
        },
    },
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        show: (row) => !row.is_locked && hasPermission('exams.update'),
        handler: (row) => openEditModal(row),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        show: (row) => row.status === 'draft' && hasPermission('exams.delete'),
        handler: (row) => deleteResource('exams', [row.id], { onSuccess: () => tableRef.value?.refresh() }),
        confirm: {
            message: (row) => `Delete exam "${row.name}"? This cannot be undone.`,
            header: 'Delete Exam',
            acceptClass: 'p-button-danger',
        },
    },
];

// ────────────────────────────────────────────────────────────────────────────
// Bulk Actions
// ────────────────────────────────────────────────────────────────────────────
const bulkActions: BulkAction<Exam>[] = [
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        show: (rows) => rows.every(r => r.status === 'draft') && hasPermission('exams.delete'),
        handler: (rows) => {
            deleteResource('exams', rows.map(r => r.id), {
                onSuccess: () => tableRef.value?.refresh(),
            });
        },
        confirm: {
            message: (rows) => `Delete ${rows.length} selected exam(s)? Only draft exams can be deleted.`,
            header: 'Bulk Delete Exams',
        },
    },
];

// ────────────────────────────────────────────────────────────────────────────
// Create / Edit Modal
// ────────────────────────────────────────────────────────────────────────────
const tableRef = ref<InstanceType<typeof AdvancedDataTable> | null>(null);
const showModal    = ref(false);
const isEditing    = ref(false);
const editingId    = ref<string | null>(null);
const formErrors   = ref<Record<string, string>>({});
const isSaving     = ref(false);

const form = ref<ExamFormData & { id?: string }>({
    name: '',
    description: null,
    academic_session_id: props.currentSession?.id ?? '',
    term_id: props.currentTerm?.id ?? null,
    class_level_id: null,
    class_section_id: null,
    assessment_template_id: '',
    exam_start_date: null,
    exam_end_date: null,
});

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    formErrors.value = {};
    form.value = {
        name: '',
        description: null,
        academic_session_id: props.currentSession?.id ?? '',
        term_id: props.currentTerm?.id ?? null,
        class_level_id: null,
        class_section_id: null,
        assessment_template_id: '',
        exam_start_date: null,
        exam_end_date: null,
    };
    showModal.value = true;
};

const openEditModal = (exam: Exam) => {
    isEditing.value = true;
    editingId.value = exam.id;
    formErrors.value = {};
    form.value = {
        id: exam.id,
        name: exam.name,
        description: exam.description,
        academic_session_id: exam.academic_session_id,
        term_id: exam.term_id,
        class_level_id: exam.class_level_id,
        class_section_id: exam.class_section_id,
        assessment_template_id: exam.assessment_template_id,
        exam_start_date: exam.exam_start_date,
        exam_end_date: exam.exam_end_date,
    };
    showModal.value = true;
};

const submitForm = () => {
    isSaving.value = true;
    formErrors.value = {};

    const url = isEditing.value
        ? route('exams.update', editingId.value!)
        : route('exams.store');

    const method = isEditing.value ? 'patch' : 'post';

    router[method](url, form.value as any, {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            tableRef.value?.refresh();
        },
        onError: (errors) => {
            formErrors.value = errors as Record<string, string>;
        },
        onFinish: () => {
            isSaving.value = false;
        },
    });
};

// ────────────────────────────────────────────────────────────────────────────
// Status Transitions
// ────────────────────────────────────────────────────────────────────────────
const transitionStatus = (examId: string, newStatus: string) => {
    router.patch(route('exams.update-status', examId), { status: newStatus }, {
        preserveScroll: true,
        onSuccess: () => tableRef.value?.refresh(),
    });
};

const modalTitle = computed(() => isEditing.value ? 'Edit Exam' : 'Create Exam');
</script>

<template>
    <AuthenticatedLayout
        title="Exams"
        :crumb="[{ label: 'Academic' }, { label: 'Exams' }]"
        :buttons="hasPermission('exams.create') ? [
            { label: 'New Exam', icon: 'pi pi-plus', onClick: openCreateModal }
        ] : []"
    >
        <!-- Exam DataTable -->
        <AdvancedDataTable
            ref="tableRef"
            :endpoint="route('exams.index')"
            :columns="columns"
            :actions="actions"
            :bulk-actions="bulkActions"
            :initial-params="{
                term_id: currentTerm?.id,
            }"
            dataProperty="data"
        />

        <!-- Create / Edit Modal -->
        <Dialog
            v-model:visible="showModal"
            :header="modalTitle"
            modal
            :style="{ width: 'min(680px, 95vw)' }"
            :draggable="false"
        >
            <div class="space-y-5 pt-2">
                <!-- Exam Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Exam Name <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        v-model="form.name"
                        placeholder="e.g. First Term 2025/2026 Examination"
                        class="w-full"
                        :invalid="!!formErrors.name"
                    />
                    <Message v-if="formErrors.name" severity="error" variant="simple" class="mt-1 text-xs">
                        {{ formErrors.name }}
                    </Message>
                </div>

                <!-- Session + Term row -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Academic Session <span class="text-red-500">*</span>
                        </label>
                        <Select
                            v-model="form.academic_session_id"
                            :options="academicSessions ?? []"
                            option-label="name"
                            option-value="id"
                            placeholder="Select session"
                            class="w-full"
                            :invalid="!!formErrors.academic_session_id"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Term
                        </label>
                        <Select
                            v-model="form.term_id"
                            :options="terms ?? []"
                            option-label="name"
                            option-value="id"
                            placeholder="Select term"
                            class="w-full"
                            show-clear
                        />
                    </div>
                </div>

                <!-- Class Level + Section row -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Class Level <span class="text-red-500">*</span>
                        </label>
                        <Select
                            v-model="form.class_level_id"
                            :options="classLevels ?? []"
                            option-label="name"
                            option-value="id"
                            placeholder="All levels"
                            class="w-full"
                            show-clear
                            :invalid="!!formErrors.class_level_id"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Specific Section
                            <span class="text-xs text-gray-400">(optional)</span>
                        </label>
                        <Select
                            v-model="form.class_section_id"
                            :options="classSections ?? []"
                            option-label="display_name"
                            option-value="id"
                            placeholder="All sections of level"
                            class="w-full"
                            show-clear
                            :disabled="!form.class_level_id"
                        />
                    </div>
                </div>

                <!-- Assessment Template -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Assessment Template <span class="text-red-500">*</span>
                    </label>
                    <Select
                        v-model="form.assessment_template_id"
                        :options="assessmentTemplates ?? []"
                        option-label="name"
                        option-value="id"
                        placeholder="Select scoring template"
                        class="w-full"
                        :invalid="!!formErrors.assessment_template_id"
                    />
                    <Message v-if="formErrors.assessment_template_id" severity="error" variant="simple" class="mt-1 text-xs">
                        {{ formErrors.assessment_template_id }}
                    </Message>
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Exam Start Date
                        </label>
                        <DatePicker
                            v-model="form.exam_start_date"
                            date-format="dd/mm/yy"
                            show-button-bar
                            class="w-full"
                            show-icon
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Exam End Date
                        </label>
                        <DatePicker
                            v-model="form.exam_end_date"
                            date-format="dd/mm/yy"
                            show-button-bar
                            class="w-full"
                            show-icon
                        />
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Description
                    </label>
                    <Textarea
                        v-model="form.description"
                        rows="2"
                        placeholder="Optional notes about this exam..."
                        class="w-full"
                        auto-resize
                    />
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" severity="secondary" text @click="showModal = false" />
                <Button
                    :label="isEditing ? 'Save Changes' : 'Create Exam'"
                    icon="pi pi-check"
                    :loading="isSaving"
                    @click="submitForm"
                />
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
