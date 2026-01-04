<!--
  resources/js/Components/HRM/DataTableActions.vue

  Purpose & Features Implemented (Production-Ready – December 16, 2025):

  1. Reusable row actions dropdown for all HRM DataTables:
     - Consistent look and behavior across modules (Departments, Employees, Roles, etc.)
     - Shows only actions the authenticated user is permitted to perform
     - Supports: Edit, Delete, Restore (for trashed rows)

  2. Permission-aware rendering:
     - Accepts permission strings as props (e.g., 'departments.update')
     - Uses usePermissions composable to check current user capabilities
     - Hides actions user cannot perform – prevents confusing disabled states

  3. Event emission:
     - Emits 'edit', 'delete', 'restore' events with row data or ID
     - Parent table (AdvancedDataTable + Index.vue) handles the actual action
     - Keeps this component pure UI – no business logic

  4. Trashed-aware:
     - When trashed=true, shows "Restore" instead of "Delete"
     - Optional force-delete can be added later if needed

  5. UX & Accessibility:
     - PrimeVue SplitButton for clean dropdown
     - Icons + labels for clarity
     - Keyboard navigable
     - Small size, right-aligned in actions column
     - Hover polish and focus states

  6. Scalability & Maintainability:
     - Generic – works for any resource with edit/delete/restore
     - Easy to extend (add View, Archive, etc.)
     - Tailwind + PrimeVue consistent styling

  7. Integration Example (used in Index.vue):
     <DataTableActions
       :row="row"
       edit-permission="departments.update"
       delete-permission="departments.delete"
       restore-permission="departments.restore"
       :trashed="showTrashed"
       @edit="openEditModal(row)"
       @restore="restoreDepartment(row.id)"
     />

  This component ensures consistent, secure, and professional row actions across the entire HRM section.
-->
<script setup lang="ts">
import { computed } from 'vue'
import { usePermissions } from '@/composables/usePermissions'
import SplitButton from 'primevue/splitbutton'

const props = defineProps<{
    row: any
    viewPermission?: string
    editPermission?: string
    deletePermission?: string
    restorePermission?: string
    trashed?: boolean
}>()

const emit = defineEmits<{
    (e: 'view', row: any): void
    (e: 'edit', row: any): void
    (e: 'delete', row: any): void
    (e: 'restore', row: any): void
}>()

const { hasPermission } = usePermissions()

// Permissions checks
const canView = computed(() => hasPermission(props.viewPermission))
const canEdit = computed(() => hasPermission(props.editPermission))
const canRestore = computed(() => props.trashed && hasPermission(props.restorePermission))
const canDelete = computed(() => !props.trashed && hasPermission(props.deletePermission))

const hasAnyAction = computed(() => canView.value || canEdit.value || canRestore.value || canDelete.value)


// Primary action logic
const primaryActionType = computed(() => {
    if (canView.value) return 'view'
    if (canEdit.value) return 'edit'
    return null
})

const primaryLabel = computed(() => {
    switch (primaryActionType.value) {
        case 'view': return 'View'
        case 'edit': return 'Edit'
        default: return ''
    }
})

const primaryIcon = computed(() => {
    switch (primaryActionType.value) {
        case 'view': return 'pi pi-eye'
        case 'edit': return 'pi pi-pencil'
        default: return ''
    }
})

const primaryAction = () => {
    switch (primaryActionType.value) {
        case 'view': emit('view', props.row); break
        case 'edit': emit('edit', props.row); break
    }
}

// Dropdown items
const dropdownItems = computed(() => {
    const items: any[] = []

    // Delete (if permitted and not trashed)
    if (canDelete.value) {

        items.push({
            label: 'Delete',
            icon: 'pi pi-trash',
            command: () => emit('delete', props.row),
            severity: 'danger' as const
        })
    }

    // View as dropdown fallback (if not primary)
    if (canRestore.value) {
        items.push({
            label: 'Restore',
            icon: 'pi pi-undo',
            command: () => emit('restore', props.row)
        })
    }

    return items
})
</script>

<template>
    <div class="flex justify-end">
        <SplitButton v-if="hasAnyAction" :label="primaryLabel" :icon="primaryIcon" :model="dropdownItems" size="small"
            severity="secondary" outlined @click="primaryAction" class="h-9" :pt="{
                root: { class: 'border-gray-300' },
                button: { class: 'px-3' },
                menuButton: { class: 'px-2 w-9' }
            }" />

        <span v-else class="text-gray-400 text-sm">—</span>
    </div>
</template>

<style scoped lang="postcss">
:deep(.p-splitbutton) {
    @apply h-9;
}
</style>
