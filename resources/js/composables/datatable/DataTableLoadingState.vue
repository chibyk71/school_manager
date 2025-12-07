<!-- resources/js/Components/shared/DataTableLoadingState.vue -->
<template>
    <tr v-for="n in skeletonRows" :key="n" role="row" class="animate-pulse">
        <td :colspan="colSpan" class="py-4 px-6" role="cell">
            <Skeleton height="2rem" class="w-full" border-radius="8px" />
        </td>
    </tr>
</template>

<script setup lang="ts">
import { computed, inject } from 'vue'
import Skeleton from 'primevue/skeleton'

/**
 * Props:
 * - colSpan: total number of columns (including selection column)
 * - rows: number of skeleton rows to show (defaults to current perPage or 10)
 */
const props = withDefaults(defineProps<{
    colSpan?: number
    rows?: number
}>(), {
    colSpan: 8,
    rows: undefined
})

// Optional: pull perPage from useDataTable context if available
const injectedPerPage = inject<number>('datatablePerPage', 10)
const perPage = computed(() => props.rows ?? injectedPerPage ?? 10)

const skeletonRows = computed(() => {
    const count = perPage.value
    return Array.from({ length: Math.min(count, 15) }, (_, i) => i + 1) // cap at 15 for sanity
})
</script>

<style scoped>
/* Smooth fade-in for skeleton rows */
tr {
    @apply transition-opacity duration-300;
}
</style>
