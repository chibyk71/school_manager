<!--
  resources/js/Pages/Settings/CustomFields/Index.vue

  MAIN ADMIN MANAGEMENT SCREEN FOR CUSTOM FIELDS
  ────────────────────────────────────────────────────────────────

  This is the primary Inertia page for viewing, managing and reordering custom fields.

  Backend alignment (CustomFieldsController::index):
  • Receives:
    - resources: array of {value, label} from ModelResolver
    - initialResource: string (current or default)
    - fields: paginated collection of effective CustomField (merged global + school)
    - meta: pagination metadata, total, etc.
  • Supports ?resource= query param to filter by model basename

  Features implemented:
  • Resource/model selector (dropdown) – changes URL query & reloads data
  • AdvancedDataTable with:
    - Server-side pagination, sorting, filtering, global search
    - Reorderable rows (drag & drop → PATCH to /order)
    - Per-row actions: Edit (modal), Delete (confirm + useDeleteResource)
    - Bulk selection + bulk delete
    - Column visibility toggler
    - Export (visible / all)
  • New Field button → opens CustomFieldModal (create mode)
  • Apply Preset button → opens future preset modal
  • Loading / error / empty states with retry & user feedback
  • Cache invalidation after mutations (via useCustomFields.invalidateCache)

  Integration points:
  • Uses Inertia shared props + page props from controller
  • Opens modals via useModal composable
  • Uses useDeleteResource for consistent delete UX
  • Reuses AdvancedDataTable (your production component)
  • Aligns with types/datatables.ts (ColumnDefinition, TableAction)

  Tech notes:
  • Responsive: mobile-friendly table + stacked controls
  • Accessible: ARIA roles, labels, keyboard nav via PrimeVue
  • Performance: server-side everything, minimal client processing
  • Error handling: toast + inline messages + retry button

  Future extensions:
  • Preset apply modal
  • Column chooser persistence (localStorage)
  • Live preview column
-->

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useModal } from '@/composables/useModal'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import { Button, Select, Message } from 'primevue'
import type { CustomField, FieldTypeMap } from '@/types/custom-fields'
import { TableQueryProps, type BulkAction, type ColumnDefinition, type TableAction } from '@/types/datatables'
import { useDeleteResource } from '@/composables/useDelete'
import { useEnhancedColumns } from '@/composables/useEnhancedColumns'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useTrashedToggle } from '@/composables/useTrashedToggle'
import { usePermissions } from '@/composables/usePermissions'
import { useRestoreResource } from '@/composables/useRestoreResource'

// ────────────────────────────────────────────────
// Inertia page props from CustomFieldsController::index
// ────────────────────────────────────────────────
const props = defineProps<TableQueryProps<CustomField> & {
    fieldTypes: FieldTypeMap[]
    currentResource: string
    error?: string
}>()

// ────────────────────────────────────────────────
// State & composables
// ────────────────────────────────────────────────
const toast = useToast()
const confirm = useConfirm()
const modal = useModal()
const { deleteResource } = useDeleteResource()
const { showTrashed } = useTrashedToggle();
const { hasPermission } = usePermissions()
const {  restoreResource } = useRestoreResource()

const selectedResource = ref(props.currentResource || 'student')

// ────────────────────────────────────────────────
// Table columns
// ────────────────────────────────────────────────
const { enhancedColumns } = useEnhancedColumns(props.columns, {
    required: {
        field: 'required',
        header: 'Required',
        sortable: true,
        filterType: 'boolean',
        render: (row) => row.required ? 'Yes' : 'No'
    }
})


const actionsButtons: TableAction<CustomField>[] = [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        severity: 'info',
        handler: (row) => openEdit(row)
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (row) => confirmDelete(row),
        confirm: {
            message: (row) => `Delete "${row.label || row.name}"? This action cannot be undone.`,
            header: 'Confirm Deletion',
            icon: 'pi pi-exclamation-triangle',
            acceptClass: 'p-button-danger'
        }
    }
]

const bulkActions: BulkAction<CustomField>[] = [
    {
        label: 'Delete Selected',
        action: 'delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: async (selected) => {
            await bulkDelete(selected)
        }
    },
    {
        label: 'Restore Selected',
        action: 'restore',
        icon: 'pi pi-refresh',
        severity: 'success',
        visible: (rows) => showTrashed.value && hasPermission('custom-fields.restore'),
        handler: async (selected) => {
            restoreResource('custom-fields', selected.map((s) => s.id!))
        }
    }
]
// ────────────────────────────────────────────────
// Resource change → reload page with query param
// ────────────────────────────────────────────────
// watch(selectedResource, (newVal) => {
//         router.get(
//             route('settings.custom-fields'),
//             { resource: newVal },
//             {
//                 preserveState: true,
//                 preserveScroll: true,
//                 onSuccess: () => {
//                     selectedRows.value = []
//                 }
//             }
//         )
//     })

// ────────────────────────────────────────────────
// Actions
// ────────────────────────────────────────────────
const openCreate = () => {
    modal.open('custom-field', {
        field: null,
        onSuccess: () => {
            router.reload({ only: ['fields'] })
            toast.add({ severity: 'success', summary: 'Created', detail: 'New field added', life: 4000 })
        }
    })
}

const openEdit = (field: CustomField) => {
    modal.open('custom-field', {
        field,
        onSuccess: () => {
            router.reload({ only: ['fields'] })
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Field updated', life: 4000 })
        }
    })
}

const confirmDelete = (field: CustomField) => {
    deleteResource('custom-fields', [field.id!], {
        onSuccess: () => {
            router.reload({ only: ['data'] })
        },
        onError: (msg) => {
            toast.add({ severity: 'error', summary: 'Delete Failed', detail: msg, life: 6000 })
        }
    })
}

const bulkDelete = async (selectedRows: CustomField[]) => {
    if (!selectedRows.length) return

    deleteResource('custom-fields', selectedRows.map(f => f.id!), {
        onSuccess: () => {
            router.reload({ only: ['data'] })
        }
    })
}

const handleReorder = async (event: { newIndex: number; oldIndex: number; value: CustomField[] }) => {
    const orderedIds = event.value.map(f => f.id)
    const orderPayload = orderedIds.map((id, index) => ({ id, sort: index + 1 }))

    try {
        await router.patch(route('settings.custom-fields.order'), { order: orderPayload }, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['fields'] })
                toast.add({ severity: 'success', summary: 'Reordered', detail: 'Field order saved', life: 3000 })
            },
            onError: () => {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Could not save order', life: 5000 })
            }
        })
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Reorder failed', life: 5000 })
    }
}
</script>

<template>

    <Head title="Custom Fields Management" />

    <AuthenticatedLayout title="Custom Fields" :crumb="[{ label: 'Settings' }, { label: 'System' }, { label: 'Custom Fields' }]"
        :buttons="[{ icon: 'ti ti-refresh', severity: 'secondary', size: 'small' }, { label: 'Create New', icon: 'ti ti-plus', onClick: openCreate }]">

        <!-- Error message from server -->
        <Message v-if="error" severity="error" :closable="false" class="mb-6">
            {{ props.error }}
        </Message>

        <!-- TODO: Filter by resource, add a dropdown to list supported resources, to filter by -->

        <!-- Main content -->
        <AdvancedDataTable :endpoint="route('settings.system.custom-fields', { resource: selectedResource })"
            :columns="enhancedColumns" :initial-data="props.data" :total-records="props.totalRecords"
            :initial-params="{ resource: selectedResource }" @row-reorder="handleReorder" data-key="id"
            :global-filter-fields="globalFilterables" :export-filename="`custom-fields-${selectedResource}`" :actions="actionsButtons" :bulk-actions="bulkActions">
            <!-- Custom empty state -->
            <template #empty>
                <div class="text-center py-16 text-gray-500 dark:text-gray-400">
                    <p class="text-lg mb-4">
                        No custom fields defined for <strong>{{ selectedResource }}</strong> yet.
                    </p>
                    <Button label="Create First Field" icon="pi pi-plus" @click="openCreate" />
                </div>
            </template>
        </AdvancedDataTable>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Optional table styling overrides */
:deep(.p-datatable .p-datatable-thead > tr > th) {
    @apply bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 uppercase text-xs;
}
</style>
