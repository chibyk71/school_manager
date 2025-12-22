<!-- resources/js/composables/datatable/DataTableEmptyState.vue -->
<template>
    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400 w-full h-60">
        <!-- Icon -->
        <i class="pi pi-inbox text-7xl mb-5 opacity-30" aria-hidden="true" />

        <!-- Message -->
        <p class="text-lg font-medium mb-2">
            <slot>No records found</slot>
        </p>

        <!-- Optional hint (e.g. when filters are active) -->
        <p v-if="hasActiveFilters" class="text-sm opacity-75">
            Try adjusting your filters or search query
        </p>

        <!-- Optional action button (e.g. clear filters) -->
        <slot name="action">
            <Button v-if="hasActiveFilters" label="Clear filters" severity="secondary" outlined size="small"
                class="mt-4" @click="clearFilters" />
        </slot>
    </div>
</template>

<script setup lang="ts">
import { Button } from 'primevue'
import { inject, computed } from 'vue'

const props = withDefaults(defineProps<{
    colSpan?: number
}>(), {
    colSpan: 8
})

const filters = inject<any>('datatableFilters', null)
const clearFilters = inject<() => void>('datatableClearFilters', () => { })

const hasActiveFilters = computed(() => {
    if (!filters) return false

    return Object.values(filters).some((f: any) => {
        if (f && typeof f === 'object' && 'value' in f) {
            return f.value !== null && f.value !== '' && f.value !== undefined
        }
        return false
    })
})
</script>

<style scoped lang="postcss">
td {
    @apply bg-gray-50/50 dark:bg-gray-800/30;
}
</style>
