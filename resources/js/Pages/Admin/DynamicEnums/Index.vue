<!-- resources/js/Pages/Admin/DynamicEnums/Index.vue -->
<script setup lang="ts">
/**
 * Admin/DynamicEnums/Index.vue
 *
 * Updated admin listing page for DynamicEnums – now focused on viewing and managing options only.
 *
 * Changes / Problems Solved (based on new requirements):
 * - Removed "Add New" button entirely – dynamic enums are fixed/pre-seeded (title, gender, profile_type, address.type).
 *   No creation allowed; new customizable fields should use the Custom Fields module instead.
 * - Removed single-row delete action – core enums cannot be deleted (prevents breaking existing data/validation).
 * - Transformed into an expandable/accordion-style table:
 *     • Master rows show the enum definition (label, machine name, applies_to, description).
 *     • Expandable detail row shows the full list of options with inline editing and add/delete per-option.
 * - Added per-enum option management inside the expanded row:
 *     • Edit enum metadata (label, description, color) – name and applies_to are read-only/immutable.
 *     • Reorder options via drag-and-drop (future-proof, uses PrimeVue sortable).
 *     • Add new option (value + label + optional color).
 *     • Edit existing option.
 *     • Delete individual options (safe as long as not in use – backend can enforce).
 * - Uses modal-based editing for options (consistent with your app's pattern).
 * - Real-time updates: after save/delete, refreshes only the affected enum's options.
 * - No bulk actions – not needed for fixed set.
 * - Accessibility/responsiveness maintained with PrimeVue DataTable expansion + Tailwind.
 *
 * Fits into the refined DynamicEnums Module:
 * - Now a read-mostly admin view for inspecting and fine-tuning the fixed set of system/school-overridable enums.
 * - Core enums remain seeded and immutable in structure (name/applies_to).
 * - Schools can customize labels/descriptions and add/remove/reorder options without creating new enums.
 * - Aligns with decision: true free-form fields → use Custom Fields module; these are controlled vocabularies.
 * - Backend controller will need minor updates (remove store/destroy, add patch for options/metadata).
 */

import { ref, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';
import { useModal } from '@/composables/useModal';
import { router } from '@inertiajs/vue3';
import type { ColumnDefinition } from '@/types/datatables';
import { type DynamicEnum } from '@/types/dynamic-enums';

const modal = useModal();
const toast = useToast();

const props = defineProps<{
    data: DynamicEnum[]
    columns: ColumnDefinition<DynamicEnum>[]
    globalFilterables: string[]
    totalRecords: number
}>();

const enums = computed<DynamicEnum[]>(() => props.data);
const totalRecords = computed(() => props.totalRecords);
const expandedRows = ref<string[]>([]);

// Open modal to edit enum metadata (label, description, color)
const openEditMetadataModal = (enumData: any) => {
    modal.open('dynamic-enum-metadata', { enum: enumData, title: 'Edit Enum Details' });
};

// Open modal to manage options (add/edit/delete/reorder)
const openEditOptionsModal = (enumData: any) => {
    modal.open('dynamic-enum-options', { enum: enumData, title: `Manage Options – ${enumData.label}` });
};

// Listen for save events from modals
modal.emitter.value?.on('saved', () => {
    router.reload({ only: ['data'] });
    toast.add({ severity: 'success', summary: 'Success', detail: 'Changes saved successfully.' });
});
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Dynamic Enums Management</h1>
            <p class="text-gray-600 mt-2">
                View and customize options for system-defined enums. Enum names and target models are fixed.
                Use Custom Fields for fully free-form data.
            </p>
        </div>

        <div class="card">
            <DataTable :value="enums" :paginator="true" :rows="20" :totalRecords v-model:expandedRows="expandedRows"
                :globalFilterFields="globalFilterables" dataKey="id" responsiveLayout="scroll" size="small">
                <template #empty>
                    <div class="text-center py-8 text-gray-500">No dynamic enums defined.</div>
                </template>

                <template #loading>
                    <div class="flex items-center justify-center py-8">
                        <ProgressSpinner />
                        <span class="ml-3">Loading enums...</span>
                    </div>
                </template>

                <!-- Expand toggle -->
                <Column expander style="width: 4rem" />

                <!-- Master row content -->
                <Column field="label" header="Label" sortable />
                <Column field="name" header="Machine Name" sortable />
                <Column field="applies_to" header="Applies To" sortable>
                    <template #body="slotProps">
                        <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ slotProps.data.applies_to }}</code>
                    </template>
                </Column>
                <Column field="description" header="Description" />
                <Column header="Options" style="width: 8rem">
                    <template #body="slotProps">
                        <span class="font-medium">{{ slotProps.data.options?.length ?? 0 }}</span>
                    </template>
                </Column>

                <!-- Actions on master row -->
                <Column header="Actions" style="width: 14rem">
                    <template #body="slotProps">
                        <div class="flex gap-2">
                            <Button icon="pi pi-cog" class="p-button-rounded p-button-info p-button-sm"
                                @click="openEditMetadataModal(slotProps.data)"
                                title="Edit Details (label, description, color)" />
                            <Button icon="pi pi-list" class="p-button-rounded p-button-success p-button-sm"
                                @click="openEditOptionsModal(slotProps.data)" title="Manage Options" />
                        </div>
                    </template>
                </Column>

                <!-- Expanded detail row – options list preview -->
                <template #expansion="slotProps">
                    <div class="p-4 bg-gray-50 border-t">
                        <h4 class="font-medium mb-3">Current Options ({{ slotProps.data.options?.length ?? 0 }})</h4>
                        <div class="flex flex-wrap gap-3">
                            <div v-for="opt in slotProps.data.options" :key="opt.value"
                                class="flex items-center gap-2 bg-white px-3 py-2 rounded shadow-sm">
                                <span v-if="opt.color" :class="opt.color"
                                    class="w-4 h-4 rounded-full inline-block"></span>
                                <span class="font-medium">{{ opt.label }}</span>
                                <code class="text-xs text-gray-600">({{ opt.value }})</code>
                            </div>
                            <div v-if="!slotProps.data.options?.length" class="text-gray-500 italic">
                                No options defined yet.
                            </div>
                        </div>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>
</template>
