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

const props = defineProps<{
    grades: TableQueryProps<GradeListItem>
    schoolSection: { id: number; name: string } | null
    schoolSections: { id: number; name: string }[]
    crumbs: Array<{ label: string }>
}>()

const toast = useToast()
const confirm = useConfirm()
const modal = useModal()
const { restoreResource } = useRestoreResource()
const { academicSettingsNav } = useSettingsNavigation()
const { deleteResource } = useDeleteResource()
const { hasPermission } = usePermissions()

const openCreateModal = () => {
    modal.open('grade-form', { grade: null })
}

const openEditModal = (grade: GradeListItem) => {
    modal.open('grade-form', { grade })
}

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
    in_use: {
        header: 'In Use',
        render: (data: GradeListItem) => ({
            component: Tag as any,
            props: {
                value: data.is_used ? 'Yes' : 'No',
                severity: data.is_used ? 'danger' : 'success',
                rounded: true,
            },
        }),
    },
})

const TableActions = ref<TableAction<GradeListItem>[]>([
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        severity: 'info',
        handler: (data) => openEditModal(data),
        show: (data) => !data.deleted_at && hasPermission('grades.update'),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (data) => deleteResource('grades', [data.id.toString()]),
        show: (data) => !data.deleted_at && hasPermission('grades.delete'),
    },
    {
        label: 'Restore',
        icon: 'pi pi-recycle',
        severity: 'success',
        handler: (data) => handleRestore(data.id.toString()),
        show: (data) => !!data.deleted_at && hasPermission('grades.restore'),
    },
])

const bulkActions = ref<BulkAction<GradeListItem>[]>([
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (selected) => deleteResource('grades', selected.map((s) => s.id.toString())),
        visible: (selected) =>
            selected.length > 0 && selected.some((item) => !item.deleted_at) && hasPermission('grades.delete'),
    },
    {
        label: 'Force Delete Selected',
        icon: 'pi pi-times-circle',
        severity: 'danger',
        handler: (selected) =>
            deleteResource(
                'grades',
                selected.map((s) => s.id.toString()),
                { force: true },
            ),
        visible: (selected) =>
            selected.length > 0 && selected.some((item) => item.deleted_at) && hasPermission('grades.force_delete'),
    },
])
</script>

<template>
    <AuthenticatedLayout
        title="Grading Scales"
        :crumb="props.crumbs"
        :buttons="[{ label: 'Add Grading Scale', icon: 'pi pi-plus', severity: 'success', onClick: openCreateModal }]"
    >
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
                    </div>
                    <AdvancedDataTable
                        :endpoint="route('grades.index')"
                        :columns="enhancedColumns"
                        :initial-data="grades.data"
                        :actions="TableActions"
                        :bulk-actions="bulkActions"
                    />
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
    @apply bg-gray-50 text-gray-700 font-medium;
}
</style>
