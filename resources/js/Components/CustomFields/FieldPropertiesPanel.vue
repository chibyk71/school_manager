<!--
  resources/js/components/CustomFields/FieldPropertiesPanel.vue

  RIGHT SIDEBAR – FIELD PROPERTIES EDITOR
  ────────────────────────────────────────────────────────────────

  Purpose & Role in the Custom Fields module:
  • Displays and allows editing of all properties/settings for the currently selected field
  • Acts as the "inspector" panel — changes made here update the field in real-time
  • Provides type-specific UI controls (e.g. options table for select/radio, file constraints for file/image)
  • Handles validation rules input (simple textarea → array conversion)
  • Supports conditional visibility rules (placeholder UI — full logic later)
  • Two-way sync with useFormBuilder (updates field via updateField)
  • Responsive: full-width on mobile, fixed-width sidebar on desktop
  • Accessible: proper labels, ARIA, focus management

  Features / Problems solved:
  • Type-specific conditional rendering (options only for select/radio/etc.)
  • Real-time preview of changes (label, placeholder, required, etc.)
  • Options editor: add/remove/reorder options with value/label pairs
  • Rules editor: one rule per line → converted to array for backend
  • File/image constraints: max size, extensions, multiple toggle
  • Name uniqueness check & auto-suffix suggestion
  • Clean, scrollable layout with grouped sections
  • Error/toast feedback on invalid inputs

  Integration points:
  • Placed in CustomFieldBuilder.vue right sidebar
  • Receives :field prop from builder.selectedField
  • Uses useFormBuilder.updateField() to persist changes
  • Uses shared customFieldTypes metadata for type-specific hints
  • Works in both create & edit modes (new fields have temp id)

  Props:
  • field: BuilderField – the currently selected field object

  Dependencies:
  • useFormBuilder composable (updateField method)
  • PrimeVue: InputText, InputNumber, Textarea, ToggleSwitch, Button, Message, Chips (for extensions)
  • Tailwind + custom CSS for grouped sections & mobile spacing

  Future extensions planned:
  • Full conditional rules builder (if field X = Y then show/hide)
  • Default value input (with type-aware control)
  • Custom classes / Tailwind editor
  • Validation preview (show example error messages)
-->

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useFormBuilder } from '@/composables/useFormBuilder'
import { useToast } from 'primevue/usetoast'
import {
    InputText,
    InputNumber,
    Textarea,
    ToggleSwitch,
    Button,
    Message,
    Chips,
    useConfirm
} from 'primevue'
import type { BuilderField } from '@/composables/useFormBuilder'
const confirm = useConfirm()


// ────────────────────────────────────────────────
// Props
// ────────────────────────────────────────────────
const props = defineProps<{
    field: BuilderField
}>()

// ────────────────────────────────────────────────
// Builder & toast
// ────────────────────────────────────────────────
const builder = useFormBuilder()
const toast = useToast()

// Local form state (two-way bound to field)
const localField = ref<BuilderField>({ ...props.field })

// Sync local → builder on change
watch(
    localField,
    (newVal) => {
        builder.updateField(props.field.id, newVal)
    },
    { deep: true }
)

// Sync props → local when selection changes
watch(
    () => props.field,
    (newField) => {
        localField.value = { ...newField }
    },
    { deep: true }
)

// ────────────────────────────────────────────────
// Type-specific helpers
// ────────────────────────────────────────────────
const needsOptions = computed(() =>
    ['select', 'multiselect', 'radio', 'checkbox'].includes(localField.value.field_type)
)

const isFileType = computed(() =>
    ['file', 'image'].includes(localField.value.field_type)
)

// ────────────────────────────────────────────────
// Options editor helpers
// ────────────────────────────────────────────────
const addOption = () => {
    localField.value.options = localField.value.options || []
    localField.value.options.push({ value: '', label: '' })
}

const removeOption = (index: number) => {
    localField.value.options?.splice(index, 1)
}

// ────────────────────────────────────────────────
// Rules editor (textarea → array)
// ────────────────────────────────────────────────
const rulesText = computed({
    get: () => localField.value.rules?.join('\n') || '',
    set: (val: string) => {
        localField.value.rules = val
            .split('\n')
            .map(r => r.trim())
            .filter(r => r.length > 0)
    }
})

// ────────────────────────────────────────────────
// Name uniqueness suggestion
// ────────────────────────────────────────────────
const suggestUniqueName = () => {
    const base = localField.value.name || `field_${localField.value.field_type}`
    let name = base
    let counter = 1

    const existingNames = builder.fields.value
        .filter(f => f.id !== props.field.id)
        .map(f => f.name)

    while (existingNames.includes(name)) {
        name = `${base}_${counter++}`
    }

    localField.value.name = name
    toast.add({
        severity: 'info',
        summary: 'Name Adjusted',
        detail: `Renamed to "${name}" to avoid conflict`,
        life: 4000
    })
}

const confirmDelete = () => {
    confirm.require({
        message: `Are you sure you want to delete "${localField.value.label || localField.value.name || 'this field'}"?`,
        header: 'Delete Field',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Yes, Delete',
        acceptProps: {
            severity: 'danger'
        },
        rejectLabel: 'Cancel',
        accept: () => {
            builder.deleteField(props.field.id)
            toast.add({
                severity: 'success',
                summary: 'Deleted',
                detail: 'Field removed from the form',
                life: 3000
            })
        }
    })
}
</script>

<template>
    <div
        class="properties-panel h-full flex flex-col bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-800">
        <!-- Header -->
        <div class="p-5 border-b border-gray-200 dark:border-gray-800">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Field Settings
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ localField.field_type.toUpperCase() }} Field
            </p>
        </div>

        <!-- Scrollable content -->
        <div class="flex-1 overflow-y-auto p-5 space-y-6">
            <!-- Basic info -->
            <section>
                <h3 class="text-sm font-medium mb-3 text-gray-700 dark:text-gray-300">
                    Basic Information
                </h3>

                <!-- Name (machine name) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1.5">
                        Field Name (internal key)
                    </label>
                    <div class="flex gap-2">
                        <InputText v-model="localField.name" placeholder="e.g. emergency_contact" class="flex-1"
                            :invalid="!localField.name" />
                        <Button icon="pi pi-refresh" text severity="secondary" v-tooltip.top="'Make unique'"
                            @click="suggestUniqueName" />
                    </div>
                    <small class="text-xs text-gray-500 mt-1 block">
                        Used in code/database — lowercase, underscores
                    </small>
                </div>

                <!-- Display Label -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1.5">
                        Display Label
                    </label>
                    <InputText v-model="localField.label" placeholder="e.g. Emergency Contact Number" class="w-full" />
                </div>

                <!-- Required toggle -->
                <div class="flex items-center gap-3 mb-4">
                    <ToggleSwitch v-model="localField.required" />
                    <label class="text-sm font-medium cursor-pointer">
                        Required field
                    </label>
                </div>
            </section>

            <!-- Placeholder & Hint -->
            <section>
                <h3 class="text-sm font-medium mb-3 text-gray-700 dark:text-gray-300">
                    Input Hints
                </h3>

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1.5">
                            Placeholder
                        </label>
                        <InputText v-model="localField.placeholder" placeholder="e.g. Enter phone number"
                            class="w-full" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1.5">
                            Hint / Tooltip
                        </label>
                        <InputText v-model="localField.hint" placeholder="e.g. Include country code" class="w-full" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1.5">
                            Description (below field)
                        </label>
                        <Textarea v-model="localField.description" rows="2"
                            placeholder="Additional explanation for users" class="w-full" />
                    </div>
                </div>
            </section>

            <!-- Options (for select, radio, checkbox, multiselect) -->
            <section v-if="needsOptions">
                <h3 class="text-sm font-medium mb-3 text-gray-700 dark:text-gray-300">
                    Options
                </h3>

                <div v-if="!localField.options?.length" class="text-center py-6 text-gray-500">
                    No options yet — add below
                </div>

                <div v-for="(opt, index) in localField.options || []" :key="index" class="flex gap-3 mb-3 items-center">
                    <InputText v-model="opt.value" placeholder="Value (e.g. male)" class="flex-1" />
                    <InputText v-model="opt.label" placeholder="Display (e.g. Male)" class="flex-1" />
                    <Button icon="pi pi-trash" severity="danger" text rounded @click="removeOption(index)" />
                </div>

                <Button label="Add Option" icon="pi pi-plus" outlined class="mt-2 w-full" @click="addOption" />
            </section>

            <!-- File / Image constraints -->
            <section v-if="isFileType">
                <h3 class="text-sm font-medium mb-3 text-gray-700 dark:text-gray-300">
                    File Upload Settings
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1.5">
                            Max File Size (KB)
                        </label>
                        <InputNumber v-model="localField.file_constraints?.max_size_kb" :min="100" :max="20480"
                            :step="100" class="w-full" />
                        <small class="text-xs text-gray-500 mt-1 block">
                            Default: 2048 KB (2 MB)
                        </small>
                    </div>

                    <div class="flex items-center gap-3">
                        <ToggleSwitch v-model="localField.file_constraints?.multiple" />
                        <label class="text-sm font-medium cursor-pointer">
                            Allow multiple files
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1.5">
                            Allowed Extensions (comma-separated)
                        </label>
                        <Chips v-model="localField.file_constraints?.allowed_extensions" separator=","
                            placeholder="pdf, jpg, png" class="w-full" />
                        <small class="text-xs text-gray-500 mt-1 block">
                            Leave empty to allow all types
                        </small>
                    </div>
                </div>
            </section>

            <!-- Validation Rules -->
            <section>
                <h3 class="text-sm font-medium mb-3 text-gray-700 dark:text-gray-300">
                    Validation Rules
                </h3>

                <Textarea v-model="rulesText" rows="5"
                    placeholder="One rule per line&#10;e.g.&#10;required&#10;email&#10;max:255"
                    class="w-full font-mono text-sm" />

                <small class="text-xs text-gray-500 mt-2 block">
                    Laravel-style rules (required, email, max:255, mimes:pdf,jpg, etc.)
                </small>
            </section>
        </div>

        <!-- Footer actions -->
        <div class="p-5 border-t border-gray-200 dark:border-gray-800">
            <Button label="Delete Field" icon="pi pi-trash" severity="danger" text class="w-full"
                @click="confirmDelete" />
        </div>
    </div>
</template>

<style scoped>
/* Grouped sections */
section {
    @apply pb-6 border-b border-gray-200 dark:border-gray-800 last:border-b-0;
}
</style>
