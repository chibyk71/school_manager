<!-- resources/js/Pages/Settings/Schools/Index.vue -->
<!--
Schools Management Index Page (Index.vue) v2.0 – Production-Ready with Multi-Address Support

Purpose & Context:
------------------
This is the **central listing page** for managing all schools (tenants/branches) in the multi-tenant SaaS.
It provides a powerful, searchable, sortable data table with full row/bulk actions.

Key Features & Improvements (v2.0):
----------------------------------
- **Full integration with multi-address workflow**: Edit action now links to CreateEdit.vue (full-page form)
  which uses AddressManager.vue for complete address CRUD (multiple addresses + primary designation).
- **Consistent action handling** via reusable composables (useDeleteResource, useRestoreResource).
- **Permission-aware UI** using usePermissions composable.
- **Trashed toggle** support via useTrashedToggle.
- **Optimistic status toggle** with rollback on failure.
- **Logo preview** in table with fallback.
- **Responsive design** and dark mode support.
- **Inline status toggle** disabled on trashed records with tooltip explanation.
- **Bulk actions** for delete/restore/force-delete/activate/deactivate.
- **Create button** links to full-page create form.

Problems Solved:
----------------
- Unified delete/restore UX across single & bulk operations.
- Clear permission gating prevents unauthorized actions.
- Trashed records properly handled (no status toggle, different actions).
- Direct navigation to full-page edit form (replaces old modal).
- Table refresh after any mutation (partial reload via Inertia).

Dependencies:
-------------
- AdvancedDataTable.vue
- useDeleteResource, useRestoreResource, useTrashedToggle, usePermissions
- AddressManager.vue (indirectly via edit page)
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
    totalRecords: number;
}>();

const toast = useToast();
const { deleteResource } = useDeleteResource();
const { restoreResource } = useRestoreResource();
const { showTrashed } = useTrashedToggle();
const { hasPermission } = usePermissions();

// Partial reload after mutations
const refreshTable = () => {
    router.reload({ only: ['data', 'totalRecords'] });
};

// Enhanced columns: logo preview + inline status toggle
const enhancedColumns = computed<ColumnDefinition<School>[]>(() => {
    return props.columns.map((col) => {
        // Logo column → render as rounded image with fallback
        if (col.field === 'logo_url') {
            return {
                ...col,
                render: (row) => ({
                    template: 'img',
                    src: row.logo_url || '/images/default-school-logo.png',
                    class: 'w-12 h-12 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700 shadow-sm',
                    alt: `${row.name} logo`,
                }),
            };
        }

        // Status column → inline toggle switch
        if (col.field === 'is_active') {
            return {
                ...col,
                render: (row) => ({
                    component: markRaw(ToggleSwitch) as any,
                    props: {
                        modelValue: row.is_active,
                        disabled: !!row.deleted_at || !hasPermission('schools.update'),
                    },
                    on: {
                        'update:modelValue': (newValue: boolean) => toggleStatus(row, newValue),
                    },
                    // Tooltip for disabled state on trashed records
                    pt: {
                        root: {
                            'data-pc-tooltip': !!row.deleted_at ? 'Cannot toggle status on trashed schools' : undefined,
                        },
                    },
                }),
            };
        }

        return col;
    });
});

// Navigate to full-page create/edit form
const openSchoolForm = (school?: School) => {
    const canEdit = school ? hasPermission('schools.update') : hasPermission('schools.create');
    if (!canEdit) {
        toast.add({
            severity: 'warn',
            summary: 'Unauthorized',
            detail: 'You do not have permission to perform this action.',
            life: 4000,
        });
        return;
    }

    const routeName = school ? 'settings.schools.edit' : 'settings.schools.create';
    const params = school ? { school: school.id } : {};
    router.visit(route(routeName, params));
};

// Optimistic inline status toggle with rollback on error
const toggleStatus = async (school: School, newValue: boolean) => {
    const original = school.is_active;
    school.is_active = newValue; // Optimistic UI update

    try {
        await router.patch(
            route('schools.update', school.id),
            { is_active: newValue },
            { preserveScroll: true }
        );

        toast.add({
            severity: 'success',
            summary: 'Status Updated',
            detail: `School ${newValue ? 'activated' : 'deactivated'}.`,
            life: 3000,
        });
    } catch {
        school.is_active = original; // Rollback on failure
        toast.add({
            severity: 'error',
            summary: 'Update Failed',
            detail: 'Could not change school status.',
            life: 5000,
        });
    }
};

/**
 * Row Actions – Powered by composables for consistent dialogs/toasts
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
        label: (row) => (row.is_active ? 'Deactivate' : 'Activate'),
        icon: (row) => (row.is_active ? 'pi pi-power-off' : 'pi pi-check-circle'),
        severity: (row) => (row.is_active ? 'warning' : 'success'),
        handler: (row) => toggleStatus(row, !row.is_active),
        show: () => hasPermission('schools.update'),
        disabled: (row) => !!row.deleted_at,
    },
];

/**
 * Bulk Actions – Consistent UX via composables where possible
 */
const schoolBulkActions: BulkAction<School>[] = [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (selected) => deleteResource('schools', selected.map((s) => s.id), { onSuccess: refreshTable }),
        visible: (selected) => selected.every((s) => !s.deleted_at) && hasPermission('schools.delete'),
    },
    {
        label: 'Restore Selected',
        icon: 'pi pi-undo',
        severity: 'info',
        handler: (selected) => restoreResource('schools', selected.map((s) => s.id), { onSuccess: refreshTable }),
        visible: (selected) => selected.every((s) => !!s.deleted_at) && hasPermission('schools.restore'),
    },
    {
        label: 'Force Delete Selected',
        icon: 'pi pi-times-circle',
        severity: 'danger',
        handler: (selected) => deleteResource('schools', selected.map((s) => s.id), { force: true, onSuccess: refreshTable }),
        visible: (selected) => selected.every((s) => !!s.deleted_at) && hasPermission('schools.force-delete'),
    },
    {
        label: 'Activate Selected',
        icon: 'pi pi-check-circle',
        severity: 'success',
        handler: async (selected) => {
            await router.post(
                route('settings.schools.bulk-toggle'),
                { ids: selected.map((s) => s.id), is_active: true },
                { preserveScroll: true }
            );
            refreshTable();
        },
        visible: (selected) => selected.some((s) => !s.is_active) && hasPermission('schools.update'),
    },
    {
        label: 'Deactivate Selected',
        icon: 'pi pi-power-off',
        severity: 'warning',
        handler: async (selected) => {
            await router.post(
                route('settings.schools.bulk-toggle'),
                { ids: selected.map((s) => s.id), is_active: false },
                { preserveScroll: true }
            );
            refreshTable();
        },
        visible: (selected) => selected.some((s) => s.is_active) && hasPermission('schools.update'),
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
            <!-- Info banner -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    Manage your organization's schools. Super admins see all schools; school owners see only their
                    assigned ones.
                </p>
            </div>

            <!-- Main data table -->
            <AdvancedDataTable endpoint="settings/schools" :columns="enhancedColumns" :bulk-actions="schoolBulkActions"
                :initial-data="props.data" :total-records="props.totalRecords"
                :global-filter-fields="props.globalFilterables" :actions="schoolActions" />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped lang="postcss">
/* Table header styling */
:deep(.p-datatable .p-datatable-header) {
    @apply bg-primary-600 text-white rounded-t-lg;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
    @apply bg-primary-50 dark:bg-gray-800 text-primary-900 dark:text-primary-100 font-semibold;
}

/* Small action buttons */
:deep(.p-button.p-button-sm) {
    @apply h-8 w-8;
}
</style>
