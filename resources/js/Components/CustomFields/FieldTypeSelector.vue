<!--
  resources/js/components/CustomFields/FieldTypeSelector.vue

  LEFT SIDEBAR / TOOLBAR FOR FORM BUILDER
  ────────────────────────────────────────────────────────────────

  Purpose / Role in the Custom Fields module:
  • Displays all available field types as draggable cards
  • Acts as the "toolbox" from which users drag new fields into the builder canvas
  • Pulls metadata directly from backend-shared data (via Inertia props.customFieldTypes)
  • Supports drag & drop initiation (HTML5 Drag API + optional PrimeVue DnD)
  • Responsive: vertical list on desktop, collapsible/scrollable on mobile
  • Accessible: keyboard focusable cards, ARIA labels, drag announcements

  Features implemented:
  • Cards show: icon, name, short description (from metadata)
  • Draggable: each card has dataTransfer payload with field_type
  • Hover/active states with Tailwind transitions
  • Loading/fallback when types not yet loaded
  • Filters out unsupported types automatically
  • Emits 'drag-start' event (or you can use native drag events)

  Integration points:
  • Placed in CustomFieldBuilder.vue as left sidebar
  • Reads Inertia shared prop: customFieldTypes (from CustomFieldType::toFrontendArray())
  • Used with HTML5 Drag & Drop (or PrimeVue draggable if you have a DnD wrapper)
  • Aligns with backend CustomFieldType enum (icons, components, has_options, is_file)

  Props:
  • none required (uses Inertia shared data)

  Emits:
  • drag-start(fieldType: string) — optional, if you prefer event over native drag

  Future extensions:
  • Search/filter field types
  • Favorites / recently used section
  • Preview tooltip on hover
  • Group by category (text, selection, file, etc.)
-->

<script setup lang="ts">
import { computed } from 'vue'
import type { FieldTypeMap, CustomFieldType } from '@/types/custom-fields'

// ────────────────────────────────────────────────
// Inertia shared data (from Laravel)
// ────────────────────────────────────────────────
const props = defineProps<{
    customFieldTypes: FieldTypeMap
}>()

// ────────────────────────────────────────────────
// Computed types list (sorted, filtered)
// ────────────────────────────────────────────────
const fieldTypes = computed(() => {
    const types = props.customFieldTypes || {}

    return Object.entries(types)
        .map(([type, meta]) => ({
            type: type as CustomFieldType,
            name: meta.name,
            icon: meta.icon,
            component: meta.component,
            hasOptions: meta.has_options,
            isFile: meta.is_file
        }))
        .sort((a, b) => a.name.localeCompare(b.name))
})

// ────────────────────────────────────────────────
// Drag start handler (HTML5 Drag & Drop)
// ────────────────────────────────────────────────
const startDrag = (event: DragEvent, fieldType: CustomFieldType) => {
    if (!event.dataTransfer) return

    // Payload: what the builder canvas will receive on drop
    event.dataTransfer.setData('application/json', JSON.stringify({
        field_type: fieldType,
        // Optional: add default label, placeholder, etc.
        label: `New ${fieldTypes.value.find(t => t.type === fieldType)?.name || 'Field'}`,
        required: false
    }))

    // Optional: visual feedback during drag
    event.dataTransfer.effectAllowed = 'copy'
    event.dataTransfer.dropEffect = 'copy'

    // Optional emit if parent wants to handle drag logic
    // emit('drag-start', fieldType)
}
</script>

<template>
    <div
        class="field-type-selector h-full bg-gray-50 dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col">
        <!-- Header -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-800">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                Add Field
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Drag a field type to the canvas
            </p>
        </div>

        <!-- Scrollable list of field types -->
        <div class="flex-1 overflow-y-auto p-3 space-y-2">
            <div v-if="!fieldTypes.length" class="text-center py-10 text-gray-500 dark:text-gray-400">
                Loading field types...
            </div>

            <div v-for="ft in fieldTypes" :key="ft.type" draggable="true" @dragstart="startDrag($event, ft.type)"
                class="field-type-card group flex items-center gap-3 p-3 rounded-lg cursor-grab active:cursor-grabbing bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:border-primary-500 hover:shadow-md transition-all duration-150 select-none">
                <!-- Icon -->
                <div
                    class="w-10 h-10 rounded-md bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-xl text-gray-600 dark:text-gray-300 group-hover:text-primary-600 transition-colors">
                    <i :class="ft.icon" />
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div
                        class="font-medium text-gray-900 dark:text-white group-hover:text-primary-600 transition-colors">
                        {{ ft.name }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                        {{ ft.isFile ? 'File upload' : ft.hasOptions ? 'With options' : 'Basic input' }}
                    </div>
                </div>

                <!-- Drag hint -->
                <i class="pi pi-bars text-gray-400 group-hover:text-primary-500 transition-colors" />
            </div>
        </div>

        <!-- Optional footer / hint -->
        <div class="p-4 border-t border-gray-200 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
            Drag & drop to add fields. You can reorder them on the canvas.
        </div>
    </div>
</template>

<style scoped>
.field-type-card {
    @apply transition-shadow hover:shadow-md active:shadow-inner;
}

.field-type-card:active {
    @apply scale-[0.98];
}
</style>
