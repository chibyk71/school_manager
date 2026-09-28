<script setup lang="ts" generic="T extends Record<string, any>">
/**
 * DataTable header: search, columns, bulk actions.
 * Selection count and bulk-action visibility MUST use canonical selection
 * (cross-page), not PrimeVue page-local selectedRows alone.
 */
import { computed, ref, watch } from 'vue'
import {
    Button,
    IconField,
    InputIcon,
    InputText,
    MultiSelect,
    Checkbox,
    useConfirm,
    useToast,
} from 'primevue'
import type { BulkAction, ColumnDefinition } from '@/types/datatables'
import { debounce } from 'lodash'

const props = defineProps<{
    /** Page-local PrimeVue rows (visual only). */
    selectedRows: T[]
    /**
     * Canonical selection count:
     * - number of explicit IDs across pages, or
     * - -1 for query selection (all matching), or
     * - 0 when nothing selected
     */
    selectionCount?: number
    /** True when any canonical selection exists (ids or query). */
    hasCanonicalSelection?: boolean
    /** Label for selection badge (e.g. "3 selected", "All matching selected"). */
    selectionLabel?: string
    bulkActions?: BulkAction<T>[]
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

const globalSearch = defineModel<string>('globalSearch', { default: '' })
const hiddenColumns = defineModel<string[]>('hiddenColumns', { default: () => [] })

const loadingActions = ref<Set<string>>(new Set())

const toggleableColumns = computed(() =>
    props.columns.filter((col) => !col.frozen && !['id', 'actions'].includes(String(col.field))),
)

const effectiveBulkActions = computed(() =>
    Array.isArray(props.bulkActions) ? props.bulkActions : [],
)

/** Canonical selection is the source of truth for bulk-action availability. */
const hasSelection = computed(() => {
    if (props.hasCanonicalSelection !== undefined) {
        return props.hasCanonicalSelection
    }
    // Legacy fallback only for non-Phase-6 consumers that only pass selectedRows
    return props.selectedRows.length > 0
})

const displaySelectionLabel = computed(() => {
    if (props.selectionLabel) return props.selectionLabel
    const n = props.selectionCount
    if (typeof n === 'number' && n > 0) return `${n} selected`
    if (n === -1) return 'All matching selected'
    if (props.selectedRows.length > 0) return `${props.selectedRows.length} selected`
    return ''
})

const visibleBulkActions = computed(() =>
    effectiveBulkActions.value.filter((action) => {
        if (typeof action.visible === 'function') {
            return action.visible(props.selectedRows)
        }
        if (typeof action.visible === 'boolean') {
            return action.visible && hasSelection.value
        }
        return hasSelection.value
    }),
)

const selectionCountForLabel = computed(() => {
    if (typeof props.selectionCount === 'number' && props.selectionCount > 0) {
        return props.selectionCount
    }
    if (props.selectionCount === -1) return null
    return props.selectedRows.length || null
})

const updateSearch = debounce((value: string) => {
    emit('update:globalSearch', value.trim())
}, 400)

watch(globalSearch, (val) => updateSearch(val), { immediate: true })

const handleBulkAction = async (action: BulkAction<T>) => {
    if (!hasSelection.value) return

    if (action.handler) {
        const handler = action.handler
        if (action.confirm) {
            confirm.require({
                message:
                    typeof action.confirm.message === 'function'
                        ? action.confirm.message(props.selectedRows)
                        : action.confirm.message,
                header: action.confirm.header ?? 'Confirm Action',
                icon: action.confirm.icon ?? 'pi pi-exclamation-triangle',
                acceptLabel: action.confirm.acceptLabel ?? 'Yes',
                rejectLabel: action.confirm.rejectLabel ?? 'Cancel',
                acceptClass: action.confirm.acceptClass ?? 'p-button-danger',
                rejectClass: 'p-button-secondary p-button-outlined',
                accept: async () => {
                    await executeHandler(handler, action)
                },
            })
        } else {
            await executeHandler(handler, action)
        }
    }
}

const executeHandler = async (
    handler: (rows: any[]) => void | Promise<void>,
    action: BulkAction<T>,
) => {
    const actionKey = action.action ?? action.label ?? 'unknown'
    loadingActions.value.add(actionKey)
    try {
        // Phase 6 capability handlers ignore the rows argument and use canonical selection.
        await handler(props.selectedRows)
        toast.add({
            severity: 'success',
            summary: 'Action completed',
            life: 3000,
        })
    } catch (err: any) {
        toast.add({
            severity: 'error',
            summary: 'Action failed',
            detail: err.message || 'Unknown error',
            life: 5000,
        })
    } finally {
        loadingActions.value.delete(actionKey)
    }
}
</script>

<template>
    <div
        class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 py-4 px-6 border-b border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900"
    >
        <div class="flex flex-wrap items-center gap-4">
            <transition name="fade-scale">
                <div
                    v-if="displaySelectionLabel"
                    class="px-4 py-2 text-sm font-semibold rounded-full bg-primary/10 text-primary border border-primary/30"
                >
                    {{ displaySelectionLabel }}
                </div>
            </transition>
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto">
            <IconField icon-position="left" class="flex-1 md:flex-initial">
                <InputIcon class="pi pi-search text-surface-500" />
                <InputText
                    v-model="globalSearch"
                    placeholder="Search all columns..."
                    class="w-full md:w-80 h-11 text-sm"
                    show-clear
                    @clear="globalSearch = ''"
                />
            </IconField>

            <MultiSelect
                v-model="hiddenColumns"
                :options="toggleableColumns"
                option-label="header"
                option-value="field"
                placeholder="Columns"
                display="chip"
                :max-selected-labels="4"
                class="w-full md:w-64 text-sm"
                filter
            >
                <template #header>
                    <div
                        class="px-4 py-3 text-xs font-medium text-surface-600 dark:text-surface-300 border-b border-surface-200 dark:border-surface-700"
                    >
                        Visible Columns
                    </div>
                </template>
                <template #option="{ option }">
                    <div class="flex items-center gap-3 py-2">
                        <Checkbox
                            :model-value="!hiddenColumns.includes(option.field)"
                            :input-id="`col-${option.field}`"
                            binary
                            disabled
                        />
                        <label :for="`col-${option.field}`" class="text-sm cursor-pointer">
                            {{ option.header }}
                        </label>
                    </div>
                </template>
                <template #footer>
                    <div class="px-4 py-2 text-xs text-surface-500">
                        {{ toggleableColumns.length - hiddenColumns.length }} of
                        {{ toggleableColumns.length }} visible
                    </div>
                </template>
            </MultiSelect>

            <Button
                icon="pi pi-refresh"
                @click="emit('refresh')"
                rounded
                text
                severity="secondary"
                size="small"
                class="h-11 w-11"
                :loading="refreshing"
                aria-label="Refresh table"
            />
        </div>
    </div>
    <div
        class="w-full flex items-center justify-between px-6 py-3 bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700"
    >
        <transition name="fade">
            <div v-if="visibleBulkActions.length" class="flex flex-wrap items-center gap-2">
                <Button
                    v-for="action in visibleBulkActions"
                    :key="action.action ?? action.label"
                    :label="
                        selectionCountForLabel
                            ? `${action.label} (${selectionCountForLabel})`
                            : action.label
                    "
                    :icon="action.icon"
                    size="small"
                    :severity="action.severity || 'secondary'"
                    :outlined="!hasSelection"
                    :loading="loadingActions.has(action.action ?? action.label)"
                    :disabled="!hasSelection"
                    @click="handleBulkAction(action)"
                    class="transition-all duration-200"
                />
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
    @apply py-2;
}
</style>
