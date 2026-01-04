<script setup lang="ts" generic="T extends Record<string, any>">
import { computed, ref, watch } from 'vue'
import {
    Button,
    IconField,
    InputIcon,
    InputText,
    MultiSelect,
    Checkbox,
    useConfirm,
    useToast
} from 'primevue'
import type { BulkAction, ColumnDefinition } from '@/types/datatables'
import { debounce } from 'lodash'

const props = defineProps<{
    selectedRows: T[]
    bulkActions?: BulkAction[]
    columns: ColumnDefinition<T>[]
    refreshing?: boolean
}>()

const emit = defineEmits<{
    (e: 'refresh'): void
    (e: 'update:globalSearch', value: string): void
    (e: 'update:hiddenColumns', value: string[]): void
}>()

const confirm = useConfirm()
const toast = useToast()

// v-model bindings
const globalSearch = defineModel<string>('globalSearch', { default: '' })
const hiddenColumns = defineModel<string[]>('hiddenColumns', { default: () => [] })

// Loading state per action (for async handlers)
const loadingActions = ref<Set<string>>(new Set())

// Toggleable columns (exclude frozen or special fields)
const toggleableColumns = computed(() =>
    props.columns.filter(
        col => !col.frozen && !['id', 'actions'].includes(String(col.field))
    )
)

// Safe bulk actions
const effectiveBulkActions = computed(() =>
    Array.isArray(props.bulkActions) ? props.bulkActions : []
)

// Visible bulk actions based on selection or custom visibility
const visibleBulkActions = computed(() =>
    effectiveBulkActions.value.filter(action =>
        action.visible
            ? action.visible(props.selectedRows)
            : props.selectedRows.length > 0
    )
)

// Debounced global search
const updateSearch = debounce((value: string) => {
    emit('update:globalSearch', value.trim())
}, 400)

watch(globalSearch, (val) => updateSearch(val), { immediate: true })

// Handle bulk action execution
const handleBulkAction = async (action: BulkAction) => {
    const selected = props.selectedRows
    if (selected.length === 0 && !action.visible?.(selected)) return

    // If local handler exists → use it (preferred)
    if (action.handler) {
        const handler = action.handler
        if (action.confirm) {
            confirm.require({
                message:
                    typeof action.confirm.message === 'function'
                        ? action.confirm.message(selected)
                        : action.confirm.message,
                header: action.confirm.header ?? 'Confirm Action',
                icon: action.confirm.icon ?? 'pi pi-exclamation-triangle',
                acceptLabel: action.confirm.acceptLabel ?? 'Yes',
                rejectLabel: action.confirm.rejectLabel ?? 'Cancel',
                acceptClass: action.confirm.acceptClass ?? 'p-button-danger',
                rejectClass: 'p-button-secondary p-button-outlined',
                accept: async () => {
                    await executeHandler(handler)
                }
            })
        } else {
            await executeHandler(handler)
        }
    }
}

// Helper to execute local handler safely
const executeHandler = async (handler: (rows: any[]) => void | Promise<void>) => {
    const actionKey = (props.bulkActions || []).find(a => a.handler === handler)?.action || 'unknown'
    loadingActions.value.add(actionKey)
    try {
        await handler(props.selectedRows)
        toast.add({
            severity: 'success',
            summary: 'Action completed',
            life: 3000
        })
    } catch (err: any) {
        toast.add({
            severity: 'error',
            summary: 'Action failed',
            detail: err.message || 'Unknown error',
            life: 5000
        })
    } finally {
        loadingActions.value.delete(actionKey)
    }
}
</script>

<template>
    <div
        class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 py-4 px-6 border-b border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900">
        <!-- Left: Selection Info + Bulk Actions -->
        <div class="flex flex-wrap items-center gap-4">
            <!-- Selection Badge -->
            <transition name="fade-scale">
                <div v-if="selectedRows.length"
                    class="px-4 py-2 text-sm font-semibold rounded-full bg-primary/10 text-primary border border-primary/30">
                    {{ selectedRows.length }} selected
                </div>
            </transition>
        </div>

        <!-- Right: Controls -->
        <div class="flex items-center gap-3 w-full md:w-auto">
            <!-- Global Search -->
            <IconField icon-position="left" class="flex-1 md:flex-initial">
                <InputIcon class="pi pi-search text-surface-500" />
                <InputText v-model="globalSearch" placeholder="Search all columns..."
                    class="w-full md:w-80 h-11 text-sm" show-clear @clear="globalSearch = ''" />
            </IconField>

            <!-- Column Visibility -->
            <MultiSelect v-model="hiddenColumns" :options="toggleableColumns" option-label="header" option-value="field"
                placeholder="Columns" display="chip" :max-selected-labels="4" class="w-full md:w-64 text-sm" filter>
                <template #header>
                    <div
                        class="px-4 py-3 text-xs font-medium text-surface-600 dark:text-surface-300 border-b border-surface-200 dark:border-surface-700">
                        Visible Columns
                    </div>
                </template>

                <template #option="{ option }">
                    <div class="flex items-center gap-3 py-2">
                        <Checkbox :model-value="!hiddenColumns.includes(option.field)" :input-id="`col-${option.field}`"
                            binary disabled />
                        <label :for="`col-${option.field}`" class="text-sm cursor-pointer">
                            {{ option.header }}
                        </label>
                    </div>
                </template>

                <template #footer>
                    <div class="px-4 py-2 text-xs text-surface-500">
                        {{ toggleableColumns.length - hiddenColumns.length }} of {{ toggleableColumns.length }} visible
                    </div>
                </template>
            </MultiSelect>

            <!-- Refresh -->
            <Button icon="pi pi-refresh" @click="emit('refresh')" rounded text severity="secondary" size="small"
                class="h-11 w-11" :loading="refreshing" aria-label="Refresh table" />
        </div>
    </div>
    <div
        class="w-full flex items-center justify-between px-6 py-3 bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
        <!-- Bulk Actions -->
        <transition name="fade">
            <div v-if="visibleBulkActions.length" class="flex flex-wrap items-center gap-2">
                <Button v-for="action in visibleBulkActions" :key="action.action"
                    :label="selectedRows.length ? `${action.label} (${selectedRows.length})` : action.label"
                    :icon="action.icon" size="small" :severity="action.severity || 'secondary'"
                    :outlined="!selectedRows.length" :loading="loadingActions.has(action.action)"
                    :disabled="selectedRows.length === 0 && !action.visible?.(selectedRows)"
                    @click="handleBulkAction(action)" class="transition-all duration-200" />
            </div>
        </transition>
    </div>
</template>

<style scoped lang="postcss">
.fade-scale-enter-active,
.fade-scale-leave-active {
    transition: all 0.2s ease;
}

.fade-scale-enter-from,
.fade-scale-leave-to {
    opacity: 0;
    transform: scale(0.95) translateY(-4px);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

:deep(.p-button:hover:not(:disabled)) {
    @apply shadow-md ring-2 ring-primary/20;
}

:deep(.p-multiselect-panel .p-multiselect-item) {
    @apply py-2.5;
}
</style>
