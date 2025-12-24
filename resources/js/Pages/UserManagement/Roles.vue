<!-- resources/js/Pages/Settings/School/Roles/Index.vue -->
<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue';
import { useToast } from 'primevue/usetoast';
import { modals } from '@/helpers';
import { useDeleteResource, useSelectedResources } from '@/helpers';
import axios from 'axios';
import type { BulkAction, ColumnDefinition, TableAction } from '@/types/datatables';
import { computed, markRaw } from 'vue';
import DepartmentBadges from './components/roles/DepartmentBadges.vue';
import RoleActionsDropdown from './components/roles/RoleActionsDropdown.vue';

// Props from Inertia (SSR)
const props = defineProps<{
    columns: ColumnDefinition<any>[];
    data: any[];
    totalRecords: number
    globalFilters: string[]
}>();

const toast = useToast();

// Bulk selection
const { selectedResources: selectedRoles, selectedResourceIds } = useSelectedResources();

// Delete handler
const { deleteResource } = useDeleteResource();

// Open Create Modal
const openCreateModal = async () => {
    // const { data } = await axios.get(route('admin.roles.create-data'));
    modals.open('create-role', { existingRoles: '' });
};

// Open Edit Modal
const openEditModal = async (role: any) => {
    modals.open('create-role', { role: role });
};

// Single delete
const deleteRole = (role: any) => {
    deleteResource('roles', [role.id]);
};

// Bulk delete
const handleBulkDelete = () => {
    if (selectedResourceIds.value.length === 0) {
        toast.add({ severity: 'warn', summary: 'No Selection', detail: 'Please select at least one role.' });
        return;
    }
    deleteResource('roles', selectedResourceIds.value);
};

// Bulk actions config
const bulkActions: BulkAction[] = [
    {
        label: 'Delete Selected',
        action: 'delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        visible: () => selectedRoles.value.length > 0,
    },
];

// Enhance columns exactly like UsersDataTable.vue
const enhancedColumns = computed<ColumnDefinition<any>[]>(() => {
    const cols = [...props.columns]; // Clone to avoid mutation

    const upsert = (field: string, newCol: Partial<ColumnDefinition<any>>) => {
        const index = cols.findIndex(c => c.field === field);
        if (index >= 0) {
            cols[index] = { ...cols[index], ...newCol };
        } else {
            cols.push({ field, header: field, ...newCol } as ColumnDefinition<any>);
        }
    };

    // 1. Users Count
    upsert('users_count', {
        sortable: true,
        align: 'center',
        width: '100px',
        render: (row: any) => ({
            template: 'span',
            text: row.users_count ?? '0',
            class: 'font-medium text-gray-900 dark:text-gray-100 hello',
        }),
    });

    // 2. Permissions Count
    upsert('permissions_count', {
        header: 'Permissions',
        sortable: true,
        align: 'center',
        width: '120px',
        render: (row: any) => ({
            template: 'span',
            text: row.permissions_count ?? 0,
            class: 'font-medium text-gray-900 dark:text-gray-100',
        }),
    });

    // 3. Departments – Badge Display
    upsert('departments', {
        header: 'Departments',
        filterable: true,
        filterType: 'dropdown',
        render: (row: any) => ({
            component: markRaw(DepartmentBadges) as any,
            props: { departments: row.departments || [] },
        }),
    });

    upsert('name', {
        hidden: true
    });

    upsert('school', {
        formatter: (row) => {
            return row.name
        }
    })

    return cols;
});

const roleActions: TableAction<any>[] = [
    {
        label: 'Edit Role',
        icon: 'pi pi-pencil',
        severity: 'secondary',
        handler: (role) => openEditModal(role),
    },
    {
        label: 'Manage Permissions',
        icon: 'pi pi-shield',
        severity: 'info',
        handler: (role) => router.visit(route('admin.roles.permissions.manage', role.id)),
    },
    {
        label: 'Delete Role',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: (role) => deleteRole([role.id]),
    },
];
</script>

<template>

    <Head title="Roles Management" />

    <AuthenticatedLayout title="Roles Management" :crumb="[{ label: 'User Management' }, { label: 'Roles' }]" :buttons="[
        {
            label: 'Create New Role',
            icon: 'pi pi-plus',
            severity: 'primary',
            onClick: openCreateModal,
        }
    ]">
        <div class="space-y-6">
            <AdvancedDataTable :actions="roleActions" endpoint="/admin/roles" :columns="enhancedColumns" :initial-data="data"
                :bulk-actions="bulkActions" @bulk-action="(action) => action === 'delete' && handleBulkDelete()"
                :global-filter-fields="globalFilters" :total-records="totalRecords">
            </AdvancedDataTable>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped lang="postcss">
:deep(.p-datatable-tbody .flex.gap-2 button) {
    @apply w-9 h-9;
}
</style>
