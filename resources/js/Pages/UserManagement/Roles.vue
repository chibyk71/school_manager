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
    meta?: {
        currentPage: number;
        perPage: number;
        total: number;
        lastPage: number;
    };
}>();

const initialTableResponse = computed(() => {
    if (!props.meta || !props.columns?.length) return null
    return { data: props.data ?? [], columns: props.columns as any, meta: props.meta }
});

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

const handleBulkDelete = async () => {
    if (!selectedResourceIds.value.length) return;
    await deleteResource('admin.roles.destroy', selectedResourceIds.value);
};

const bulkActions = computed<BulkAction[]>(() => [
    {
        label: 'Delete Selected',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: handleBulkDelete,
    },
]);

const roleActions = computed<TableAction<any>[]>(() => [
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        handler: (row) => openEditModal(row),
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        severity: 'danger',
        handler: async (row) => {
            await deleteResource('admin.roles.destroy', [row.id]);
        },
    },
]);

const enhancedColumns = computed(() => {
    const cols = [...(props.columns || [])];
    return cols;
});
</script>

<template>
    <AuthenticatedLayout title="Roles Management" :crumb="[{ label: 'User Management' }, { label: 'Roles' }]" :buttons="[
        {
            label: 'Create New Role',
            icon: 'pi pi-plus',
            severity: 'primary',
            onClick: openCreateModal,
        }
    ]">
        <div class="space-y-6">
            <AdvancedDataTable
                :actions="roleActions"
                endpoint="/admin/roles"
                :columns="enhancedColumns"
                :initial-response="initialTableResponse"
                :bulk-actions="bulkActions"
            />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped lang="postcss">
:deep(.p-datatable-tbody .flex.gap-2 button) {
    @apply w-9 h-9;
}
</style>
