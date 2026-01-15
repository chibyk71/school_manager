<!--
  resources/js/components/CustomFields/Toolbar/ExportSchemaButton.vue

  Exports the current builder state as a clean JSON schema file.

  Features / Problems solved:
  • One-click export of the entire form structure (fields, order, settings)
  • Matches backend CustomField shape + frontend extras (sort, temp ids removed)
  • Generates valid filename with timestamp + resource name
  • Shows success toast on download
  • Disabled when no fields exist
  • Accessible: keyboard focus, ARIA label

  Integration:
  • Drop into toolbar: <ExportSchemaButton :builder="builder" :resource="resource" />

  Props:
  • builder — useFormBuilder instance (provides fields & getSavePayload)
  • resource — current model name (Student, Teacher, etc.)

  Dependencies:
  • useFormBuilder (getSavePayload)
  • PrimeVue: Button, Tooltip
-->

<script setup lang="ts">
import { computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import { Button } from 'primevue'
import type { useFormBuilder } from '@/composables/useFormBuilder'

const props = defineProps<{
    builder: ReturnType<typeof useFormBuilder>
    resource: string
}>()

const toast = useToast()

const isDisabled = computed(() => props.builder.fields.value.length === 0)

const exportSchema = () => {
    if (isDisabled.value) return

    const payload = props.builder.getSavePayload()

    const schema = {
        resource: props.resource,
        generated_at: new Date().toISOString(),
        total_fields: payload.length,
        fields: payload.map(f => ({
            ...f,
            // Clean up builder-specific props
            id: undefined,
            _isNew: undefined,
            _original: undefined
        }))
    }

    const blob = new Blob([JSON.stringify(schema, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)

    const link = document.createElement('a')
    link.href = url
    link.download = `custom-fields-${props.resource.toLowerCase()}-${new Date().toISOString().split('T')[0]}.json`
    link.click()

    URL.revokeObjectURL(url)

    toast.add({
        severity: 'success',
        summary: 'Exported',
        detail: `Schema saved as ${link.download}`,
        life: 4000
    })
}
</script>

<template>
    <Button label="Export Schema" icon="pi pi-download" outlined severity="secondary" :disabled="isDisabled"
        v-tooltip.bottom="'Download current form structure as JSON'" @click="exportSchema" />
</template>
