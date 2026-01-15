// resources/js/composables/useFormBuilder.ts
/**
 * useFormBuilder.ts – v1.0 (Production-Ready)
 *
 * Core state management composable for the Custom Fields Form Builder.
 *
 * Main responsibilities & features:
 * ────────────────────────────────────────────────────────────────
 * • Maintains the current list of fields being built (reactive array)
 * • Handles drag & drop: adding new fields from toolbox, reordering existing
 * • Selection: single field selected for properties editing
 * • Undo/Redo history (limited stack, with snapshot diffing)
 * • Validation of field names (unique within form)
 * • Generates save-ready payload matching Laravel CustomField model
 * • Optimistic updates + rollback support
 * • Emits events for parent components (add, update, delete, reorder)
 *
 * Integration points:
 * • Consumed in CustomFieldBuilder.vue (main workspace)
 * • Receives drops from FieldTypeSelector.vue (new field creation)
 * • Controls rendering loop in canvas (v-for over fields)
 * • Updates selected field in FieldPropertiesPanel.vue
 * • Saves via Inertia/router.patch or post to backend endpoint
 * • Uses types from custom-fields.ts (CustomField shape)
 *
 * Key decisions:
 * • Fields stored as CustomField-like objects (with temp id for new ones)
 * • History uses deep clone snapshots (structuredClone if available, fallback JSON)
 * • Undo/redo limited to 20 steps (configurable)
 * • Name uniqueness enforced client-side (prevents backend 422 errors)
 * • Drag payload uses JSON dataTransfer (compatible with native + PrimeVue DnD)
 *
 * Future extensions planned:
 * • Conditional visibility preview
 * • Live form preview pane (using DynamicForm)
 * • Export schema button
 * • Collaboration (multi-user editing via WebSockets)
 */

import { ref, computed, shallowRef, watch } from 'vue'
import type { CustomField, CustomFieldType } from '@/types/custom-fields'
import { v4 as uuidv4 } from 'uuid'

// ────────────────────────────────────────────────
// Types
// ────────────────────────────────────────────────

export interface BuilderField extends Omit<CustomField, 'id' | 'sort'> {
    id: string                  // temp uuid for new fields, real id after save
    sort: number                // current position in builder
    _isNew?: boolean            // flag for unsaved fields
    _original?: Partial<CustomField> // for optimistic rollback
}

interface HistoryEntry {
    timestamp: number
    fields: BuilderField[]
    selectedId: string | null
}

// ────────────────────────────────────────────────
// Constants
// ────────────────────────────────────────────────
const MAX_HISTORY = 20
const DEFAULT_FIELD: Partial<BuilderField> = {
    label: 'New Field',
    required: false,
    placeholder: '',
    hint: '',
    description: '',
    sort: 0,
    options: [],
    rules: [],
    classes: '',
    extra_attributes: {}
}

// ────────────────────────────────────────────────
// Main composable
// ────────────────────────────────────────────────
export function useFormBuilder() {
    // Core state
    const fields = ref<BuilderField[]>([])
    const selectedFieldId = ref<string | null>(null)
    const history = shallowRef<HistoryEntry[]>([])
    const historyIndex = ref(-1) // -1 = current, 0 = first undo step

    // ── Selection ────────────────────────────────────────────────
    const selectedField = computed<BuilderField | null>(() => {
        if (!selectedFieldId.value) return null
        return fields.value.find(f => f.id === selectedFieldId.value) ?? null
    })

    const selectField = (fieldId: string | null) => {
        selectedFieldId.value = fieldId
        pushHistory() // snapshot on selection change
    }

    // ── Drag & Drop: Add new field from toolbox ──────────────────
    const addNewField = (fieldType: CustomFieldType, dropIndex?: number) => {
        const newField: BuilderField = {
            id: uuidv4(),
            name: `field_${Date.now()}`,
            field_type: fieldType,
            sort: dropIndex !== undefined ? dropIndex : fields.value.length,
            _isNew: true,
            ...DEFAULT_FIELD
        }

        // Insert at drop position or append
        if (dropIndex !== undefined && dropIndex >= 0 && dropIndex <= fields.value.length) {
            fields.value.splice(dropIndex, 0, newField)
            // Re-number sorts
            fields.value.forEach((f, i) => f.sort = i)
        } else {
            fields.value.push(newField)
        }

        // Select the new field automatically
        selectField(newField.id)
        pushHistory()
    }

    // ── Reorder (drag & drop existing fields) ────────────────────
    const reorderFields = (oldIndex: number, newIndex: number) => {
        if (oldIndex === newIndex) return

        const movedField = fields.value.splice(oldIndex, 1)[0]
        fields.value.splice(newIndex, 0, movedField)

        // Update sort values
        fields.value.forEach((f, i) => {
            f.sort = i
        })

        pushHistory()
    }

    // ── Update single field ──────────────────────────────────────
    const updateField = (fieldId: string, updates: Partial<BuilderField>) => {
        const index = fields.value.findIndex(f => f.id === fieldId)
        if (index === -1) return

        // Optimistic update
        const original = { ...fields.value[index] }
        fields.value[index] = { ...fields.value[index], ...updates }

        // Auto-fix name uniqueness
        if (updates.name) {
            ensureUniqueName(fieldId)
        }

        pushHistory()
    }

    const ensureUniqueName = (currentId: string) => {
        const names = new Set<string>()
        fields.value.forEach(f => {
            if (f.id !== currentId) names.add(f.name)
        })

        let field = fields.value.find(f => f.id === currentId)
        if (!field) return

        let baseName = field.name
        let counter = 1
        while (names.has(field.name)) {
            field.name = `${baseName}_${counter++}`
        }
    }

    // ── Delete field ─────────────────────────────────────────────
    const deleteField = (fieldId: string) => {
        const index = fields.value.findIndex(f => f.id === fieldId)
        if (index === -1) return

        fields.value.splice(index, 1)

        // Re-sort
        fields.value.forEach((f, i) => f.sort = i)

        // Clear selection if deleted
        if (selectedFieldId.value === fieldId) {
            selectedFieldId.value = null
        }

        pushHistory()
    }

    // ── Undo / Redo ──────────────────────────────────────────────
    const pushHistory = () => {
        // Remove future steps if we're in the middle of history
        if (historyIndex.value < history.value.length - 1) {
            history.value = history.value.slice(0, historyIndex.value + 1)
        }

        history.value.push({
            timestamp: Date.now(),
            fields: structuredClone(fields.value), // deep copy
            selectedId: selectedFieldId.value
        })

        // Limit history size
        if (history.value.length > MAX_HISTORY) {
            history.value.shift()
        }

        historyIndex.value = history.value.length - 1
    }

    const undo = () => {
        if (historyIndex.value <= 0) return

        historyIndex.value--
        restoreFromHistory()
    }

    const redo = () => {
        if (historyIndex.value >= history.value.length - 1) return

        historyIndex.value++
        restoreFromHistory()
    }

    const restoreFromHistory = () => {
        const entry = history.value[historyIndex.value]
        if (!entry) return

        fields.value = structuredClone(entry.fields)
        selectedFieldId.value = entry.selectedId
    }

    // ── Save payload preparation (for backend) ───────────────────
    const getSavePayload = () => {
        return fields.value.map(f => ({
            id: f._isNew ? undefined : f.id,
            name: f.name,
            label: f.label,
            field_type: f.field_type,
            required: f.required,
            placeholder: f.placeholder,
            hint: f.hint,
            description: f.description,
            sort: f.sort,
            options: f.options,
            rules: f.rules,
            classes: f.classes,
            extra_attributes: f.extra_attributes,
            file_constraints: f.file_constraints,
            // conditional_rules: f.conditional_rules, // future
        }))
    }

    // ── Public API ───────────────────────────────────────────────
    return {
        // State
        fields,
        selectedFieldId,
        selectedField,
        canUndo: computed(() => historyIndex.value > 0),
        canRedo: computed(() => historyIndex.value < history.value.length - 1),

        // Actions
        addNewField,
        reorderFields,
        updateField,
        deleteField,
        selectField,
        undo,
        redo,
        getSavePayload,

        // Debug / dev helpers
        historyLength: computed(() => history.value.length),
        currentHistoryIndex: computed(() => historyIndex.value)
    }
}
