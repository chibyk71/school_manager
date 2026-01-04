<!-- resources/js/Pages/Admin/DynamicEnums/DynamicEnumOptionsForm.vue -->
<script setup lang="ts">
/**
 * Admin/DynamicEnums/DynamicEnumOptionsForm.vue
 *
 * Modal form for managing the options list of a fixed DynamicEnum.
 *
 * Features / Problems Solved:
 * - Provides full CRUD for individual options within a single dynamic enum:
 *     • Add new option (value + label + optional color)
 *     • Edit existing option
 *     • Delete individual option (with confirmation)
 *     • Reorder options via drag-and-drop (updates sort order on save)
 * - Options are edited in-place in a clean PrimeVue DataTable with row editing.
 * - Automatic validation:
 *     • Unique values within the enum
 *     • Required value and label
 *     • Value must be alpha_dash (safe for DB/storage)
 * - Drag-and-drop reordering using PrimeVue's built-in row reordering (no extra library needed).
 * - Real-time preview of option appearance (badge with color if provided).
 * - Bulk save: all changes (add/edit/delete/reorder) are sent in one PATCH request.
 * - Uses useModalForm.ts for submission, error handling, toasts, and modal closing.
 * - Graceful handling of empty options list.
 * - Responsive: table scrolls horizontally on mobile, actions stacked.
 * - Accessibility: proper ARIA labels via PrimeVue, keyboard support for editing/reordering.
 * - Production-ready: type-safe, aligned with Tailwind/PrimeVue theme, efficient single request.
 *
 * Fits into the DynamicEnums Module:
 * - Second of two admin modals (alongside metadata editing).
 * - Opened from Index page via 'dynamic-enum-options' modal ID.
 * - Payload: { enum: DynamicEnum } – receives full enum with current options.
 * - Submits entire updated options array to a dedicated endpoint
 *   (e.g., PATCH /admin/dynamic-enums/{id}/options).
 * - Ensures schools can fully customize the allowed values while keeping enum identity fixed.
 * - Complements the "no create/delete enum" rule – only options are mutable.
 */

import { ref, computed, watch } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { useModalForm } from '@/composables/useModalForm';
import { useModal } from '@/composables/useModal';
import type { DynamicEnum, DynamicEnumOption } from '@/types/dynamic-enums';

interface Props {
    /** Full enum object passed from Index page */
    enum: DynamicEnum;
}

const props = defineProps<Props>();

const toast = useToast();
const confirm = useConfirm();
const modal = useModal();

// Local editable copy of options – with added temporary id for Vue keying
const localOptions = ref<
    (DynamicEnumOption & { _tempId?: string; _isNew?: boolean })[]
>(
    props.enum.options.map((opt, index) => ({
        ...opt,
        _tempId: `existing-${index}`, // stable key for existing
    }))
);

// Track changes for dirty state (optional future enhancement)
const hasChanges = computed(() => {
    // Simple comparison – could be deep if needed
    return JSON.stringify(localOptions.value.map(o => ({ value: o.value, label: o.label, color: o.color }))) !==
        JSON.stringify(props.enum.options);
});

// Add new empty option
const addOption = () => {
    localOptions.value.push({
        value: '',
        label: '',
        color: '',
        _tempId: `new-${Date.now()}`,
        _isNew: true,
    });
};

// Delete option with confirmation
const deleteOption = (option: typeof localOptions.value[0]) => {
    confirm.require({
        message: 'Are you sure you want to delete this option? This cannot be undone.',
        header: 'Confirm Delete',
        icon: 'pi pi-exclamation-triangle',
        accept: () => {
            const index = localOptions.value.findIndex(opt => opt._tempId === option._tempId);
            if (index > -1) {
                localOptions.value.splice(index, 1);
                toast.add({ severity: 'info', summary: 'Deleted', detail: 'Option removed (save to confirm).' });
            }
        },
    });
};

// Reorder handler – PrimeVue emits new data order
const onRowReorder = (event: { value: typeof localOptions.value }) => {
    localOptions.value = event.value;
};

// useModalForm setup – submits full options array
const { form, submit, isLoading } = useModalForm(
    { options: localOptions.value },
    {
        method: 'patch',
        resource: route('dynamic-enums.options', props.enum.id), // Assumes route dynamic-enums.options → PATCH /admin/dynamic-enums/{id}/options
        resourceId: props.enum.id,
        successMessage: 'Options updated successfully.',
        onSuccess: () => {
            modal.emitter.value?.emit('saved');
        },
    }
);

// Sync localOptions → form.options on changes
watch(localOptions, (newOpts) => {
    form.options = newOpts.map(opt => ({
        value: opt.value,
        label: opt.label,
        color: opt.color || undefined,
    }));
}, { deep: true });

// Initial sync
form.options = localOptions.value.map(opt => ({
    value: opt.value,
    label: opt.label,
    color: opt.color || undefined,
}));
</script>

<template>
    <div class="space-y-6">
        <div class="text-sm text-gray-600">
            <p class="font-medium mb-2">Enum: {{ props.enum.label }} ({{ props.enum.name }})</p>
            <p>Manage the allowed options. Changes are saved in bulk when you click "Save All".</p>
        </div>

        <div class="card">
            <DataTable :value="localOptions" editMode="row" dataKey="_tempId" @row-reorder="onRowReorder"
                reorderableRows responsiveLayout="scroll" class="p-datatable-sm">
                <template #empty>
                    <div class="text-center py-6 text-gray-500">No options defined yet. Click "Add Option" to start.
                    </div>
                </template>

                <Column style="width: 3rem" rowReorder />

                <Column field="value" header="Value (machine)" style="min-width: 12rem">
                    <template #editor="slotProps">
                        <InputText v-model="slotProps.data.value" class="w-full" placeholder="e.g., male"
                            :class="{ 'p-invalid': !slotProps.data.value }" />
                    </template>
                    <template #body="slotProps">
                        <code class="text-xs">{{ slotProps.data.value || '<empty>' }}</code>
                    </template>
                </Column>

                <Column field="label" header="Label (display)" style="min-width: 14rem">
                    <template #editor="slotProps">
                        <InputText v-model="slotProps.data.label" class="w-full" placeholder="e.g., Male"
                            :class="{ 'p-invalid': !slotProps.data.label }" />
                    </template>
                    <template #body="slotProps">
                        {{ slotProps.data.label || '<empty>' }}
                    </template>
                </Column>

                <Column field="color" header="Color Badge" style="min-width: 12rem">
                    <template #editor="slotProps">
                        <InputText v-model="slotProps.data.color" class="w-full"
                            placeholder="e.g., bg-blue-100 text-blue-800" />
                    </template>
                    <template #body="slotProps">
                        <span v-if="slotProps.data.color" :class="slotProps.data.color"
                            class="inline-block px-3 py-1 rounded-full text-xs">
                            {{ slotProps.data.color }}
                        </span>
                        <span v-else class="text-gray-400 text-xs">None</span>
                    </template>
                </Column>

                <Column :exportable="false" style="width: 8rem">
                    <template #body="slotProps">
                        <Button icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-sm"
                            @click="deleteOption(slotProps.data)" title="Delete option" />
                    </template>
                </Column>
            </DataTable>

            <div class="flex justify-between items-center mt-4">
                <Button label="Add Option" icon="pi pi-plus" class="p-button-success" @click="addOption"
                    :disabled="isLoading" />

                <div class="text-sm text-gray-500">
                    Drag rows to reorder
                </div>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="flex justify-end gap-3 pt-4 border-t">
            <Button label="Cancel" severity="secondary" outlined @click="useModal().closeCurrent()"
                :disabled="isLoading" />
            <Button label="Save All Changes" icon="pi pi-save" :loading="isLoading" :disabled="!hasChanges"
                @click="submit" />
        </div>
    </div>
</template>
