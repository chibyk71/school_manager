<!--
Grades.vue – Main listing page for managing grading scales
───────────────────────────────────────────────────────────────────────────────────────────────
Purpose / Features Implemented:
• Displays all grades in a server-side PrimeVue DataTable (pagination, search, sort, filter)
• Supports contextual filtering when nested under a school section
• Bulk actions: soft-delete, force-delete, restore (with confirmation dialogs)
• Triggers create/edit modals via ModalService
• Handles trashed view toggle (soft-deleted grades) with restore/delete options
• Responsive layout, accessible (ARIA labels, keyboard navigation), Tailwind + PrimeVue styled
• Uses useDataTable composable for consistent server-side table behavior
• Integrates with GradeResource JSON shape from backend
• Real-time toast notifications on success/error
• Type-safe with GradeListItem from Types/grade.ts

How it fits into the Grades Module:
───────────────────────────────────────────────────────────────────────────────────────────────
• Inertia entry point rendered by GradeController::index()
• Receives props: grades (paginated data), schoolSection (optional context), schoolSections (for modal)
• Primary UI for viewing, creating, editing, deleting, and restoring grades
• Coordinates with:
  - GradeModal.vue (create/edit form)
  - ModalService (open/close modals)
  - useDataTable composable (server-side table logic)
  - useRestoreResource / useDeleteResource composables (confirmation + API calls)
  - GradeResource backend output (matches table columns)
• Supports both normal view and trashed view (via showTrashed toggle)

Tech Stack Alignment:
• Vue 3 Composition API + <script setup>
• PrimeVue: DataTable, Column, Button, MultiSelect, ConfirmDialog, Toast
• Inertia.js: usePage() for props, router for reload
• Tailwind CSS for layout/responsiveness
• Ziggy for route() helper
• TypeScript types from Types/grade.ts

Props (from Inertia):
• grades: paginated data (from tableQuery)
• schoolSection: current section context (optional)
• schoolSections: list for modal section selection
-->

<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useModal } from '@/composables/useModal'
import { useRestoreResource } from '@/composables/useRestoreResource'
import type { GradeListItem } from '@/types/grade'
import { useDeleteResource } from '@/composables/useDelete'
import type { BulkAction, TableAction, TableQueryProps } from '@/types/datatables'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import SettingsLayout from '../Partials/SettingsLayout.vue'
import { useSettingsNavigation } from '@/composables/useSettingsNavigation'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import { useEnhancedColumns } from '@/composables/useEnhancedColumns'
import { Tag } from 'primevue'
import { usePermissions } from '@/composables/usePermissions'

// ─── Inertia Page Props ───────────────────────────────────────────────────────────
const props = defineProps<{
    grades: TableQueryProps<GradeListItem>
    schoolSection: { id: number; name: string } | null
    schoolSections: { id: number; name: string }[]
    crumbs: Array<{ label: string }>
}>()

// ─── Services / Composables ──────────────────────────────────────────────────────
const toast = useToast()
const confirm = useConfirm()
const modal = useModal()
const { restoreResource } = useRestoreResource()
const { academicSettingsNav } = useSettingsNavigation()
const { deleteResource } = useDeleteResource()
const { hasPermission } = usePermissions()


// ─── Modal Triggers ───────────────────────────────────────────────────────────────
const openCreateModal = () => {
    modal.open('grade-form', { grade: null })
}

const openEditModal = (grade: GradeListItem) => {
    modal.open('grade-form', { grade })
}

// ─── Restore Handler (single or bulk possible in future) ──────────────────────────
const handleRestore = (id: string) => {
    restoreResource('grades', [id], {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Restored', detail: 'Grade restored successfully.' })
            router.reload()
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to restore grade.' })
        },
    })
}

const { enhancedColumns } = useEnhancedColumns(props.grades.columns, {
    'in_use': {
        header: 'In Use',
        render: (data: GradeListItem) => ({
            component: Tag as any,
            props: { value: data.is_used ? 'Yes' : 'No', severity: data.is_used ? 'danger' : 'success', rounded: true },
        }),
    },
})

const TableActions = ref<TableAction<GradeListItem>[]>([
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        severity: 'info',
        handler: (data) => openEditModal(data),
        show: (data) => !data.deleted_at && hasPermission('grades.update'), // Only show if not trashed
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (data) => deleteResource('grades', [data.id.toString()]), // TODO: add confirmation(data),
        show: (data) => !data.deleted_at && hasPermission('grades.delete'), // Only show if not trashed
    },
    {
        label: 'Restore',
        icon: 'pi pi-recycle',
        severity: 'success',
        handler: (data) => handleRestore(data.id.toString()),
        show: (data) => !!data.deleted_at && hasPermission('grades.restore'), // Only show if trashed and user has permission
    },
])

const bulkActions = ref<BulkAction<GradeListItem>[]>([
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (selected) => deleteResource('grades', selected.map((s) => s.id.toString())), // TODO: add confirmation(selected, false),
        visible: (selected) => selected.length > 0 && selected.some(item => !item.deleted_at) && hasPermission('grades.delete'),
    },
    {
        label: 'Force Delete Selected',
        icon: 'pi pi-times-circle',
        severity: 'danger',
        handler: (selected) => deleteResource('grades', selected.map((s) => s.id.toString()), { force: true }), // TODO: add confirmation(selected, true)
        visible: (selected) => selected.length > 0 && selected.some(item => item.deleted_at) && hasPermission('grades.force_delete'),
    },
])
</script>

<style scoped>
/* Custom Tailwind overrides for better spacing / alignment */
:deep(.p-datatable .p-datatable-thead > tr > th) {
    @apply bg-gray-50 text-gray-700 font-medium;
}
</style>
<template>
    <AuthenticatedLayout title="Grading Scales" :crumb="props.crumbs"
        :buttons="[{ label: 'Add Grading Scale', icon: 'pi pi-plus', severity: 'success', onClick: openCreateModal }]">

        <Head title="Grading Scales" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Academic" :items="academicSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-6xl">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-2xl font-bold">Grading Scales</h1>
                            <p class="text-gray-600 mt-1">
                                Manage grading scales and assign them to school sections.
                                <span v-if="schoolSection">Showing grades for: {{ schoolSection.name }}</span>
                            </p>
                        </div>
                        <!-- <Button label="Add Grading Scale" @click="openModal()" /> -->
                    </div>
                    <AdvancedDataTable :endpoint="route('grades.index')" :columns="enhancedColumns" :data="grades.data"
                        rowKey="id" :tableActions="TableActions" :bulkActions="bulkActions" />
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
