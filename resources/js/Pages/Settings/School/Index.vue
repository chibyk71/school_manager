<!-- resources/js/Pages/Settings/Schools/Index.vue -->

<!--
Comprehensive File Overview: Schools Management Page (Index.vue) – Updated Actions

This updated version refactors all row and bulk actions to exclusively use the reusable composables:
- useDeleteResource() → for soft-delete and force-delete (single + bulk via same function)
- useRestoreResource() → for restore (single + bulk via same function)

Key Changes & Improvements:
- Removed direct router.post/delete calls in bulk handlers
- Unified single/bulk delete & restore logic through composables → consistent confirmation dialogs, toasts, error handling
- Added onSuccess callbacks to refresh table data after any destructive/action (partial reload)
- Force delete now uses deleteResource with { force: true } option
- Separate bulk status toggle actions (activate/deactivate) kept as lightweight router.post (no confirmation needed via composable)
- Dynamic visibility & labels preserved
- Better icons and confirmation messages
- Permission checks aligned (schools.delete, schools.force-delete, schools.restore, schools.update, schools.create)
- Tooltip added for disabled status toggle on trashed schools

Problems Solved:
- Code duplication eliminated
- Consistent UX across all delete/restore operations (same dialogs, toasts, loading states)
- Easier maintenance – changes in confirmation/toast logic only in composables
- Proper bulk support without custom handlers
- Clear separation: destructive actions (delete/restore) use composables; non-destructive (status toggle) use direct calls

Dependencies:
- useDeleteResource, useRestoreResource, useTrashedToggle, usePermissions
-->

<script setup lang="ts">
import { computed, markRaw } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue';
import { usePermissions } from '@/composables/usePermissions';
import ToggleSwitch from 'primevue/toggleswitch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { BulkAction, ColumnDefinition, TableAction } from '@/types/datatables';
import { useRestoreResource } from '@/composables/useRestoreResource';
import { useTrashedToggle } from '@/composables/useTrashedToggle';
import { useDeleteResource } from '@/composables/useDelete';

type School = {
    id: number;
    name: string;
    slug: string;
    code: string;
    email: string;
    phone: string | null;
    logo_url: string | null;
    is_active: boolean;
    deleted_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};

const props = defineProps<{
    data: School[];
    columns: ColumnDefinition<School>[];
    globalFilterables: string[];
    totalRecords: number
}>();

const toast = useToast();
const { deleteResource } = useDeleteResource();
const { restoreResource } = useRestoreResource();
const { showTrashed } = useTrashedToggle();
const { hasPermission } = usePermissions();

// Helper: refresh table after action
const refreshTable = () => {
    router.reload({ only: ['data', 'totalRecords'] });
};

// Enhanced columns (logo preview + status toggle)
const enhancedColumns = computed<ColumnDefinition<School>[]>(() => {
    return props.columns.map((col) => {
        if (col.field === 'logo_url') {
            return {
                ...col,
                render: (row) => ({
                    template: 'img',
                    src: row.logo_url || '/images/default-school-logo.png',
                    class: 'w-12 h-12 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700',
                    alt: `${row.name} logo`,
                }),
            };
        }
        if (col.field === 'is_active') {
            return {
                ...col,
                render: (row) => ({
                    component: markRaw(ToggleSwitch) as any,
                    props: {
                        modelValue: row.is_active,
                        disabled: !!row.deleted_at || !hasPermission('schools.update'),
                        pt: { root: { 'data-pc-tooltip': !!row.deleted_at ? 'Cannot toggle status on trashed schools' : undefined } }
                    },
                    on: {
                        'update:modelValue': (newValue: boolean) => toggleStatus(row, newValue),
                    },
                }),
            };
        }
        return col;
    });
});

// Open create/edit form
const openSchoolForm = (school?: School) => {
    const canEdit = school ? hasPermission('schools.update') : hasPermission('schools.create');
    if (!canEdit) {
        toast.add({ severity: 'warn', summary: 'Unauthorized', detail: 'Insufficient permissions.', life: 4000 });
        return;
    }
    router.visit(school ? route('settings.schools.edit', school.id) : route('settings.schools.create'));
};

// Optimistic status toggle
const toggleStatus = async (school: School, newValue: boolean) => {
    const original = school.is_active;
    school.is_active = newValue;
    try {
        router.patch(route('schools.update', school.id), { is_active: newValue }, { preserveScroll: true });
        toast.add({ severity: 'success', summary: 'Updated', detail: 'School status changed.', life: 3000 });
    } catch {
        school.is_active = original;
        toast.add({ severity: 'error', summary: 'Failed', detail: 'Could not update status.', life: 5000 });
    }
};

/**
 * Row Actions – Now fully powered by useDeleteResource & useRestoreResource composables
 */
const schoolActions: TableAction<School>[] = [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        severity: 'success',
        handler: (row) => openSchoolForm(row),
        show: () => hasPermission('schools.update'),
    },
    {
        label: 'Restore',
        icon: 'pi pi-undo',
        severity: 'info',
        handler: (row) => restoreResource('schools', [row.id], { onSuccess: refreshTable }),
        show: (row) => !!row.deleted_at && hasPermission('schools.restore') && showTrashed.value,
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (row) => deleteResource('schools', [row.id], { onSuccess: refreshTable }),
        show: (row) => !row.deleted_at && hasPermission('schools.delete'),
    },
    {
        label: 'Force Delete',
        icon: 'pi pi-times-circle',
        severity: 'danger',
        handler: (row) => deleteResource('schools', [row.id], { force: true, onSuccess: refreshTable }),
        show: (row) => !!row.deleted_at && hasPermission('schools.force-delete') && showTrashed.value,
    },
    {
        label: (row) => row.is_active ? 'Deactivate' : 'Activate',
        icon: (row) => row.is_active ? 'pi pi-power-off' : 'pi pi-check-circle',
        severity: (row) => row.is_active ? 'warning' : 'success',
        handler: (row) => toggleStatus(row, !row.is_active),
        show: () => hasPermission('schools.update'),
        disabled: (row) => !!row.deleted_at,
    },
];

/**
 * Bulk Actions – Now fully powered by useDeleteResource & useRestoreResource composables
 */
const schoolBulkActions: BulkAction<School>[] = [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (selected) => deleteResource('schools', selected.map(s => s.id), { onSuccess: refreshTable }),
        visible: (selected) => selected.every(s => !s.deleted_at) && hasPermission('schools.delete'),
    },
    {
        label: 'Restore Selected',
        icon: 'pi pi-undo',
        severity: 'info',
        handler: (selected) => restoreResource('schools', selected.map(s => s.id), { onSuccess: refreshTable }),
        visible: (selected) => selected.every(s => !!s.deleted_at) && hasPermission('schools.restore'),
    },
    {
        label: 'Force Delete Selected',
        icon: 'pi pi-times-circle',
        severity: 'danger',
        handler: (selected) => deleteResource('schools', selected.map(s => s.id), { force: true, onSuccess: refreshTable }),
        visible: (selected) => selected.every(s => !!s.deleted_at) && hasPermission('schools.force-delete'),
    },
    {
        label: 'Activate Selected',
        icon: 'pi pi-check-circle',
        severity: 'success',
        handler: async (selected) => {
            await router.post(route('settings.schools.bulk-toggle'), { ids: selected.map(s => s.id), is_active: true }, { preserveScroll: true });
            refreshTable();
        },
        visible: (selected) => selected.some(s => !s.is_active) && hasPermission('schools.update'),
    },
    {
        label: 'Deactivate Selected',
        icon: 'pi pi-power-off',
        severity: 'warning',
        handler: async (selected) => {
            router.post(route('schools.bulk-toggle'), { ids: selected.map(s => s.id), is_active: false }, { preserveScroll: true });
            refreshTable();
        },
        visible: (selected) => selected.some(s => s.is_active) && hasPermission('schools.update'),
    },
];
</script>

<template>
    <AuthenticatedLayout title="Schools Management"
        :crumb="[{ label: 'Dashboard', url: route('dashboard') }, { label: 'Schools' }]" :buttons="hasPermission('schools.create') ? [{
            label: 'Add New School',
            icon: 'pi pi-plus',
            class: 'p-button-success',
            onClick: () => openSchoolForm()
        }] : []">
        <div class="space-y-6">
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    Manage your organization's schools. Super admins see all schools; school owners see only their
                    assigned ones.
                </p>
            </div>

            <AdvancedDataTable endpoint="settings/schools" :columns="enhancedColumns" :bulk-actions="schoolBulkActions"
                :initial-data="props.data" :total-records="props.totalRecords"
                :global-filter-fields="props.globalFilterables" :actions="schoolActions" />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped lang="postcss">
:deep(.p-datatable .p-datatable-header) {
    @apply bg-primary-600 text-white rounded-t-lg;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
    @apply bg-primary-50 dark:bg-gray-800 text-primary-900 dark:text-primary-100 font-semibold;
}

:deep(.p-button.p-button-sm) {
    @apply h-8 w-8;
}
</style>
