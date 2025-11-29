<!-- resources/js/Components/shared/DataTableHeader.vue -->
<template>
    <div
        class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 p-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
        <!-- Left: Bulk Actions -->
        <div class="flex flex-wrap gap-2">
            <Button v-for="action in bulkActions" :key="action.label" :label="action.label" :icon="action.icon"
                size="small" :severity="action.severity || 'secondary'" :outlined="action.outlined ?? true"
                :disabled="!selectedRows?.length" @click="action.handler(selectedRows)"
                class="transition-all duration-200" />

            <!-- Optional: Show selection count -->
            <span v-if="selectedRows?.length"
                class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full bg-primary/10 text-primary">
                {{ selectedRows.length }} selected
            </span>
        </div>

        <!-- Right: Search + Column Toggle + Refresh -->
        <div class="flex items-center gap-3 w-full md:w-auto">
            <!-- Global Search -->
            <IconField iconPosition="left" class="w-full md:w-72">
                <InputIcon>
                    <i class="pi pi-search text-gray-500" />
                </InputIcon>
                <InputText v-model="globalSearch" placeholder="Search all columns..." class="w-full h-10 text-sm"
                    @input="debouncedSearch" />
            </IconField>

            <!-- Column Visibility Toggle -->
            <MultiSelect v-model="hiddenColumns" :options="visibleColumnOptions" optionLabel="header"
                optionValue="field" placeholder="Columns" display="chip" :maxSelectedLabels="3"
                class="w-full md:w-56 text-sm" filter>
                <template #header>
                    <div class="px-3 py-2 text-xs font-medium text-gray-600 dark:text-gray-400">
                        Toggle Columns
                    </div>
                </template>
                <template #option="slotProps">
                    <div class="flex items-center gap-2">
                        <Checkbox :modelValue="!hiddenColumns.includes(slotProps.option.field)"
                            :inputId="slotProps.option.field" binary disabled />
                        <label :for="slotProps.option.field" class="cursor-pointer">
                            {{ slotProps.option.header }}
                        </label>
                    </div>
                </template>
            </MultiSelect>

            <!-- Refresh Button -->
            <Button icon="pi pi-refresh" @click="emit('refresh')" rounded text severity="secondary" size="small"
                class="h-10 w-10" :loading="refreshing" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import debounce from 'lodash/debounce'
import type { ColumnDefinition } from '@/types'

interface BulkAction {
    label: string
    icon?: string
    handler: (selected: any[]) => void
    severity?: 'primary' | 'secondary' | 'success' | 'info' | 'warning' | 'danger' | 'contrast'
    outlined?: boolean
}

const props = defineProps<{
    selectedRows: any[]
    bulkActions?: BulkAction[]
    columns: ColumnDefinition[]
    refreshing?: boolean
}>()

const emit = defineEmits<{
    (e: 'refresh'): void
    (e: 'update:globalSearch', value: string): void
    (e: 'update:hiddenColumns', value: string[]): void
}>()

// Two-way bindable props
const globalSearch = defineModel<string>('globalSearch', { default: '' })
const hiddenColumns = defineModel<string[]>('hiddenColumns', { default: () => [] })

// Only show columns that are hideable (not system-critical)
const visibleColumnOptions = computed(() =>
    props.columns.filter(col => col.field !== 'id' && col.field !== 'actions')
)

// Debounced search
const debouncedSearch = debounce(() => {
    // Emits automatically via v-model
}, 400)

// Optional: auto-clear search when no results (advanced UX)
watch(
    () => props.selectedRows,
    () => {
        if (props.selectedRows.length === 0) {
            // Optionally auto-clear selection badge on refresh
        }
    }
)
</script>

<style scoped>
:deep(.p-multiselect-panel .p-multiselect-items) {
    @apply max-h-64;
}

:deep(.p-button.p-button-outlined:hover) {
    @apply bg-primary/5 border-primary text-primary;
}
</style>