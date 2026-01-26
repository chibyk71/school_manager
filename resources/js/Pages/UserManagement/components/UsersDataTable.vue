<!-- resources/js/Pages/Admin/Users/components/UsersDataTable.vue -->
<script setup lang="ts">
import { computed, markRaw, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import UserTypeBadge from './UserTypeBadge.vue'
import StatusToggle from './StatusToggle.vue'
import UserActionsDropdown from './UserActionsDropdown.vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import { ProgressSpinner } from 'primevue'

import type { BulkAction, ColumnDefinition } from '@/types/datatables'
import { useSelectedResources } from '@/helpers'

const confirm = useConfirm()
const toast = useToast()

// Global loading state for bulk operations & status toggle
const loading = ref(false)

// Inertia props – typed properly
interface Props {
    columns: ColumnDefinition<any>[]
    users: any[]
    totalRecords?: number
    globalFilterFields?: string[]
}
const props = defineProps<Props>()


const { selectedResourceIds, selectedResources: selectedUsers } = useSelectedResources()

// Enhance columns with custom renders (without mutation!)
const enhancedColumns = computed<ColumnDefinition<any>[]>(() => {
    const cols = Array.isArray(props.columns) ? [...props.columns] : []

    // Helper to find and replace or push
    const upsert = (field: string, newCol: Partial<ColumnDefinition<any>>) => {
        const index = cols.findIndex(c => c.field === field)
        if (index >= 0) {
            cols[index] = { ...cols[index], ...newCol }
        } else {
            cols.push({ field, header: field, ...newCol } as ColumnDefinition<any>)
        }
    }

    // 1. User Type Badge
        upsert('type', {
            header: 'Type',
            filterType: 'dropdown',
            render: (row: any) => ({
                component: markRaw(UserTypeBadge) as any,
                props: { type: row.type || 'student', size: 'small' }
            })
        })

    // 2. Status Toggle
        upsert('is_active', {
            header: 'Status',
            filterType: 'boolean',
            sortable: true,
            align: 'center',
            width: '120px',
            render: (row: any) => ({
                component: markRaw(StatusToggle) as any,
                props: { userId: row.id, active: row.is_active },
                on: {
                    'update:active': (value: boolean) => toggleUserStatus(row.id, value)
                }
            })
        })

    // 3. Full Name + Avatar
    upsert('full_name', {
        header: 'Full Name',
        sortable: true,
        render: (row: any) => ({
            template: 'div',
            class: 'flex items-center gap-3 min-w-0',
            children: [
                {
                    template: 'img',
                    src: row.avatar_url || '/assets/img/users/user-01.jpg',
                    class: 'w-10 h-10 rounded-full object-cover flex-shrink-0 border-2 border-gray-200 dark:border-gray-700',
                    props: { alt: `${row.full_name}'s avatar` }
                },
                {
                    template: 'div',
                    class: 'flex flex-col min-w-0',
                    children: [
                        { template: 'span', text: row.full_name, class: 'font-medium truncate' },
                        { template: 'span', text: row.email, class: 'text-xs text-gray-500 truncate' }
                    ]
                }
            ]
        })
    })

    // 4. Actions column – ensure it exists exactly once
        const hasActions = cols.some(c => c.field === 'actions')
        if (!hasActions) {
            cols.push({
                field: 'actions',
                header: 'Actions',
                sortable: false,
                filterable: false,
                frozen: true,
                align: 'right',
                width: '100px',
                bodyClass: 'text-right pr-4',
                render: (row: any) => ({
                    component: markRaw(UserActionsDropdown) as any,
                    props: { user: row }
                })
            })
        }

    return cols
})

// Toggle single user status
const toggleUserStatus = async (userId: string | number, active: boolean) => {
    loading.value = true
    try {
        await router.patch(route('api.users.toggle-status', userId), { active }, {
            preserveState: true,
            preserveScroll: true
        })

        toast.add({
            severity: active ? 'success' : 'warn',
            summary: active ? 'Activated' : 'Deactivated',
            detail: 'User status updated successfully',
            life: 4000
        })

        // Only reload users data
        router.reload({ only: ['users'] })
    } catch (err: any) {
        toast.add({
            severity: 'error',
            summary: 'Failed',
            detail: err.response?.data?.message || 'Could not update status',
            life: 6000
        })
    } finally {
        loading.value = false
    }
}

// Bulk actions – fully typed and injectable
const bulkActions = ref<BulkAction<any>[]>([
    {
        label: 'Activate',
        icon: 'pi pi-check-circle',
        severity: 'success',
        action: 'activate',
        handler: (user) => {}
    },
    {
        label: 'Deactivate',
        icon: 'pi pi-ban',
        severity: 'warn',
        action: 'deactivate',
        confirm: { message: 'Deactivate selected users?', severity: 'warn' },
        handler: () => {}
    },
    {
        label: 'Reset Password',
        icon: 'pi pi-key',
        severity: 'info',
        action: 'reset-password',
        confirm: { message: 'Send password reset emails to selected users?', severity: 'info' },
        handler: () => {}
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        action: 'delete',
        handler: () => {}
    }
])

// Central bulk action handler
const handleBulkAction = async (action: string, selectedRows: any[]) => {
    if (selectedRows.length === 0) return

    const ids = selectedRows.map(r => r.id)
    const config = bulkActions.value.find(a => a.action === action)

    // Special case: assign role (future modal)
    if (action === 'assign-role') {
        toast.add({ severity: 'info', summary: 'Coming soon', detail: 'Bulk role assignment' })
        return
    }

    // All other actions require confirmation
    confirm.require({
        message: config?.confirm?.message || 'Confirm this action',
        header: config?.confirm?.header || 'Confirm',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: config?.confirm?.acceptLabel || 'Confirm',
        acceptProps: { severity: config?.confirm?.severity || 'secondary' },
        rejectLabel: 'Cancel',
        accept: async () => {
            loading.value = true
            try {
                const routeMap: Record<string, string> = {
                    // TODO make sure this route are correct and active
                    activate: 'api.users.bulk-activate',
                    deactivate: 'api.users.bulk-deactivate',
                    'reset-password': 'api.users.bulk-reset-password',
                    delete: 'api.users.bulk-destroy'
                }

                const routeName = routeMap[action]
                if (!routeName) throw new Error('Unknown action')

                const axios = (await import('axios')).default
                if (action === 'delete') {
                    await axios.delete(route(routeName), { data: { ids } })
                } else {
                    await axios.patch(route(routeName), { ids })
                }

                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: `${selectedRows.length} user(s) processed`,
                    life: 5000
                })

                router.reload({ only: ['users'] })
            } catch (err: any) {
                toast.add({
                    severity: 'error',
                    summary: 'Failed',
                    detail: err.response?.data?.message || 'Action could not be completed',
                    life: 8000
                })
            } finally {
                loading.value = false
            }
        }
    })
}

</script>

<template>
    <div class="relative">
        <!-- Advanced DataTable with full power -->
        <AdvancedDataTable endpoint="/users" :columns="enhancedColumns" :initial-data="props.users"
            :total-records="props.totalRecords" :initial-params="{ with: 'profiles,roles,schools' }"
            :bulk-actions="bulkActions" @bulk-action="handleBulkAction" v-model:selected-rows="selectedUsers"
            selection-mode="multiple" :loading="loading" :global-filter-fields="globalFilterFields" />

        <!-- Full-screen overlay loader during bulk operations -->
        <transition name="fade">
            <div v-if="loading"
                class="absolute inset-0 bg-white/80 dark:bg-black/80 flex items-center justify-center z-50 rounded-lg backdrop-blur-sm"
                aria-live="polite" aria-busy="true">
                <div class="flex flex-col items-center gap-4">
                    <ProgressSpinner style="width: 50px; height: 50px" />
                    <p class="text-lg font-medium text-gray-700 dark:text-gray-300">
                        Processing...
                    </p>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
