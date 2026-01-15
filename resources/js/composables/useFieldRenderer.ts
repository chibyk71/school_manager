// resources/js/composables/useFieldRenderer.ts
/**
 * useFieldRenderer.ts
 *
 * Central mapping composable that resolves a CustomField.field_type
 * to the appropriate PrimeVue input component (or custom renderer).
 *
 * Purpose & responsibilities:
 * ────────────────────────────────────────────────────────────────
 * • Single source of truth for field_type → component mapping
 * • Prevents scattered switch/if-else logic in renderers/forms
 * • Provides type-safe resolution with fallback
 * • Allows easy addition of new field types (just add to map)
 * • Supports optional wrapper/component overrides per type
 * • Returns component + optional props/config for that type
 *
 * Why this exists:
 * • Avoids huge v-if chains in CustomFieldRenderer.vue
 * • Makes adding new field types (e.g. rich-text, rating, OTP) trivial
 * • Enables future optimizations (lazy loading of heavy components)
 * • Keeps DynamicForm & CustomFieldRenderer clean & focused
 *
 * Integration:
 * • Used inside CustomFieldRenderer.vue
 * • Example:
 *   const { getComponent, getDefaultProps } = useFieldRenderer()
 *   const Comp = getComponent(field.field_type)
 *   <component :is="Comp" v-bind="getDefaultProps(field)" ... />
 *
 * Extensibility:
 * • Add new entries to componentMap when supporting new types
 * • For complex fields (file with preview, editor, map), use a dedicated .vue
 *   component instead of raw PrimeVue (already done for FileUpload)
 *
 * Current supported mappings (aligned with backend CustomFieldType enum):
 *   text, email, tel, url, password → InputText
 *   number → InputNumber
 *   textarea → Textarea
 *   select → Dropdown
 *   multiselect → MultiSelect
 *   radio → custom radio group (in renderer)
 *   checkbox → custom checkbox group (in renderer)
 *   boolean → ToggleSwitch
 *   date, datetime → Calendar
 *   color → ColorPicker
 *   file, image → FileUpload (with custom handling)
 *
 * Tech notes:
 * • Uses defineAsyncComponent for potential lazy-loading of heavy components
 * • Returns raw component reference (Vue 3 compatible)
 * • No side-effects — pure mapping function
 */

import { computed, shallowRef, type Component } from 'vue'
import {
    InputText,
    InputNumber,
    Textarea,
    Dropdown,
    MultiSelect,
    ToggleSwitch,
    Calendar,
    ColorPicker,
    FileUpload
} from 'primevue'
import type { CustomField, CustomFieldType } from '@/types/custom-fields'

// ────────────────────────────────────────────────
// Main mapping table
// ────────────────────────────────────────────────
// Key = field_type (exact match with backend enum)
// Value = { component, defaultProps? (optional merge) }
const componentMap = shallowRef<
    Partial<
        Record<
            CustomFieldType,
            {
                component: Component
                defaultProps?: (field: CustomField) => Record<string, any>
            }
        >
    >
>({
    // Text-based
    text: { component: InputText },
    email: { component: InputText },
    tel: { component: InputText },
    url: { component: InputText },
    password: { component: InputText },

    // Number
    number: {
        component: InputNumber,
        defaultProps: (field) => ({
            min: field.extra_attributes?.min,
            max: field.extra_attributes?.max,
            step: field.extra_attributes?.step ?? 1,
            locale: 'en-US', // or from app locale
            mode: 'decimal'
        })
    },

    // Textarea
    textarea: {
        component: Textarea,
        defaultProps: (field) => ({
            rows: field.extra_attributes?.rows ?? 4,
            autoResize: true
        })
    },

    // Selection
    select: {
        component: Dropdown,
        defaultProps: (field) => ({
            optionLabel: 'label',
            optionValue: 'value',
            filter: field.options && field.options.length > 10,
            showClear: true,
            virtualScrollerOptions: { itemSize: 38 }
        })
    },

    multiselect: {
        component: MultiSelect,
        defaultProps: (field) => ({
            optionLabel: 'label',
            optionValue: 'value',
            display: 'chip',
            filter: true,
            showClear: true
        })
    },

    // Boolean (ToggleSwitch preferred over plain Checkbox)
    boolean: { component: ToggleSwitch },

    // Date / Time
    date: {
        component: Calendar,
        defaultProps: () => ({
            showIcon: true,
            showButtonBar: true,
            dateFormat: 'dd/mm/yy',
            selectionMode: 'date'
        })
    },

    datetime: {
        component: Calendar,
        defaultProps: () => ({
            showIcon: true,
            showButtonBar: true,
            dateFormat: 'dd/mm/yy',
            selectionMode: 'dateTime',
            hourFormat: '24'
        })
    },

    // Color
    color: { component: ColorPicker },

    // File / Image (uses custom FileUpload with preview logic)
    file: { component: FileUpload },
    image: { component: FileUpload }
})

/**
 * Main composable hook
 */
export function useFieldRenderer() {
    /**
     * Get the component for a given field_type
     * @returns PrimeVue component or fallback
     */
    const getComponent = (fieldType: CustomFieldType | string): Component => {
        const entry = componentMap.value[fieldType as CustomFieldType]

        if (entry?.component) {
            return entry.component
        }

        // Fallback for unknown types
        console.warn(`[useFieldRenderer] No component mapped for type: ${fieldType}`)
        return InputText // safe default
    }

    /**
     * Get any default props/config specific to this field type
     * These are merged into the component in CustomFieldRenderer
     */
    const getDefaultProps = (field: CustomField): Record<string, any> => {
        const entry = componentMap.value[field.field_type as CustomFieldType]
        if (entry?.defaultProps) {
            return entry.defaultProps(field)
        }
        return {}
    }

    /**
     * Check if a field type should use custom group rendering
     * (radio, checkbox — handled in CustomFieldRenderer with fieldset)
     */
    const isGroupField = computed(() => (fieldType: string) =>
        ['radio', 'checkbox'].includes(fieldType)
    )

    /**
     * Check if field needs special file handling
     */
    const isFileType = computed(() => (fieldType: string) =>
        ['file', 'image'].includes(fieldType)
    )

    return {
        getComponent,
        getDefaultProps,
        isGroupField,
        isFileType
    }
}
