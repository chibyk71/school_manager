<!-- resources/js/Pages/Settings/Schools/Index.vue -->
<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue';
import { usePermissions } from '@/composables/usePermissions';
import { ToggleSwitch } from 'primevue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { BulkAction } from '@/types/datatables';

// Inertia page props
const props = defineProps<{
    schools: any; // Paginated response from controller
    columns: any[]; // From ColumnDefinitionHelper
}>();

// Toast & Confirm
const toast = useToast();
const confirm = useConfirm();

// Permissions
const { hasPermission } = usePermissions();

// Enhanced columns with custom renderers (actions + status toggle + logo preview)
const enhancedColumns = computed(() => {
    return props.columns.map((col: any) => {
        // Logo preview
        if (col.field === 'logo_url') {
            return {
                ...col,
                render: (row: any) => ({
                    template: 'img',
                    src: row.logo_url || '/images/default-school-logo.png',
                    class: 'w-12 h-12 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700',
                    alt: `${row.name} logo`,
                }),
            };
        }

        // Active status toggle
        if (col.field === 'is_active') {
            return {
                ...col,
                render: (row: any) => ({
                    component: ToggleSwitch,
                    props: { modelValue: row.is_active, disabled: !hasPermission('update-school') },
                    on: {
                        'update:modelValue': () => toggleStatus(row),
                    },
                }),
            };
        }

        // Actions column (always last)
        if (col.field === 'actions') {
            return {
                ...col,
                frozen: true,
                align: 'right',
                width: '140px',
                render: (row: any) => ({
                    template: 'div',
                    class: 'flex items-center justify-end gap-2',
                    children: [
                        {
                            template: 'button',
                            icon: 'pi pi-pencil',
                            class: 'p-button p-button-rounded p-button-success p-button-sm',
                            props: { disabled: !hasPermission('update-school') },
                            on: { click: () => openEditModal(row) },
                            'aria-label': `Edit ${row.name}`,
                        },
                        {
                            template: 'button',
                            icon: 'pi pi-trash',
                            class: 'p-button-rounded p-button-danger p-button-sm',
                            props: { disabled: !hasPermission('delete-school') },
                            on: { click: () => deactivateSchool(row) },
                            'aria-label': `Deactivate ${row.name}`,
                        },
                    ],
                }),
            };
        }

        return col;
    });
});

// Bulk actions
const bulkActions: BulkAction[] = [
    {
        label: 'Deactivate Selected',
        action: 'deactivate',
        severity: 'danger',
        icon: 'pi pi-trash',
        confirm: {
            message: 'Are you sure you want to deactivate the selected schools?',
            header: 'Confirm Bulk Deactivation',
            acceptLabel: 'Yes, Deactivate',
            severity: 'danger',
        },
    },
];

// Compute students array for grid view
const schoolsArray = computed(() => props.schools?.data ?? [])

// Open edit modal (using DynamicDialog from layout)
const openEditModal = (school: any) => {
    if (!hasPermission('update-school')) {
        toast.add({ severity: 'warn', summary: 'Unauthorized', detail: 'You cannot edit schools.', life: 4000 });
        return;
    }

    // Assuming you have a registered dynamic component 'SchoolFormDialog'
    // Or import and use <DynamicDialog> programmatically
    import('@/Components/Modals/Create/SchoolForm.vue').then((module) => {
        // Use PrimeVue DialogService if configured, or fallback
        // For simplicity: router.visit with query or separate route
        router.visit(route('settings.schools.edit', school.id));
    });
};

// Toggle status (optimistic + partial update)
const toggleStatus = async (school: any) => {
    const original = school.is_active;
    school.is_active = !original; // Optimistic UI

    try {
        await router.patch(route('settings.schools.update', school.id), { is_active: school.is_active }, { preserveScroll: true });
        toast.add({ severity: 'success', summary: 'Updated', detail: 'School status changed.', life: 3000 });
    } catch (error: any) {
        school.is_active = original; // Rollback
        toast.add({
            severity: 'error',
            summary: 'Failed',
            detail: error.response?.data?.message || 'Could not update status.',
            life: 5000,
        });
    }
};

// Deactivate single school
const deactivateSchool = (school: any) => {
    if (!hasPermission('delete-school')) {
        toast.add({ severity: 'warn', summary: 'Unauthorized', detail: 'You cannot deactivate schools.', life: 4000 });
        return;
    }

    confirm.require({
        message: `Deactivate "${school.name}"? This will prevent access but keep data.`,
        header: 'Confirm Deactivation',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await router.delete(route('settings.schools.destroy', school.id), { preserveScroll: true });
                toast.add({ severity: 'success', summary: 'Deactivated', detail: `${school.name} deactivated.`, life: 4000 });
                router.reload({ only: ['schools'] });
            } catch (error: any) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: error.response?.data?.error || 'Cannot deactivate: active users/students exist.',
                    life: 6000,
                });
            }
        },
    });
};

// Bulk action handler
const handleBulkAction = async (action: string, selected: any[]) => {
    if (action === 'deactivate') {
        confirm.require({
            message: `Deactivate ${selected.length} selected school(s)?`,
            header: 'Bulk Deactivation',
            acceptClass: 'p-button-danger',
            accept: async () => {
                try {
                    await router.post(route('settings.schools.bulk-deactivate'), { ids: selected.map(s => s.id) });
                    toast.add({ severity: 'success', summary: 'Success', detail: 'Schools deactivated.', life: 4000 });
                    router.reload({ only: ['schools'] });
                } catch (error: any) {
                    toast.add({ severity: 'error', summary: 'Failed', detail: 'Bulk operation failed.', life: 5000 });
                }
            },
        });
    }
};

onMounted(() => {
    // Optional: Pre-fetch or analytics
});
</script>

<template>
    <AuthenticatedLayout title="Schools Management" :crumb="[{ label: 'Dashboard', url: route('dashboard') },
        { label: 'Schools' }]" :buttons="hasPermission('create-school')?[{
            label: 'Add New School',
            icon: 'pi pi-plus',
            class: 'p-button-success',
            onClick: () => router.visit(route('schools.create'))
        }]: []">

        <!-- Page Content -->
        <div class="space-y-6">
            <!-- Optional Page Description -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    Manage your organization's schools. Super admins see all schools; school owners see only their
                    assigned ones.
                </p>
            </div>

            <!-- Advanced DataTable -->
            <AdvancedDataTable endpoint="/schools" :columns="enhancedColumns" :bulk-actions="bulkActions"
                :initial-data="schoolsArray" :total-records="schools.total" :global-filter-fields="['name','slug','code','email']" @bulk-action="handleBulkAction" />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped lang="postcss">
/* Custom enhancements for this page */
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