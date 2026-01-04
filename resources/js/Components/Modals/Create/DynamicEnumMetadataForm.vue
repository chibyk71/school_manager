<!-- resources/js/Pages/Admin/DynamicEnums/DynamicEnumMetadataForm.vue -->
<script setup lang="ts">
/**
 * Admin/DynamicEnums/DynamicEnumMetadataForm.vue
 *
 * Modal form for editing the non-structural metadata of a fixed DynamicEnum.
 *
 * Features / Problems Solved:
 * - Allows school admins to customize the display label, description, and color of a system-defined enum
 *   without touching the immutable structural fields (name, applies_to).
 * - Used exclusively inside a modal opened from the Index page (via 'dynamic-enum-metadata' modal ID).
 * - Read-only display of immutable fields (name, applies_to) for context and safety.
 * - Simple, focused form: only three editable fields – keeps UI clean and prevents mistakes.
 * - Full validation feedback via Inertia errors (server-side from Update request).
 * - Uses your existing useModalForm.ts composable for submission, toasts, and modal closing.
 * - Auto-closes modal and emits 'saved' event on success for table refresh.
 * - Responsive: works perfectly in ResourceDialog.vue (maxWidth lg) on mobile/desktop.
 * - Accessibility: proper labels, PrimeVue built-in ARIA support.
 * - Production-ready: type-safe, aligned with Tailwind/PrimeVue theme, no unnecessary complexity.
 *
 * Fits into the DynamicEnums Module:
 * - One of two admin modals (alongside options management).
 * - Payload received via modal data: { enum: DynamicEnum }.
 * - Submits to a dedicated PATCH endpoint (e.g., dynamic-enums.metadata) that only updates allowed fields.
 * - Ensures core enum identity (name/applies_to) remains immutable while allowing cosmetic customization.
 * - Complements the "no create/delete" rule – enums are fixed, only tunable.
 */

import { computed } from 'vue';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { useModalForm } from '@/composables/useModalForm';
import type { DynamicEnum } from '@/types/dynamic-enums';
import { useModal } from '@/composables/useModal';

interface Props {
    /** Payload passed from modal.open() – contains the enum to edit */
    enum: DynamicEnum;
}

const modal = useModal();

const props = defineProps<Props>();

// Initial form data – only editable fields
const initialData = {
    label: props.enum.label,
    description: props.enum.description ?? '',
    color: props.enum.color ?? '',
};

// useModalForm handles submission, validation errors, processing state, and modal close
const { form, submit, isLoading, errors } = useModalForm(initialData, {
    method: 'patch',
    url: route('dynamic-enums.metadata', props.enum.id), // Assumes route 'dynamic-enums.metadata' → PATCH /admin/dynamic-enums/{id}/metadata
    resourceId: props.enum.id,
    successMessage: 'Enum details updated successfully.',
    onSuccess: () => {
        // Emit custom event for Index page to refresh
        modal.emitter.value?.emit('saved');
    },
});
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-6">
            <!-- Immutable context fields (read-only) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Machine Name</label>
                <code class="block w-full px-3 py-2 bg-gray-100 rounded-md text-sm">{{ props.enum.name }}</code>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Applies To</label>
                <code class="block w-full px-3 py-2 bg-gray-100 rounded-md text-sm">{{ props.enum.applies_to }}</code>
            </div>

            <!-- Editable fields -->
            <div>
                <label for="label" class="block text-sm font-medium text-gray-700 mb-1">
                    Display Label <span class="text-red-600">*</span>
                </label>
                <InputText id="label" v-model="form.label" type="text" class="w-full"
                    :class="{ 'p-invalid': errors.label }" placeholder="e.g., Gender" autofocus />
                <small v-if="errors.label" class="p-error">{{ errors.label[0] }}</small>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                    Description
                </label>
                <Textarea id="description" v-model="form.description" rows="3" class="w-full"
                    :class="{ 'p-invalid': errors.description }"
                    placeholder="Optional description for admin reference" />
                <small v-if="errors.description" class="p-error">{{ errors.description[0] }}</small>
            </div>

            <div>
                <label for="color" class="block text-sm font-medium text-gray-700 mb-1">
                    Badge Color (Tailwind classes)
                </label>
                <InputText id="color" v-model="form.color" type="text" class="w-full"
                    :class="{ 'p-invalid': errors.color }" placeholder="e.g., bg-pink-100 text-pink-800" />
                <small class="text-gray-500">Optional – used in admin previews and option badges</small>
                <small v-if="errors.color" class="p-error block mt-1">{{ errors.color[0] }}</small>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="flex justify-end gap-3 pt-4 border-t">
            <Button label="Cancel" severity="secondary" outlined @click="useModal().closeCurrent()"
                :disabled="isLoading" />
            <Button label="Save Changes" icon="pi pi-check" :loading="isLoading" @click="submit" />
        </div>
    </div>
</template>
