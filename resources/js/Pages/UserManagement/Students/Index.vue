<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DataView from 'primevue/dataview'
import { computed, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { Button, Menu } from 'primevue'
import type { ColumnDefinition } from '@/types/datatables'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import { usePopup } from '@/helpers'

const props = defineProps<{
    students: any
    columns: ColumnDefinition<any>[]
    meta?: {
        currentPage: number
        perPage: number
        total: number
        lastPage: number
    }
    can: {
        create: boolean
        edit: boolean
        delete: boolean
        export: boolean
    }
}>()

const initialTableResponse = computed(() => {
    const data = Array.isArray(props.students) ? props.students : (props.students?.data ?? [])
    if (!props.meta || !props.columns?.length) return null
    return { data, columns: props.columns as any, meta: props.meta }
})

const viewMode = ref<'table' | 'grid'>('table')
const { toggle: exportMenu } = usePopup('exportMenu')

const exportItems = [
    { label: 'Export as CSV', icon: 'pi pi-file', command: () => exportData('csv') },
    { label: 'Export as Excel', icon: 'pi pi-file-excel', command: () => exportData('excel') },
]

const exportData = async (format: 'csv' | 'excel') => {
    window.location.href = route('students.export', { format })
}

const studentsArray = computed(() => props.students?.data ?? [])

const enhancedColumns = computed<ColumnDefinition<any>[]>(() => {
    const cols = [...props.columns]
    return cols
})

const { can } = props
</script>

<template>
    <AuthenticatedLayout
        title="Students"
        :crumb="[{ label: 'Dashboard', url: '/dashboard' }, { label: 'Students' }]"
        :buttons="[
            {
                label: 'Add Student',
                icon: 'pi pi-plus',
                severity: 'primary',
                href: route('student.create'),
                as: Link,
                class: { visible: can?.create },
            },
            {
                icon: 'pi pi-download',
                severity: 'secondary',
                class: 'ml-2',
                onClick: (e) => exportMenu(e),
            },
        ]"
    >
        <Menu ref="exportMenu" :model="exportItems" :popup="true" />

        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold">Students</h2>
                <span class="text-sm text-gray-500">({{ props.meta?.total ?? 0 }} total)</span>
            </div>
        </div>

        <div
            v-if="viewMode === 'table'"
            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
        >
            <AdvancedDataTable
                endpoint="/student"
                :columns="enhancedColumns"
                :initial-response="initialTableResponse"
                :initial-params="{ with: 'user,schoolSection,classSections' }"
                :bulk-actions="[
                    {
                        label: 'Delete Selected',
                        icon: 'pi pi-trash',
                        severity: 'danger',
                        action: 'delete',
                        visible: () => can?.delete,
                    },
                    { label: 'Export Selected', icon: 'pi pi-download', action: 'export' },
                ]"
            />
        </div>
    </AuthenticatedLayout>
</template>
