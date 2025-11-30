<!-- resources/js/Pages/Admin/Users/components/UsersDataTable.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { useSelectedResources } from '@/helpers' // Your shared selection composable
import { ColumnDefinition, FilterModes } from '@/types/datatables'
import BulkActionsBar from './BulkActionsBar.vue'
import AdvancedDataTable from '@/composables/datatable/AdvancedDataTable.vue'
import { ProgressSpinner } from 'primevue'
import UserTypeBadge from './UserTypeBadge.vue'
import StatusToggle from './StatusToggle.vue'
import UserActionsDropdown from './UserActionsDropdown.vue'

const confirm = useConfirm()
const toast = useToast()

// Use your shared selection composable
const { selectedResources, selectedResourceIds } = useSelectedResources()
const selectedUsers = selectedResources // Alias for clarity in template

const loading = ref(false)

// Columns – aligned with your ColumnDefinitionHelper (extra fields like full_name, type)
const columns = ref<ColumnDefinition[]>([
    {
        field: 'full_name',
        header: 'Name',
        sortable: true,
        matchMode: FilterModes.CONTAINS,
        filterType: 'text',
        bodyClass: 'font-medium text-left',
        render: (row: any) => ({
            template: 'div',
            class: 'flex items-center gap-3',
            children: [
                {
                    template: 'img',
                    src: row.avatar_url || '/images/avatar-placeholder.png',
                    class: 'w-10 h-10 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700 shadow-sm'
                },
                {
                    template: 'div',
                    class: 'flex flex-col',
                    children: [
                        { template: 'span', text: row.full_name, class: 'font-medium text-gray-900 dark:text-white' },
                        { template: 'span', text: row.email, class: 'text-xs text-gray-500 dark:text-gray-400' }
                    ]
                }
            ]
        })
    },
    {
        field: 'email',
        header: 'Email',
        sortable: true,
        matchMode: FilterModes.CONTAINS,
        filterType: 'text'
    },
    {
        field: 'type',
        header: 'Type',
        sortable: false,
        filterType: 'dropdown',
        filterOptions: [
            { label: 'Student', value: 'student' },
            { label: 'Staff', value: 'staff' },
            { label: 'Parent/Guardian', value: 'guardian' }
        ],
        render: (row: any) => ({
            component: UserTypeBadge,
            props: { type: row.type, size: 'small' }
        })
    },
    {
        field: 'roles_display',
        header: 'Role(s)',
        sortable: false,
        filterType: 'multiselect',
        filterOptions: [], // Can fetch from API if needed
        render: (row: any) => ({
            template: 'div',
            class: 'flex flex-wrap gap-1.5',
            children: (row.roles || []).map((role: string) => ({
                template: 'span',
                text: role,
                class: 'px-2.5 py-1 text-xs font-medium rounded-full bg-primary/10 text-primary dark:bg-primary/20 dark:text-primary-200'
            }))
        })
    },
    {
        field: 'department_name',
        header: 'Department',
        sortable: true,
        filterType: 'text'
    },
    {
        field: 'is_active',
        header: 'Status',
        sortable: true,
        filterType: 'boolean',
        render: (row: any) => ({
            component: StatusToggle,
            props: { userId: row.id, active: row.is_active },
            on: { 'update:active': (value: boolean) => toggleUserStatus(row.id, value) }
        })
    },
    {
        field: 'actions',
        header: 'Actions',
        sortable: false,
        bodyClass: 'text-right pr-4',
        render: (row: any) => ({
            component: UserActionsDropdown,
            props: { user: row }
        })
    }
])

// Bulk actions handler
const handleBulkAction = async (action: string) => {
    if (!selectedUsers.value.length) return

    const ids = selectedResourceIds.value

    switch (action) {
        case 'deactivate':
            confirm.require({
                message: `Deactivate ${ids.length} user(s)?`,
                header: 'Confirm Deactivation',
                icon: 'pi pi-exclamation-triangle',
                acceptLabel: 'Deactivate',
                acceptProps: { severity: 'danger' },
                rejectProps: { severity: 'secondary' },
                accept: async () => {
                    loading.value = true
                    try {
                        await router.patch(route('api.users.bulk-deactivate'), { ids })
                        toast.add({ severity: 'success', summary: 'Success', detail: 'Users deactivated', life: 3000 })
                        router.reload({ only: ['users'] })
                    } catch (err) {
                        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to deactivate users', life: 5000 })
                    } finally {
                        loading.value = false
                    }
                }
            })
            break

        case 'activate':
            confirm.require({
                message: `Activate ${ids.length} user(s)?`,
                header: 'Confirm Activation',
                icon: 'pi pi-check-circle',
                acceptLabel: 'Activate',
                acceptProps: { severity: 'success' },
                accept: async () => {
                    loading.value = true
                    try {
                        await router.patch(route('api.users.bulk-activate'), { ids })
                        toast.add({ severity: 'success', summary: 'Success', detail: 'Users activated', life: 3000 })
                        router.reload({ only: ['users'] })
                    } catch (err) {
                        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to activate users', life: 5000 })
                    } finally {
                        loading.value = false
                    }
                }
            })
            break

        case 'reset-password':
            confirm.require({
                message: `Send password reset links to ${ids.length} user(s)?`,
                header: 'Bulk Password Reset',
                icon: 'pi pi-key',
                acceptLabel: 'Send',
                acceptProps: { severity: 'info' },
                accept: async () => {
                    try {
                        await router.post(route('api.users.bulk-reset-password'), { ids })
                        toast.add({ severity: 'info', summary: 'Sent', detail: 'Password reset emails sent', life: 5000 })
                    } catch (err) {
                        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to send resets', life: 5000 })
                    }
                }
            })
            break
    }

    // Clear selection after action
    selectedUsers.value = []
}

// Single user status toggle
const toggleUserStatus = async (userId: string, active: boolean) => {
    loading.value = true
    try {
        await router.patch(route('api.users.toggle-status', userId), { active })
        toast.add({
            severity: active ? 'success' : 'warn',
            summary: active ? 'Activated' : 'Deactivated',
            detail: 'User status updated',
            life: 3000
        })
        router.reload({ only: ['users'] })
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update status', life: 5000 })
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="relative space-y-4">
        <!-- Bulk Actions Bar -->
        <BulkActionsBar v-if="selectedUsers.length" :count="selectedUsers.length" @action="handleBulkAction"
            @clear="selectedUsers = []" />

        <!-- Main Table -->
        <AdvancedDataTable endpoint="/api/users" :columns="columns" :initial-params="{ with: ['profiles', 'roles'] }"
            v-model:selected-rows="selectedUsers" selection-mode="multiple" :loading="loading">
            <template #empty>
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <i class="pi pi-users text-7xl text-gray-300 dark:text-gray-600 mb-4" />
                    <p class="text-lg font-medium text-gray-600 dark:text-gray-400 mb-2">No users found</p>
                    <p class="text-sm text-gray-500 dark:text-gray-500">Try adjusting your search or filters</p>
                </div>
            </template>
        </AdvancedDataTable>

        <!-- Global Loading Overlay -->
        <div v-if="loading"
            class="absolute inset-0 bg-white/50 dark:bg-gray-900/50 flex items-center justify-center z-50 rounded-xl">
            <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" animationDuration=".8s" />
        </div>
    </div>
</template>

<style scoped>
/* Custom overrides from style.css/app.css */
:deep(.p-datatable) {
    @apply overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700;
}

:deep(.p-datatable-thead > tr > th) {
    @apply bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium text-sm py-3 px-4;
}

:deep(.p-datatable-tbody > tr > td) {
    @apply py-4 px-4 text-sm text-gray-900 dark:text-white border-t border-gray-200 dark:border-gray-700;
}

:deep(.p-datatable-tbody > tr:hover) {
    @apply bg-gray-50 dark:bg-gray-800/50 transition-colors;
}
</style>