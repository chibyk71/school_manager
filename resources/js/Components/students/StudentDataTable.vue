<!-- Thin wrapper — Phase 5 uses AdvancedDataTable + useDataTableQuery -->
<script setup lang="ts">
import type { Student } from '@/types/student'
import type { ColumnDefinition, BulkAction } from '@/types/datatables'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'

defineProps<{
    endpoint: string
    columns: ColumnDefinition<Student>[]
    bulkActions?: BulkAction[]
    initialParams?: Record<string, any>
    initialData?: Student[]
}>()
</script>

<template>
    <AdvancedDataTable
        :endpoint="endpoint"
        :columns="columns"
        :bulk-actions="bulkActions"
        :initial-params="initialParams"
        :initial-data="initialData"
    />
</template>
