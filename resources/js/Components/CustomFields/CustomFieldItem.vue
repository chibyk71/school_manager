<!--
  resources/js/components/CustomFields/CustomFieldItem.vue

  SINGLE FIELD BLOCK IN THE FORM BUILDER CANVAS
  ────────────────────────────────────────────────────────────────

  Purpose / Role in the Custom Fields module:
  • Renders one field as a draggable, selectable card/block in the builder workspace
  • Provides visual representation of a field (title, type, required status)
  • Handles user interactions: drag to reorder, click to select/edit, delete
  • Shows hover/active/selected states with Tailwind transitions
  • Displays type-specific icon/badge using backend metadata
  • Accessible: keyboard focus, ARIA roles, screen-reader labels
  • Responsive: compact on narrow canvas, full-width on mobile

  Features / Problems solved:
  • Unified look for all field types (text, select, file, etc.)
  • Immediate visual feedback on selection (border, shadow)
  • Safe deletion with confirmation (via PrimeVue confirm)
  • Drag handle visibility only on hover (clean canvas)
  • Type icon + name from shared customFieldTypes metadata
  • Required indicator (red asterisk) consistent with InputWrapper
  • Click anywhere on card selects field for properties panel
  • Delete button with tooltip + confirmation dialog
  • Prevents accidental drag when clicking settings/delete

  Integration points:
  • Rendered in v-for loop inside CustomFieldBuilder.vue canvas
  • Uses useFormBuilder composable for state (selectField, updateField, deleteField)
  • Reads shared Inertia prop customFieldTypes for icon/component info
  • Emits no events (uses composable directly — cleaner parent-child)
  • Works with drag/drop events from canvas (@dragstart, @dragover, @drop)

  Props:
  • field: BuilderField — the field object from useFormBuilder.fields

  Dependencies:
  • useFormBuilder composable
  • PrimeVue: Button, Tooltip, Confirm
  • Inertia shared: customFieldTypes (from CustomFieldType::toFrontendArray())
  • Tailwind + custom CSS for card styling

  Future extensions:
  • Inline label editing
  • Duplicate field button
  • Conditional visibility indicator
  • Preview mini-form inside card
-->

<script setup lang="ts">
import { computed } from 'vue'
import { useFormBuilder, type BuilderField } from '@/composables/useFormBuilder'
import { usePage } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import { Button } from 'primevue'
import type { FieldTypeMap } from '@/types/custom-fields'

// ────────────────────────────────────────────────
// Builder composable
// ────────────────────────────────────────────────
const builder = useFormBuilder()
const confirm = useConfirm()

// ────────────────────────────────────────────────
// Props & computed
// ────────────────────────────────────────────────
const props = defineProps<{
    field: BuilderField
    customFieldTypes: FieldTypeMap
}>()

const isSelected = computed(() => builder.selectedFieldId.value === props.field.id)

const typeMeta = computed(() => {
    return props.customFieldTypes[props.field.field_type] || {
        name: props.field.field_type,
        icon: 'pi pi-question-circle',
        component: 'Unknown'
    }
})

// ────────────────────────────────────────────────
// Actions
// ────────────────────────────────────────────────
const selectThisField = () => {
    builder.selectField(props.field.id)
}

const confirmDelete = () => {
    confirm.require({
        message: `Delete "${props.field.label || props.field.name}"?`,
        header: 'Remove Field',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Delete',
        acceptProps: {
            severity: 'danger'
        },
        rejectLabel: 'Cancel',
        accept: () => {
            builder.deleteField(props.field.id)
        }
    })
}

// ────────────────────────────────────────────────
// Drag events (for reordering)
// ────────────────────────────────────────────────
const onDragStart = (e: DragEvent) => {
    if (!e.dataTransfer) return

    // Tell canvas which field is being dragged
    e.dataTransfer.setData('fieldId', props.field.id)
    e.dataTransfer.effectAllowed = 'move'
}

const onDragOver = (e: DragEvent) => {
    e.preventDefault()
    e.dataTransfer!.dropEffect = 'move'
}
</script>

<template>
    <div class="custom-field-item group relative rounded-lg border bg-white dark:bg-gray-800 p-4 shadow-sm transition-all duration-150 cursor-pointer"
        :class="{
            'border-primary-500 ring-2 ring-primary-200 dark:ring-primary-800 shadow-md z-10': isSelected,
            'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-md': !isSelected,
        }" tabindex="0" role="button" @click="selectThisField" @keydown.enter.space.prevent="selectThisField"
        draggable="true" @dragstart="onDragStart" @dragover="onDragOver"
        aria-label="Field: {{ field.label || field.name }} ({{ typeMeta.name }})">
        <!-- Drag Handle (visible on hover) -->
        <div class="absolute -left-3 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity">
            <div
                class="w-6 h-10 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center cursor-grab active:cursor-grabbing">
                <i class="pi pi-bars text-gray-600 dark:text-gray-300" />
            </div>
        </div>

        <!-- Header row: Type icon + Label + Required -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <!-- Type badge/icon -->
                <div class="w-8 h-8 rounded-md bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-lg">
                    <i :class="typeMeta.icon" class="text-gray-600 dark:text-gray-300" />
                </div>

                <!-- Label (clickable to select) -->
                <div class="font-medium text-gray-900 dark:text-white truncate max-w-[180px]">
                    {{ field.label || 'Untitled Field' }}
                </div>
            </div>

            <!-- Required indicator -->
            <span v-if="field.required" class="text-red-500 text-xl font-bold" title="Required field">*</span>
        </div>

        <!-- Field type name & hint -->
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-3">
            {{ typeMeta.name }}
            <span v-if="field.placeholder" class="text-xs text-gray-400"> • {{ field.placeholder }}</span>
        </div>

        <!-- Action buttons (bottom right) -->
        <div class="absolute bottom-3 right-3 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
            <!-- Settings / Edit -->
            <Button icon="pi pi-cog" rounded text severity="info" size="small" v-tooltip.top="'Edit field settings'"
                @click.stop="selectThisField" />

            <!-- Delete -->
            <Button icon="pi pi-trash" rounded text severity="danger" size="small" v-tooltip.top="'Remove field'"
                @click.stop="confirmDelete" />
        </div>
    </div>
</template>

<style scoped>
.custom-field-item:focus {
    @apply outline-none ring-2 ring-primary-500 ring-offset-2;
}

.custom-field-item:active {
    @apply scale-[0.98];
}
</style>
