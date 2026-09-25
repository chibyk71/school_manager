<!--
  resources/js/Pages/Academic/Exams/Index.vue

  Exam Index Page — Lists all exams for the current school with full lifecycle actions.
  Phase 5 migration: AdvancedDataTable only (no dataProperty / legacy props).
  Product behavior preserved from master.
-->
<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import type { BulkAction, ColumnDefinition, TableAction } from '@/types/datatables'
import { usePermissions } from '@/composables/usePermissions'
import { useDeleteResource } from '@/composables/useDelete'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import Tag from 'primevue/tag'
import axios from 'axios'
import { formatDate } from '@/helpers'

const props = defineProps<{
    columns: ColumnDefinition<any>[]
    currentTerm?: { id: string | number; name?: string }
    assessmentTemplates?: Array<{ id: string | number; name: string }>
    classLevels?: Array<{ id: string | number; name: string }>
    academicSessions?: Array<{ id: string | number; name: string }>
    terms?: Array<{ id: string | number; name: string; academic_session_id?: string | number }>
}>()

const toast = useToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()
const { deleteResource } = useDeleteResource()
const tableRef = ref<InstanceType<typeof AdvancedDataTable> | null>(null)

const columns = computed(() => props.columns ?? [])
const currentTerm = computed(() => props.currentTerm)

const showModal = ref(false)
const editingExam = ref<any>(null)
const formErrors = ref<Record<string, string>>({})
const form = ref({
    name: '',
    assessment_template_id: null as string | number | null,
    class_level_id: null as string | number | null,
    academic_session_id: null as string | number | null,
    term_id: null as string | number | null,
    exam_date: null as Date | string | null,
    status: 'draft',
    notes: '',
})

const modalTitle = computed(() => (editingExam.value ? 'Edit Exam' : 'Create Exam'))

const statusOptions = [
    { label: 'Draft', value: 'draft' },
    { label: 'Published', value: 'published' },
    { label: 'In Progress', value: 'in_progress' },
    { label: 'Completed', value: 'completed' },
    { label: 'Results Approved', value: 'results_approved' },
]

const resetForm = () => {
    form.value = {
        name: '',
        assessment_template_id: null,
        class_level_id: null,
        academic_session_id: null,
        term_id: null,
        exam_date: null,
        status: 'draft',
        notes: '',
    }
    formErrors.value = {}
    editingExam.value = null
}

const openCreateModal = () => {
    resetForm()
    if (currentTerm.value?.id) {
        form.value.term_id = currentTerm.value.id
    }
    showModal.value = true
}

const openEditModal = (row: any) => {
    editingExam.value = row
    form.value = {
        name: row.name ?? '',
        assessment_template_id: row.assessment_template_id ?? null,
        class_level_id: row.class_level_id ?? null,
        academic_session_id: row.academic_session_id ?? null,
        term_id: row.term_id ?? null,
        exam_date: row.exam_date ? new Date(row.exam_date) : null,
        status: row.status ?? 'draft',
        notes: row.notes ?? '',
    }
    formErrors.value = {}
    showModal.value = true
}

const submitForm = async () => {
    formErrors.value = {}
    const payload = {
        ...form.value,
        exam_date: form.value.exam_date
            ? form.value.exam_date instanceof Date
                ? form.value.exam_date.toISOString().slice(0, 10)
                : form.value.exam_date
            : null,
    }
    try {
        if (editingExam.value) {
            await axios.put(route('exams.update', editingExam.value.id), payload)
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Exam updated.' })
        } else {
            await axios.post(route('exams.store'), payload)
            toast.add({ severity: 'success', summary: 'Created', detail: 'Exam created.' })
        }
        showModal.value = false
        tableRef.value?.refresh?.()
    } catch (e: any) {
        const errors = e?.response?.data?.errors
        if (errors) {
            formErrors.value = Object.fromEntries(
                Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : String(v)]),
            )
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save exam.' })
        }
    }
}

const transitionStatus = async (id: string | number, status: string) => {
    try {
        await axios.patch(route('exams.transition', id), { status })
        toast.add({ severity: 'success', summary: 'Status updated' })
        tableRef.value?.refresh?.()
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update status.' })
    }
}

const actions = computed<TableAction<any>[]>(() => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        handler: (row) => openEditModal(row),
        show: () => hasPermission('exams.update'),
    },
    {
        label: 'Publish',
        icon: 'pi pi-send',
        severity: 'success',
        show: (row) => row.status === 'draft' && hasPermission('exams.publish'),
        handler: (row) => transitionStatus(row.id, 'published'),
    },
    {
        label: 'Results',
        icon: 'pi pi-chart-bar',
        handler: (row) => router.visit(route('exams.results', row.id)),
        show: () => hasPermission('exams.view'),
    },
    {
        label: 'Report Cards',
        icon: 'pi pi-file',
        handler: (row) => router.visit(route('exams.report-cards', row.id)),
        show: () => hasPermission('exams.view'),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (row) => deleteResource('exams.destroy', [row.id]),
        show: () => hasPermission('exams.delete'),
    },
])

const bulkActions = computed<BulkAction[]>(() => [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (selected) =>
            deleteResource(
                'exams.destroy',
                selected.map((s: any) => s.id),
            ),
        visible: () => hasPermission('exams.delete'),
    },
])

const enhancedColumns = computed(() => {
    const cols = [...(columns.value || [])]
    const upsert = (field: string, def: any) => {
        const idx = cols.findIndex((c: any) => c.field === field)
        if (idx >= 0) cols[idx] = { ...cols[idx], ...def }
        else cols.push({ field, ...def })
    }
    upsert('status', {
        header: 'Status',
        render: (row: any) => ({
            component: Tag as any,
            props: {
                value: row.status?.replace(/_/g, ' ') ?? '—',
                severity:
                    row.status === 'published'
                        ? 'success'
                        : row.status === 'draft'
                          ? 'secondary'
                          : 'info',
            },
        }),
    })
    upsert('exam_date', {
        header: 'Exam Date',
        formatter: (v: any) => (v ? formatDate(v) : '—'),
    })
    upsert('score_entry_progress', {
        header: 'Score Entry',
        render: (row: any) => ({
            template: `<span>${row.score_entry_progress ?? '—'}</span>`,
        }),
    })
    return cols
})
</script>

<template>
    <AuthenticatedLayout
        title="Exams"
        :crumb="[{ label: 'Academic' }, { label: 'Exams' }]"
        :buttons="
            hasPermission('exams.create')
                ? [{ label: 'New Exam', icon: 'pi pi-plus', onClick: openCreateModal }]
                : []
        "
    >
        <AdvancedDataTable
            ref="tableRef"
            :endpoint="route('exams.index')"
            :columns="enhancedColumns"
            :actions="actions"
            :bulk-actions="bulkActions"
            :initial-params="{
                term_id: currentTerm?.id,
            }"
        />

        <Dialog
            v-model:visible="showModal"
            :header="modalTitle"
            modal
            :style="{ width: 'min(680px, 95vw)' }"
            :draggable="false"
        >
            <div class="space-y-5 pt-2">
                <div>
                    <label class="block text-sm font-medium mb-1">Exam Name</label>
                    <InputText v-model="form.name" class="w-full" :invalid="!!formErrors.name" />
                    <small v-if="formErrors.name" class="text-red-500">{{ formErrors.name }}</small>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Assessment Template</label>
                        <Select
                            v-model="form.assessment_template_id"
                            :options="assessmentTemplates ?? []"
                            option-label="name"
                            option-value="id"
                            class="w-full"
                            placeholder="Select template"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Class Level</label>
                        <Select
                            v-model="form.class_level_id"
                            :options="classLevels ?? []"
                            option-label="name"
                            option-value="id"
                            class="w-full"
                            placeholder="Select class"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Session</label>
                        <Select
                            v-model="form.academic_session_id"
                            :options="academicSessions ?? []"
                            option-label="name"
                            option-value="id"
                            class="w-full"
                            placeholder="Select session"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Term</label>
                        <Select
                            v-model="form.term_id"
                            :options="terms ?? []"
                            option-label="name"
                            option-value="id"
                            class="w-full"
                            placeholder="Select term"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Exam Date</label>
                        <DatePicker v-model="form.exam_date" class="w-full" date-format="yy-mm-dd" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Status</label>
                        <Select
                            v-model="form.status"
                            :options="statusOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Notes</label>
                    <Textarea v-model="form.notes" class="w-full" rows="3" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" text @click="showModal = false" />
                <Button label="Save" icon="pi pi-check" @click="submitForm" />
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
