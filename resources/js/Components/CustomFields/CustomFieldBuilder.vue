<!--
  resources/js/components/CustomFields/CustomFieldBuilder.vue

  MAIN FORM BUILDER WORKSPACE LAYOUT
  ────────────────────────────────────────────────────────────────

  This is the full-screen page users see when they want to create or edit
  a complete set of custom fields for a resource (e.g. Student, Teacher).

  Big-picture layout (desktop view):
  ┌───────────────────────────────────────────────────────────────────────────────┐
  │ Toolbar ─────────────────────────────────────────────────────────────── top ──┤
  ├───────────────┬───────────────────────────────────────────────────────────────┤
  │ Left sidebar  │                       Canvas (middle)                         │
  │ Field types   │   (droppable area with field cards + drop zones)              │
  ├───────────────┼───────────────────────────────────────────────────────────────┤
  │               │   Right sidebar ────────────────────────────────────────────── right ──┤
  └───────────────┴───────────────────────────────────────────────────────────────┘
                     Properties panel (shows when a field is selected)

  Mobile behavior:
  - Sidebars collapse / become toggleable via bottom bar
  - Canvas takes full width
  - Preview opens as a full modal

  Main jobs of this component:
  1. Show toolbar (save, undo, preview toggle, etc.)
  2. Show left toolbox (FieldTypeSelector) where users drag new fields
  3. Show central canvas where fields are placed and reordered
  4. Show right properties panel when a field is selected
  5. Show live preview of what the form will look like (using DynamicForm)
  6. Handle drag & drop (new fields + reordering)
  7. Save everything to backend when user clicks Save
-->

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useFormBuilder } from '@/composables/useFormBuilder'
import { useToast } from 'primevue/usetoast'
import { router } from '@inertiajs/vue3'
import FieldTypeSelector from './FieldTypeSelector.vue'
import CustomFieldItem from './CustomFieldItem.vue'
import FieldPropertiesPanel from './FieldPropertiesPanel.vue' // ← this file probably doesn't exist yet — that's ok, it will show nothing until you create it

import { Button, ToggleButton, Dialog, Tooltip } from 'primevue'
import type { BuilderField } from '@/composables/useFormBuilder'
import DynamicForm from '../forms/DynamicForm.vue'
import type { FieldTypeMap } from '@/types/custom-fields'

// ────────────────────────────────────────────────
// Props coming from parent (usually a page like Settings/CustomFields/Builder.vue)
// ────────────────────────────────────────────────
const props = defineProps<{
    resource: string                  // e.g. "Student", "Teacher" — tells us which model we're customizing
    initialFields?: BuilderField[]    // optional — if editing an existing set of fields
    customFieldTypes: FieldTypeMap    // from CustomFieldType::toFrontendArray()
}>()

// ────────────────────────────────────────────────
// Events we send back to parent
// ────────────────────────────────────────────────
const emit = defineEmits<{
    (e: 'saved'): void               // parent might want to close builder or refresh list
    (e: 'cancelled'): void           // user clicked back/cancel
}>()

// ────────────────────────────────────────────────
// Core builder state (all important data lives here)
// ────────────────────────────────────────────────
const builder = useFormBuilder()   // ← this is the brain: fields array, selected field, undo/redo, etc.
const toast = useToast()           // PrimeVue toast notifications

// UI toggles (which panels are visible)
const showPreview = ref(false)     // show live form preview modal?
const showProperties = ref(true)   // show right sidebar (properties editor)?
const showLeftSidebar = ref(true)  // show left toolbox? (can be toggled on mobile)

// ────────────────────────────────────────────────
// Load any pre-existing fields (edit mode)
// ────────────────────────────────────────────────
onMounted(() => {
    if (props.initialFields?.length) {
        // Give every field a unique id (important for drag/drop & selection)
        builder.fields.value = props.initialFields.map((f, i) => ({
            ...f,
            id: f.id || crypto.randomUUID(),  // ← ensures every field has id
            sort: f.sort ?? i                 // make sure sort order is correct
        }))
    }
})

// ────────────────────────────────────────────────
// ── DRAG & DROP: when user drops a field type onto canvas ───────
// ────────────────────────────────────────────────
const handleDrop = (e: DragEvent, insertIndex?: number) => {
    // Prevent default browser behavior (e.g. opening file if dropped)
    e.preventDefault()
    e.stopPropagation()

    // Get the data that was dragged (from FieldTypeSelector)
    const data = e.dataTransfer?.getData('application/json')
    if (!data) return

    try {
        const payload = JSON.parse(data)
        // Add the new field at the drop position (or end if no index)
        builder.addNewField(payload.field_type, insertIndex)
    } catch (err) {
        console.error('Invalid drop payload', err)
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Could not add field',
            life: 3000
        })
    }
}

// Allow drop (re quired for drag & drop to work)
const allowDrop = (e: DragEvent) => {
    e.preventDefault()
}

// ────────────────────────────────────────────────
// Save the current form to backend
// ────────────────────────────────────────────────
const saveForm = async () => {
    // Get the clean payload ready for Laravel
    const payload = builder.getSavePayload()

    try {
        // Send to backend (adjust route name if different)
        router.post(route('settings.custom-fields.bulk'), {
            resource: props.resource,   // which model this set belongs to
            fields: payload             // array of field definitions
        }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Saved',
                    detail: 'Custom fields saved successfully',
                    life: 4000
                })
                emit('saved')  // tell parent page we finished
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0] || 'Validation error'
                toast.add({
                    severity: 'error',
                    summary: 'Save Failed',
                    detail: firstError,
                    life: 6000
                })
            }
        })
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save fields',
            life: 6000
        })
    }
}
</script>

<template>
    <!-- Main container – full screen height, no overflow outside -->
    <div class="builder-workspace flex flex-col h-screen bg-gray-50 dark:bg-gray-950 overflow-hidden">
        <!-- ── TOP TOOLBAR ──────────────────────────────────────────────── -->
        <div
            class="toolbar bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-4 py-3 flex items-center justify-between shadow-sm z-20">
            <!-- Left side: back + title -->
            <div class="flex items-center gap-4">
                <Button icon="pi pi-arrow-left" label="Back" text severity="secondary" @click="emit('cancelled')" />
                <!-- parent can close modal or go back to previous page -->

                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                    Custom Fields for {{ resource }}
                </h1>
            </div>

            <!-- Right side: controls -->
            <div class="flex items-center gap-3">
                <!-- Undo -->
                <Button icon="pi pi-undo" rounded text :disabled="!builder.canUndo" v-tooltip.bottom="'Undo (Ctrl+Z)'"
                    @click="builder.undo" />

                <!-- Redo -->
                <Button icon="pi pi-refresh" rounded text :disabled="!builder.canRedo" v-tooltip.bottom="'Redo'"
                    @click="builder.redo" />

                <!-- Toggle live preview -->
                <ToggleButton v-model="showPreview" on-label="Preview On" off-label="Preview Off" on-icon="pi pi-eye"
                    off-icon="pi pi-eye-slash" severity="info" class="w-40" />

                <!-- Save button -->
                <!-- you can add real loading state later -->
                <Button label="Save Changes" icon="pi pi-save" :loading="false" @click="saveForm"
                    class="p-button-success" />
            </div>
        </div>

        <!-- ── MAIN CONTENT AREA (sidebars + canvas) ───────────────────── -->
        <div class="flex flex-1 overflow-hidden">
            <!-- LEFT SIDEBAR: Field Types toolbox -->
            <div v-if="showLeftSidebar"
                class="field-selector-sidebar w-80 flex-shrink-0 border-r border-gray-200 dark:border-gray-800 overflow-y-auto bg-white dark:bg-gray-900 lg:block hidden">
                <!-- This component lets user drag new field types -->
                <FieldTypeSelector :custom-field-types="customFieldTypes" />
            </div>

            <!-- CENTRAL CANVAS: where fields live and can be dropped/reordered -->
            <div class="canvas flex-1 overflow-y-auto p-6 bg-gray-100 dark:bg-gray-800">
                <!-- Empty state drop zone (at top) -->
                <div class="drop-zone min-h-[200px] border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-6 mb-6 text-center text-gray-500 dark:text-gray-400"
                    @dragover="allowDrop" @drop="handleDrop($event, 0)">
                    <p v-if="!builder.fields.value.length">
                        Drag field types from the left panel (or tap on mobile) to start building
                    </p>
                    <p v-else class="text-sm">
                        Drop here to add at the beginning
                    </p>
                </div>

                <!-- Each existing field + drop zone after it -->
                <div v-for="(field, index) in builder.fields.value" :key="field.id" @dragover="allowDrop"
                    @drop="handleDrop($event, index + 1)">
                    <!-- The actual field card -->
                    <CustomFieldItem :field="field" :custom-field-types="customFieldTypes" />
                </div>
            </div>

            <!-- RIGHT SIDEBAR: Properties editor (only visible when field selected) -->
            <div v-if="showProperties && builder.selectedField.value"
                class="properties-sidebar w-96 flex-shrink-0 border-l border-gray-200 dark:border-gray-800 overflow-y-auto bg-white dark:bg-gray-900 xl:block hidden">
                <!-- This component will let you edit the selected field's settings -->
                <FieldPropertiesPanel :field="builder.selectedField.value" />
            </div>
        </div>

        <!-- ── MOBILE BOTTOM BAR (only visible on small screens) ────────── -->
        <div
            class="mobile-controls lg:hidden fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 p-3 flex justify-around z-30">
            <Button icon="pi pi-bars" label="Fields" text @click="showLeftSidebar = !showLeftSidebar" />
            <Button icon="pi pi-cog" label="Properties" text :disabled="!builder.selectedField"
                @click="showProperties = !showProperties" />
            <Button icon="pi pi-eye" label="Preview" text @click="showPreview = !showPreview" />
        </div>

        <!-- ── LIVE PREVIEW MODAL (opens when showPreview = true) ──────── -->
        <Dialog v-model:visible="showPreview" header="Live Form Preview" :modal="true"
            :style="{ width: 'min(95vw, 1200px)' }" :maximized="true" :pt="{ root: { class: 'preview-dialog' } }">
            <!-- This uses the same DynamicForm that real forms use -->
            <DynamicForm :model="resource" :submit-url="''" :fields="builder.fields" :readonly="true" class="max-w-4xl mx-auto p-6" />
        </Dialog>
    </div>
</template>

<style scoped>
.preview-dialog {
    @apply rounded-2xl shadow-2xl;
}

.mobile-controls {
    @apply flex justify-around items-center;
}

/* Optional: make canvas drop zones more visible when dragging */
:deep(.drop-zone.drag-over) {
    @apply border-primary-500 bg-primary-50 dark:bg-primary-950/30;
}
</style>

.
