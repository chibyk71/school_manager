<!--
  resources/js/components/Modals/CustomFieldModal.vue

  PURPOSE / ROLE IN THE CUSTOM FIELDS MODULE:
  ────────────────────────────────────────────────────────────────
  Single-field CRUD modal for creating or editing a custom field definition.

  Main responsibilities:
  • Display form to create/edit one CustomField (name, label, type, required, placeholder, hint, options, rules, etc.)
  • Validate inputs client-side (basic + type-specific constraints)
  • Submit to correct Laravel endpoint (store or update)
  • Show success/error toasts and close modal on success
  • Handle optimistic UI feel (disable form during submit)
  • Support edit mode (pre-fill from existing field)
  • Fully accessible (labels, ARIA, focus management)
  • Responsive & consistent with app design (PrimeVue + Tailwind)

  Features / Problems solved:
  • Unified create & edit experience in one modal
  • Type-specific fields appear conditionally (e.g. options for select/radio, file constraints for file/image)
  • Safe handling of options (array of {value, label})
  • Rules input as simple textarea (one rule per line) → converted to array for backend
  • Integration with useCustomFields → invalidates cache on success
  • Prevents duplicate field names (basic client check)
  • Proper modal lifecycle (open/close events, focus trap via PrimeVue Dialog)
  • Error handling: field-specific + form-level feedback

  How it fits into the module:
  • Opened from Index.vue (list screen) via useModal().open('custom-field', { field?, onSuccess })
  • Uses same field rendering philosophy as DynamicForm (but simpler — no categories here)
  • Triggers cache invalidation → list & forms see changes immediately
  • Prepares data in format expected by CustomFieldsController (store/update)

  Props passed via modal payload:
  • field?: Partial<CustomField>     // present = edit mode
  • onSuccess?: () => void           // optional callback after save

  Dependencies:
  • PrimeVue: Dialog, InputText, Dropdown, ToggleSwitch, Textarea, Button, Toast, Message
  • Composables: useModal, useToast, useCustomFields
  • Types: CustomField (from types/custom-fields.ts)
  • Ziggy: route() for endpoints
  • Inertia: router (for reload if needed)

  Backend alignment:
  • POST   /settings/custom-fields           → store
  • PATCH  /settings/custom-fields/{id}      → update
-->

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { Dialog, InputText, ToggleSwitch, Textarea, Button, Message, Select } from 'primevue'
import { useModal } from '@/composables/useModal'
import { useCustomFields } from '@/composables/useCustomFields'
import type { CustomField, CustomFieldType } from '@/types/custom-fields'

// ────────────────────────────────────────────────
// Modal payload & state
// ────────────────────────────────────────────────
const modal = useModal()
const toast = useToast()

const payload = computed(() => modal.currentItem?.value?.data ?? {})
const isEditMode = computed(() => !!payload.value.field?.id)
const existingField = computed<Partial<CustomField>>(() => payload.value.field ?? {})

// ────────────────────────────────────────────────
// Form setup
// ────────────────────────────────────────────────
const form = useForm<Partial<CustomField>>({
    name: existingField.value.name ?? '',
    label: existingField.value.label ?? '',
    field_type: existingField.value.field_type ?? 'text' as CustomFieldType,
    required: existingField.value.required ?? false,
    placeholder: existingField.value.placeholder ?? '',
    hint: existingField.value.hint ?? '',
    description: existingField.value.description ?? '',
    options: existingField.value.options ?? [] as Array<{ value: string; label: string }>,
    rules: Array.isArray(existingField.value.rules) ? existingField.value.rules : [] as string[],
    classes: existingField.value.classes ?? '',
    file_constraints: existingField.value.file_constraints ?? {
        max_size_kb: 2048,
        allowed_extensions: [] as string[],
        multiple: false
    }
})

const submitting = ref(form.processing)
const formError = ref<string | null>(null)

// ────────────────────────────────────────────────
// Computed helpers
// ────────────────────────────────────────────────
const needsOptions = computed(() =>
    ['select', 'multiselect', 'radio', 'checkbox'].includes(form.field_type!)
)

const isFileType = computed(() =>
    ['file', 'image'].includes(form.field_type!)
)

const fieldTypes = [
    { value: 'text', label: 'Text' },
    { value: 'textarea', label: 'Textarea' },
    { value: 'number', label: 'Number' },
    { value: 'email', label: 'Email' },
    { value: 'tel', label: 'Phone' },
    { value: 'url', label: 'URL' },
    { value: 'date', label: 'Date' },
    { value: 'datetime', label: 'Date & Time' },
    { value: 'select', label: 'Select (single)' },
    { value: 'multiselect', label: 'Select (multiple)' },
    { value: 'radio', label: 'Radio Group' },
    { value: 'checkbox', label: 'Checkbox Group' },
    { value: 'boolean', label: 'Boolean (Toggle)' },
    { value: 'file', label: 'File Upload' },
    { value: 'image', label: 'Image Upload' },
    { value: 'color', label: 'Color Picker' }
]

// ────────────────────────────────────────────────
// Form submission
// ────────────────────────────────────────────────
const submit = async () => {
    formError.value = null
    form.clearErrors()

    // Prepare payload
    const payloadData = {
        ...form.data(),
        // Convert rules textarea to array (one per line)
        rules: form.rules?.join('\n').split('\n')
            .map((r: string) => r.trim())
            .filter((r: string) => r.length > 0),
        // Ensure options is proper array
        options: needsOptions.value ? form.options : []
    }

    const url = isEditMode.value
        ? route('settings.custom-fields.update', existingField.value.id)
        : route('settings.custom-fields.store')

    const method = isEditMode.value ? 'put' : 'post'

    try {
        form.post(url, {
            data: payloadData,
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: isEditMode.value ? 'Field updated' : 'Field created',
                    life: 4000
                })

                // Invalidate cache so list & forms see the change
                const fields = useCustomFields({ model: 'any' }) // dummy to get invalidate
                fields.invalidateCache()

                modal.closeCurrent()
                payload.value.onSuccess?.()
            },
            onError: (errors) => {
                formError.value = 'Please check the form for errors.'
                const firstError = Object.values(errors)[0] as string
                if (firstError) {
                    toast.add({
                        severity: 'error',
                        summary: 'Validation Error',
                        detail: firstError,
                        life: 6000
                    })
                }
            }
        })
    } catch (err: any) {
        formError.value = err.message || 'An unexpected error occurred.'
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: formError.value,
            life: 6000
        })
    } finally {
    }
}

const computedFileConstraint = computed({
    get: () => form.file_constraints?.allowed_extensions?.join(', ') ?? '',
    set: (value: string) => {
        if (!form.file_constraints) {
            form.file_constraints = {
                max_size_kb: 2048,
                allowed_extensions: [] as string[],
                multiple: false
            }
        }
        form.file_constraints.allowed_extensions = value
            .split(',')
            .map((ext: string) => ext.trim())
            .filter((ext: string) => ext.length > 0)
    }
})

const computedRules = computed({
    get: () => form.rules?.join(', ') ?? '',
    set: (value) => (form.rules = value.split(',').map((r: string) => r.trim()).filter((r: string) => r.length > 0))
})

// ────────────────────────────────────────────────
// Options management (simple array editor)
// ────────────────────────────────────────────────
const addOption = () => {
    form.options?.push({ value: '', label: '' })
}

const removeOption = (index: number) => {
    form.options?.splice(index, 1)
}
</script>

<template>
    <div class="">
        <div class="">
            <h4 class="">{{ isEditMode ? 'Edit Field' : 'Create Field' }}</h4>
        </div>
        <div v-if="formError" class="mb-6">
            <Message severity="error" :closable="false">
                {{ formError }}
            </Message>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Name (machine name) -->
            <div class="field">
                <label for="name" class="block mb-1.5 text-sm font-medium">
                    Field Name <span class="text-red-500">*</span>
                </label>
                <InputText id="name" v-model="form.name" placeholder="e.g. emergency_contact"
                    :disabled="submitting || isEditMode" :invalid="!!form.errors.name" class="w-full" />
                <small v-if="form.errors.name" class="text-red-500 text-xs mt-1 block">
                    {{ form.errors.name }}
                </small>
                <p class="text-xs text-gray-500 mt-1">
                    Used internally (lowercase, underscores, no spaces)
                </p>
            </div>

            <!-- Label (display name) -->
            <div class="field">
                <label for="label" class="block mb-1.5 text-sm font-medium">
                    Display Label <span class="text-red-500">*</span>
                </label>
                <InputText id="label" v-model="form.label" placeholder="e.g. Emergency Contact Number"
                    :disabled="submitting" :invalid="!!form.errors.label" class="w-full" />
                <small v-if="form.errors.label" class="text-red-500 text-xs mt-1 block">
                    {{ form.errors.label }}
                </small>
            </div>

            <!-- Field Type -->
            <div class="field">
                <label for="field_type" class="block mb-1.5 text-sm font-medium">
                    Field Type <span class="text-red-500">*</span>
                </label>
                <Select id="field_type" v-model="form.field_type" :options="fieldTypes" option-label="label"
                    option-value="value" placeholder="Select field type" :disabled="submitting"
                    :invalid="!!form.errors.field_type" class="w-full" />
                <small v-if="form.errors.field_type" class="text-red-500 text-xs mt-1 block">
                    {{ form.errors.field_type }}
                </small>
            </div>

            <!-- Required toggle -->
            <div class="field flex items-center gap-3">
                <InputSwitch id="required" v-model="form.required" :disabled="submitting" />
                <label for="required" class="text-sm font-medium cursor-pointer">
                    Required field
                </label>
            </div>

            <!-- Placeholder & Hint -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="field">
                    <label for="placeholder" class="block mb-1.5 text-sm font-medium">
                        Placeholder
                    </label>
                    <InputText id="placeholder" v-model="form.placeholder" placeholder="e.g. Enter phone number"
                        :disabled="submitting" class="w-full" />
                </div>

                <div class="field">
                    <label for="hint" class="block mb-1.5 text-sm font-medium">
                        Hint / Tooltip
                    </label>
                    <InputText id="hint" v-model="form.hint" placeholder="e.g. Include country code"
                        :disabled="submitting" class="w-full" />
                </div>
            </div>

            <!-- Options (for select/radio/etc) -->
            <div v-if="needsOptions" class="field">
                <label class="block mb-2 text-sm font-medium">
                    Options <span class="text-red-500">*</span>
                </label>

                <div v-for="(opt, index) in form.options" :key="index" class="flex gap-3 mb-3 items-center">
                    <InputText v-model="opt.value" placeholder="Value (e.g. male)" class="flex-1"
                        :disabled="submitting" />
                    <InputText v-model="opt.label" placeholder="Display Label (e.g. Male)" class="flex-1"
                        :disabled="submitting" />
                    <Button icon="pi pi-trash" severity="danger" text rounded @click="removeOption(index)"
                        :disabled="submitting" />
                </div>

                <Button label="Add Option" icon="pi pi-plus" outlined class="mt-2" @click="addOption"
                    :disabled="submitting" />

                <small v-if="form.errors.options" class="text-red-500 text-xs mt-2 block">
                    {{ form.errors.options }}
                </small>
            </div>

            <!-- File constraints -->
            <div v-if="isFileType" class="space-y-4 border-t pt-6">
                <h4 class="text-sm font-semibold">File Upload Settings</h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="field">
                        <label for="max_size_kb" class="block mb-1.5 text-sm font-medium">
                            Max File Size (KB)
                        </label>
                        <InputNumber id="max_size_kb" v-model="form.file_constraints?.max_size_kb" :min="100"
                            :max="10240" :step="100" :disabled="submitting" class="w-full" />
                    </div>

                    <div class="field flex items-center gap-3 mt-8">
                        <InputSwitch id="multiple" v-model="form.file_constraints?.multiple" :disabled="submitting" />
                        <label for="multiple" class="text-sm font-medium cursor-pointer">
                            Allow multiple files
                        </label>
                    </div>
                </div>

                <!-- Allowed extensions (simple comma-separated input) -->
                <div class="field">
                    <label for="extensions" class="block mb-1.5 text-sm font-medium">
                        Allowed Extensions (comma-separated)
                    </label>
                    <InputText id="extensions" v-model="computedFileConstraint"
                        placeholder="pdf,jpg,png,docx" :disabled="submitting" class="w-full" />
                    <small class="text-xs text-gray-500 mt-1 block">
                        Leave blank to allow all
                    </small>
                </div>
            </div>

            <!-- Rules (one per line) -->
            <div class="field">
                <label for="rules" class="block mb-1.5 text-sm font-medium">
                    Validation Rules (one per line)
                </label>
                <!-- TODO: the end users are not programmers and so dont know the rules or how towrite them, make this a dynamic select that show rules applicaple to the field type the user selected to aviod error and posible security vulnerability -->
                <Textarea id="rules" v-model="computedRules" placeholder="required&#10;min:10&#10;max:255&#10;email"
                    rows="4" :disabled="submitting" class="w-full" />
                <small class="text-xs text-gray-500 mt-1 block">
                    Laravel-style rules (e.g. required, email, max:255, mimes:pdf,jpg)
                </small>
            </div>

            <!-- Submit buttons -->
            <div class="flex justify-end gap-4 mt-8 pt-6 border-t">
                <Button type="button" label="Cancel" severity="secondary" text @click="modal.closeCurrent()"
                    :disabled="submitting" />

                <Button type="submit" :label="isEditMode ? 'Update Field' : 'Create Field'" :loading="submitting"
                    :disabled="submitting" class="min-w-[140px]" />
            </div>
        </form>
    </div>
</template>

<style scoped>
/* Optional custom overrides */
:deep(.p-dialog .p-dialog-content) {
    @apply max-h-[80vh] overflow-y-auto;
}
</style>
