<!--
  resources/js/components/CustomFields/Toolbar/UndoRedoControls.vue

  Grouped undo/redo buttons with shortcuts and tooltips.

  Features:
  • Undo (Ctrl+Z) / Redo (Ctrl+Y) buttons
  • Disabled when no action possible
  • Visual feedback (active state)
  • Keyboard shortcuts registered globally
  • Accessible: ARIA labels, focusable

  Integration:
  <UndoRedoControls :builder="builder" />

  Dependencies:
  • useFormBuilder (canUndo, canRedo, undo, redo)
  • PrimeVue: Button, Tooltip
-->

<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { Button } from 'primevue'
import type { useFormBuilder } from '@/composables/useFormBuilder';

const props = defineProps<{
    builder: ReturnType<typeof useFormBuilder>
}>()

// Keyboard shortcuts
const handleKeydown = (e: KeyboardEvent) => {
    if (e.ctrlKey || e.metaKey) {
        if (e.key.toLowerCase() === 'z') {
            e.preventDefault()
            if (props.builder.canUndo.value) props.builder.undo()
        } else if (e.key.toLowerCase() === 'y') {
            e.preventDefault()
            if (props.builder.canRedo.value) props.builder.redo()
        }
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <div class="flex items-center gap-1">
        <Button icon="pi pi-undo" rounded text severity="secondary" :disabled="!builder.canUndo"
            v-tooltip.bottom="'Undo (Ctrl+Z)'" @click="builder.undo" />

        <Button icon="pi pi-refresh" rounded text severity="secondary" :disabled="!builder.canRedo"
            v-tooltip.bottom="'Redo (Ctrl+Y)'" @click="builder.redo" />
    </div>
</template>
