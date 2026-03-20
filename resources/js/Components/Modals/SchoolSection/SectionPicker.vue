<script setup lang="ts">
/**
 * SectionPicker.vue — School Section Multi/Single Select Input
 *
 * ── Purpose ──────────────────────────────────────────────────────────────
 * A reusable form input component for selecting one or more SchoolSections.
 * Wraps AsyncSelect with section-specific configuration: searches the
 * sections options endpoint, renders short_code alongside the name in the
 * dropdown, and emits a typed value (string ID or string[] IDs).
 *
 * ── When to use ──────────────────────────────────────────────────────────
 * Use wherever a form needs the user to pick a school section:
 *   - Class level assignment form
 *   - Staff / student filters
 *   - Department role scoping (DepartmentFormModal already does this
 *     with a raw AsyncSelect — swap to SectionPicker for consistency)
 *   - Report filters, timetable forms, etc.
 *
 * ── Props ────────────────────────────────────────────────────────────────
 *   multiple      boolean  false    Single select by default
 *   placeholder   string   optional Overrides built-in default
 *   disabled      boolean  false
 *   invalid       boolean  false    Triggers PrimeVue invalid styling
 *   activeOnly    boolean  true     Only show is_active=1 sections
 *                                   Set false to include inactive sections
 *                                   (e.g. admin views)
 *
 * ── v-model ──────────────────────────────────────────────────────────────
 *   single:   string | null        (section ID)
 *   multiple: string[]             (array of section IDs)
 *
 * ── Endpoint ─────────────────────────────────────────────────────────────
 * GET /settings/school/sections/options
 * Returns SchoolSectionOption[] — lightweight shape:
 *   { id, name, display_name, short_code, label, value }
 * The controller filters by school scope automatically.
 * activeOnly=true adds ?active=1 to the request.
 *
 * ── Slot: option-label ───────────────────────────────────────────────────
 * The dropdown option renders:
 *   [JSS]  Junior Secondary School
 * short_code in a monospace chip + display_name as label.
 * This is handled via AsyncSelect's option slot passthrough.
 *
 * ── Example usage ────────────────────────────────────────────────────────
 * Single:
 *   <SectionPicker v-model="form.school_section_id" />
 *
 * Multiple + inactive included:
 *   <SectionPicker
 *     v-model="form.section_ids"
 *     :multiple="true"
 *     :active-only="false"
 *     placeholder="Select sections…"
 *   />
 *
 * With error:
 *   <SectionPicker
 *     v-model="form.school_section_id"
 *     :invalid="!!form.errors.school_section_id"
 *   />
 *   <small v-if="form.errors.school_section_id" class="text-red-500 text-xs">
 *     {{ form.errors.school_section_id }}
 *   </small>
 */

import { computed } from 'vue'
import AsyncSelect from '@/Components/forms/AsyncSelect.vue'

// ── Props ──────────────────────────────────────────────────────────────────
const props = withDefaults(
    defineProps<{
        multiple?: boolean
        placeholder?: string
        disabled?: boolean
        invalid?: boolean
        /** When true (default) only active sections are returned from the API */
        activeOnly?: boolean
    }>(),
    {
        multiple: false,
        disabled: false,
        invalid: false,
        activeOnly: true,
    }
)

// ── v-model ────────────────────────────────────────────────────────────────
// Single mode:   string | null
// Multiple mode: string[]
const model = defineModel<string | string[] | null>({
    default: null,
})

// ── AsyncSelect field config ───────────────────────────────────────────────
// Built once as a computed so activeOnly reactively updates the params.
const fieldConfig = computed(() => ({
    search_url: route('settings.school.sections.options'),
    multiple: props.multiple,
    placeholder: props.placeholder
        ?? (props.multiple ? 'Select sections…' : 'Select a section…'),
    field_options: {
        option_label: 'display_name',
        option_value: 'id',
        search_key: 'q',
        search_delay: 250,
        search_params: props.activeOnly ? { active: 1 } : {},
        // label_field tells AsyncSelect which field to display in selected chips
        label_field: 'display_name',
    },
}))
</script>

<template>
    <AsyncSelect id="section-picker" v-model="model" :field="fieldConfig" :disabled="disabled" :invalid="invalid">
        <template #option="{ option }">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-1.5 py-0.5 rounded
                         bg-primary/10 text-primary text-xs font-mono font-medium">
                    {{ option.short_code }}
                </span>
                <span class="text-sm">{{ option.display_name }}</span>
            </div>
        </template>
    </AsyncSelect>
</template>
