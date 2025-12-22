<!-- resources/js/composables/datatable/DataTableHeader.vue -->
<script setup lang="ts" generic="T extends Record<string, any>">
import { computed, watch } from 'vue'
import { Button, IconField, InputIcon, InputText, MultiSelect, Checkbox } from 'primevue'
import type { BulkAction, ColumnDefinition } from '@/types/datatables'
import { debounce } from 'lodash';

const props = defineProps<{
    /** Currently selected rows */
    selectedRows: T[]

    /** Bulk actions configuration */
    bulkActions?: BulkAction[]

    /** All column definitions */
    columns: ColumnDefinition<T>[]

    /** Is table currently refreshing/loading? */
    refreshing?: boolean
}>()

const emit = defineEmits<{
    (e: 'refresh'): void
    (e: 'update:globalSearch', value: string): void
    (e: 'update:hiddenColumns', value: string[]): void
    (e: 'bulk-action', action: string, selected: any[]): void
}>()

// v-model bindings
const globalSearch = defineModel<string>('globalSearch', { default: '' })
const hiddenColumns = defineModel<string[]>('hiddenColumns', { default: () => [] })

// Columns available for toggling (exclude special ones)
const toggleableColumns = computed(() =>
    props.columns.filter(col => !col.frozen && !['id', 'actions'].includes(String(col.field)))
)

// Safe bulk actions
const effectiveBulkActions = computed(() =>
    Array.isArray(props.bulkActions) ? props.bulkActions : []
)

// Show bulk actions only when rows are selected OR action is always visible
const visibleBulkActions = computed(() =>
    effectiveBulkActions.value.filter(action =>
        action.visible ? action.visible(props.selectedRows) : props.selectedRows.length > 0
    )
)

// Debounced global search → emits update
const updateSearch = debounce((value: string) => {
    emit('update:globalSearch', value.trim())
}, 400)

// Watch immediate to catch external resets
watch(globalSearch, (newVal) => updateSearch(newVal), { immediate: true })

// Handle bulk action click
const handleBulkAction = (action: BulkAction) => {
    if (props.selectedRows.length === 0 && !action.visible?.(props.selectedRows)) return

    // Optional confirmation dialog
    if (action.confirm) {
        // You can replace this with PrimeVue ConfirmDialog service
        const confirmed = window.confirm(action.confirm.message)
        if (!confirmed) return
    }

    emit('bulk-action', action.action, props.selectedRows)
}
</script>

<template>
    <div
        class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 p-5 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
        <!-- Left: Selection + Bulk Actions -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Selection Badge -->
            <transition name="fade">
                <span v-if="selectedRows.length"
                    class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-full bg-primary/10 text-primary border border-primary/20 animate-in fade-in duration-200">
                    {{ selectedRows.length }} selected
                </span>
            </transition>

            <!-- Bulk Action Buttons -->
            <transition>
                <transition-group name="fade" tag="div" class="flex flex-wrap items-center gap-2">
                    <Button v-for="action in visibleBulkActions" :key="action.action"
                        :label="selectedRows.length ? `${action.label} (${selectedRows.length})` : action.label"
                        :icon="action.icon" size="small" :severity="action.severity || 'secondary'"
                        :outlined="!selectedRows.length"
                        :disabled="selectedRows.length === 0 && !action.visible?.(selectedRows)"
                        @click="handleBulkAction(action)" class="font-medium transition-all duration-200"
                        :aria-label="action.label" />
                </transition-group>
            </transition>
        </div>

        <!-- Right: Search + Column Toggle + Refresh -->
        <div class="flex items-center gap-3 w-full md:w-auto">
            <!-- Global Search -->
            <IconField icon-position="left" class="flex-1 md:flex-initial">
                <InputIcon class="text-gray-500">
                    <i class="pi pi-search" />
                </InputIcon>
                <InputText v-model="globalSearch" placeholder="Search all columns..."
                    class="w-full md:w-80 h-11 text-sm" :aria-label="'Global search'" show-clear
                    @clear="globalSearch = ''" />
            </IconField>

            <!-- Column Visibility Toggle -->
            <MultiSelect v-model="hiddenColumns" :options="toggleableColumns" option-label="header" option-value="field"
                placeholder="Columns" display="chip" :max-selected-labels="4" class="w-full md:w-64 text-sm" filter
                :aria-label="'Toggle column visibility'">
                <template #header>
                    <div class="px-4 py-2 text-xs font-medium text-gray-600 dark:text-gray-400 border-b">
                        Visible Columns
                    </div>
                </template>

                <template #option="slotProps">
                    <div class="flex items-center gap-3 py-1">
                        <Checkbox :model-value="!hiddenColumns.includes(slotProps.option.field)"
                            :input-id="`col-${slotProps.option.field}`" binary disabled />
                        <label :for="`col-${slotProps.option.field}`" class="cursor-pointer text-sm">
                            {{ slotProps.option.header }}
                        </label>
                    </div>
                </template>

                <template #footer>
                    <div class="px-4 py-2 text-xs text-gray-500">
                        {{ toggleableColumns.length - hiddenColumns.length }} of {{ toggleableColumns.length }} visible
                    </div>
                </template>
            </MultiSelect>

            <!-- Refresh Button -->
            <Button icon="pi pi-refresh" @click="emit('refresh')" rounded text severity="secondary" size="small"
                class="h-11 w-11" :loading="refreshing" aria-label="Refresh table data" />
        </div>
    </div>
</template>

<style scoped lang="postcss">
/* Smooth transitions */
.fade-enter-active,
.fade-leave-active {
    transition: all 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

/* Hover polish */
:deep(.p-button:hover) {
    @apply shadow-md;
}

:deep(.p-multiselect-panel .p-multiselect-items .p-multiselect-item) {
    @apply py-2;
}
</style>
