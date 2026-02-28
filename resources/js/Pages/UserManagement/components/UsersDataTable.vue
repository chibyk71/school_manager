<!--
  resources/js/Pages/Admin/Users/components/UsersDataTable.vue

  Main reusable DataTable for the Admin → Users index page.

  Features / Problems Solved:
  ────────────────────────────────────────────────────────────────
  • Displays users with enhanced columns: avatar + name + email, type badge, status toggle
  • Supports server-side lazy loading via AdvancedDataTable
  • Single-row status toggle (PATCH to api.users.toggle-status)
  • Bulk actions: activate / deactivate / reset-password / delete
    → Uses standard Laravel resource routes with { ids: [...] } payload
    → No special "bulk-xxx" routes needed (matches your backend design)
  • Global loading overlay during any long-running operation
  • Toast notifications for success/error states
  • Fully typed with your datatables.ts interfaces
  • No per-row actions dropdown (replaced by table-level action menu via TableAction[])
  • Responsive, accessible, dark-mode ready

  Integration:
  ─────────────
  • Expects Inertia props: users, totalRecords, columns, globalFilterFields
  • Uses AdvancedDataTable as the rendering engine
  • Communicates with backend via Inertia router + axios for bulk non-GET requests
  • Designed to work with ProfileController bulk methods (destroy, toggle status, etc.)

  Future extensions:
  • Add "Restore" bulk action when trashed view is implemented
  • Add "Assign Role" bulk modal
  • Add export button / CSV action
-->

<script setup lang="ts">
import { computed, markRaw, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { ProgressSpinner } from 'primevue'

import UserTypeBadge from './UserTypeBadge.vue'
import StatusToggle from './StatusToggle.vue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'

import type { ColumnDefinition, TableAction, BulkAction } from '@/types/datatables'
import { useSelectedResources } from '@/helpers'
import axios from 'axios'

// ────────────────────────────────────────────────
// Props & Composition
// ────────────────────────────────────────────────

const props = defineProps<{
    columns: ColumnDefinition<any>[]
    users: any[]
    totalRecords?: number
    globalFilterFields?: string[]
}>()

const toast = useToast()
const loading = ref(false)

const { selectedResourceIds, selectedResources: selectedUsers } = useSelectedResources()

// ────────────────────────────────────────────────
// Enhanced Columns (immutable, clean upsert pattern)
// ────────────────────────────────────────────────

const enhancedColumns = computed<ColumnDefinition<any>[]>(() => {
    const cols = [...(Array.isArray(props.columns) ? props.columns : [])]

    const upsert = (field: string, overrides: Partial<ColumnDefinition<any>>) => {
        const idx = cols.findIndex(c => c.field === field)
        if (idx !== -1) {
            cols[idx] = { ...cols[idx], ...overrides }
        } else {
            cols.push({ field, header: field.charAt(0).toUpperCase() + field.slice(1), ...overrides } as ColumnDefinition<any>)
        }
    }

    upsert('full_name', {
        header: 'Name',
        sortable: true,
        filterable: true,
        filterType: 'text',
        render: row => ({
            template: 'div',
            class: 'flex items-center gap-3',
            children: [
                {
                    template: 'img',
                    src: row.avatar_url || '/assets/img/users/default-avatar.png',
                    class: 'w-10 h-10 rounded-full object-cover border border-gray-200 dark:border-gray-600',
                    props: { alt: `${row.full_name || 'User'} avatar` }
                },
                {
                    template: 'div',
                    class: 'min-w-0',
                    children: [
                        { template: 'div', text: row.full_name || '—', class: 'font-medium truncate' },
                        { template: 'div', text: row.email || '—', class: 'text-sm text-gray-500 dark:text-gray-400 truncate' }
                    ]
                }
            ]
        })
    })

    upsert('type', {
        header: 'Type',
        filterType: 'dropdown',
        render: row => ({
            component: markRaw(UserTypeBadge) as any,
            props: { type: row.type || 'student', size: 'small' }
        })
    })

    upsert('is_active', {
        header: 'Status',
        filterType: 'boolean',
        sortable: true,
        align: 'center',
        width: '100px',
        render: row => ({
            component: markRaw(StatusToggle) as any,
            props: { userId: row.id, active: !!row.is_active }
        })
    })

    return cols
})

// ────────────────────────────────────────────────
// Row Actions (passed to AdvancedDataTable as TableAction[])
// ────────────────────────────────────────────────

const rowActions = computed<TableAction<any>[]>(() => [
    {
        label: row => row.is_active ? 'Deactivate' : 'Activate',
        icon: row => row.is_active ? 'pi pi-ban' : 'pi pi-check-circle',
        severity: row => row.is_active ? 'warn' : 'success',
        handler: async row => {
            loading.value = true
            try {
                const newStatus = !row.is_active
                await axios.patch(route('profiles.toggle-status'), {
                    ids: [row.id],
                    active: newStatus
                }).then((response) => {
                    console.log(response.data);
                    toast.add({
                        severity: newStatus ? 'success' : 'warn',
                        summary: newStatus ? 'Activated' : 'Deactivated',
                        detail: `User is now ${newStatus ? 'active' : 'inactive'}.`,
                        life: 4000
                    })
                    router.reload({ only: ['users'] })
                })
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
    },
    {
        label: 'View Details',
        icon: 'pi pi-eye',
        severity: 'info',
        handler: row => {
            // TODO: open details modal when implemented
            toast.add({ severity: 'info', summary: 'View', detail: `Viewing ${row.full_name} details...` })
        }
    },
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        severity: 'primary',
        handler: row => {
            // TODO: open edit modal (admin version)
            toast.add({ severity: 'info', summary: 'Edit', detail: `Editing ${row.full_name}...` })
        }
    },
    {
        label: 'Reset Password',
        icon: 'pi pi-key',
        severity: 'help',
        handler: row => {
            // TODO: open reset password modal
            toast.add({ severity: 'info', summary: 'Reset', detail: `Resetting password for ${row.full_name}...` })
        }
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        confirm: {
            message: row => `Delete user ${row.full_name}? This action cannot be undone.`,
            header: 'Confirm Deletion',
            icon: 'pi pi-exclamation-triangle',
            acceptClass: 'p-button-danger'
        },
        handler: async row => {
            // TODO: implement single delete (or defer to bulk logic)
        }
    }
])

// ────────────────────────────────────────────────
// Bulk Actions
// ────────────────────────────────────────────────

const bulkActions = computed<BulkAction<any>[]>(() => [
    {
        label: 'Activate Selected',
        icon: 'pi pi-check-circle',
        severity: 'success',
        handler: selected => handleBulkStatusChange(selected, true)
    },
    {
        label: 'Deactivate Selected',
        icon: 'pi pi-ban',
        severity: 'warn',
        confirm: { message: 'Deactivate selected users? They will lose access.' },
        handler: selected => handleBulkStatusChange(selected, false)
    },
    {
        label: 'Reset Passwords',
        icon: 'pi pi-key',
        severity: 'info',
        confirm: { message: 'Send password reset links to selected users?' },
        handler: selected => handleBulkResetPassword(selected)
    },
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        confirm: { message: 'Permanently delete selected users? This cannot be undone.' },
        handler: selected => handleBulkDelete(selected)
    }
])

// ────────────────────────────────────────────────
// Bulk Handlers (aligned with your backend: DELETE /profiles { ids: [...] })
// ────────────────────────────────────────────────

const handleBulkStatusChange = async (selected: any[], activate: boolean) => {
    if (!selected.length) return
    const ids = selected.map(u => u.id)

    loading.value = true
    try {
        await router.patch(route('profiles.toggle-status'), { ids, active: activate }, {
            preserveScroll: true
        })
        toast.add({
            severity: activate ? 'success' : 'warn',
            summary: activate ? 'Activated' : 'Deactivated',
            detail: `${ids.length} user(s) updated.`,
            life: 5000
        })
        router.reload({ only: ['users'] })
    } catch (err: any) {
        toast.add({
            severity: 'error',
            summary: 'Failed',
            detail: err.response?.data?.message || 'Bulk status update failed.',
            life: 7000
        })
    } finally {
        loading.value = false
    }
}

const handleBulkResetPassword = async (selected: any[]) => {
    if (!selected.length) return
    const ids = selected.map(u => u.id)

    loading.value = true
    try {
        await router.post(route('profiles.password.reset'), { ids }, {
            preserveScroll: true
        })
        toast.add({
            severity: 'success',
            summary: 'Reset Initiated',
            detail: `Password reset emails sent to ${ids.length} user(s).`,
            life: 5000
        })
        router.reload({ only: ['users'] })
    } catch (err: any) {
        toast.add({
            severity: 'error',
            summary: 'Failed',
            detail: err.response?.data?.message || 'Could not reset passwords.',
            life: 7000
        })
    } finally {
        loading.value = false
    }
}

const handleBulkDelete = async (selected: any[]) => {
    if (!selected.length) return
    const ids = selected.map(u => u.id)

    loading.value = true
    try {
        await router.delete(route('profiles.destroy'), {
            data: { ids },
            preserveScroll: true
        })
        toast.add({
            severity: 'success',
            summary: 'Deleted',
            detail: `${ids.length} user(s) deleted successfully.`,
            life: 5000
        })
        router.reload({ only: ['users'] })
    } catch (err: any) {
        toast.add({
            severity: 'error',
            summary: 'Failed',
            detail: err.response?.data?.message || 'Could not delete users.',
            life: 7000
        })
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="relative min-h-[400px]">
        <AdvancedDataTable endpoint="/users" :columns="enhancedColumns" :initial-data="props.users"
            :total-records="props.totalRecords" :initial-params="{ with: 'profiles,roles,schools' }"
            :row-actions="rowActions" :bulk-actions="bulkActions" v-model:selected-rows="selectedUsers"
            selection-mode="multiple" :loading="loading" :global-filter-fields="props.globalFilterFields" lazy paginator
            :rows="20" responsive-layout="scroll" />

        <!-- Global processing overlay -->
        <transition name="fade">
            <div v-if="loading"
                class="absolute inset-0 bg-white/70 dark:bg-gray-900/70 backdrop-blur-sm flex items-center justify-center z-40 rounded-xl"
                aria-live="polite" aria-busy="true">
                <div class="flex flex-col items-center gap-5">
                    <ProgressSpinner style="width: 60px; height: 60px" stroke-width="4" />
                    <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">
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
    transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
