<script setup lang="ts">
import { computed, ref } from 'vue'
import { ProgressSpinner } from 'primevue'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import type { ColumnDefinition, TableAction, BulkAction } from '@/types/datatables'
import axios from 'axios'

const props = defineProps<{
    columns: ColumnDefinition<any>[]
    users: any[]
}>()

const loading = ref(false)

const enhancedColumns = computed(() => props.columns ?? [])

const rowActions = computed<TableAction<any>[]>(() => [
    {
        label: (row) => (row.is_active ? 'Deactivate' : 'Activate'),
        icon: (row) => (row.is_active ? 'pi pi-ban' : 'pi pi-check-circle'),
        severity: (row) => (row.is_active ? 'warn' : 'success'),
        handler: async (row) => {
            loading.value = true
            try {
                await axios.patch(route('profiles.toggle-status'), {
                    ids: [row.id],
                    active: !row.is_active,
                })
            } finally {
                loading.value = false
            }
        },
    },
])

const bulkActions = computed<BulkAction[]>(() => [])
</script>

<template>
    <div class="relative">
        <AdvancedDataTable
            endpoint="/users"
            :columns="enhancedColumns"
            :initial-data="props.users"
            :initial-params="{ with: 'profiles,roles,schools' }"
            :actions="rowActions"
            :bulk-actions="bulkActions"
        />

        <transition name="fade">
            <div
                v-if="loading"
                class="absolute inset-0 bg-white/70 dark:bg-gray-900/70 backdrop-blur-sm flex items-center justify-center z-40 rounded-xl"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="flex flex-col items-center gap-5">
                    <ProgressSpinner style="width: 60px; height: 60px" stroke-width="4" />
                    <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">Processing...</p>
                </div>
            </div>
        </transition>
    </div>
</template>
