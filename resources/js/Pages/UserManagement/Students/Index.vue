<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DataView from 'primevue/dataview'
import { computed, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { Button, Menu } from 'primevue'
import type { ColumnDefinition } from '@/types/datatables'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import { usePopup } from '@/helpers'
// import UserGridCard from './Partials/UserGridCard.vue' // We'll create this next

// Props from StudentController@index
const props = defineProps<{
    students: any
    columns: ColumnDefinition<any>[]
    totalRecords: number
    globalFilterFields: string[]
    can: {
        create: boolean
        edit: boolean
        delete: boolean
        export: boolean
    }
}>()

// View toggle: table or grid
const viewMode = ref<'table' | 'grid'>('table')

// For export menu
const {toggle: exportMenu} = usePopup("exportMenu");

const exportItems = [
    { label: 'Export as CSV', icon: 'pi pi-file', command: () => exportData('csv') },
    { label: 'Export as Excel', icon: 'pi pi-file-excel', command: () => exportData('excel') }
]

const exportData = async (format: 'csv' | 'excel') => {
    // You can enhance useDataTable to expose exportData
    window.location.href = route('students.export', { format })
}

// Compute students array for grid view
const studentsArray = computed(() => props.students?.data ?? [])

// Enhanced columns with custom renders
const enhancedColumns = computed<ColumnDefinition<any>[]>(() => {
    const cols = [...props.columns]

    cols.forEach(col => {
        if (col.field === 'status') {
            col.render = (row: any) => ({
                component: 'Badge',
                props: { value: row.is_active ? 'Active' : 'Inactive', severity: row.is_active ? 'success' : 'danger' }
            })
        }

        if (col.field === 'enrollment_id') {
            col.render = (row: any) => ({
                component: 'Link',
                props: { href: route('students.show', row.id), class: 'text-primary font-medium' },
                children: row.enrollment_id
            })
        }

        if (col.field === 'gender') {
            col.render = (row: any) => ({
                component: 'span',
                props: { class: 'capitalize' },
                children: row.gender || '—'
            })
        }
    })

    return cols
})
</script>

<template>

    <Head title="Students" />

    <AuthenticatedLayout title="Student Management"
        :crumb="[{ label: 'Dashboard', url: '/dashboard' }, { label: 'Students' }]" :buttons="[{ label: 'Add Student', icon: 'pi pi-plus', severity: 'primary', href: route('student.create'), as: Link, class: { visible: can?.create } }, { icon: 'pi pi-download', severity: 'secondary', class: 'ml-2', onClick: (e) => exportMenu(e) }
        ]">
        <!-- Export Menu -->
        <Menu ref="exportMenu" :model="exportItems" :popup="true" />

        <!-- View Mode Toggle + Search -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold">Students</h2>
                <span class="text-sm text-gray-500">({{ totalRecords }} total)</span>
            </div>

            <div class="flex items-center gap-3">
                <!-- View Toggle -->
                <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-700 overflow-hidden">
                    <Button :icon="viewMode === 'table' ? 'pi pi-table' : 'pi pi-th-large'"
                        :severity="viewMode === 'table' ? 'primary' : 'secondary'" text size="small"
                        @click="viewMode = 'table'" v-tooltip.bottom="'Table View'" />
                    <Button :icon="viewMode === 'grid' ? 'pi pi-th-large' : 'pi pi-grid'"
                        :severity="viewMode === 'grid' ? 'primary' : 'secondary'" text size="small"
                        @click="viewMode = 'grid'" v-tooltip.bottom="'Grid View'" />
                </div>
            </div>
        </div>

        <!-- Table View -->
        <div v-if="viewMode === 'table'"
            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <AdvancedDataTable endpoint="/student" :columns="enhancedColumns" :initial-data="studentsArray"
                :total-records="totalRecords" :global-filter-fields="globalFilterFields"
                :initial-params="{ with: 'user,schoolSection,classSections' }" selection-mode="multiple" :bulk-actions="[
                    { label: 'Delete Selected', icon: 'pi pi-trash', severity: 'danger', action: 'delete', visible: () => can?.delete },
                    { label: 'Export Selected', icon: 'pi pi-download', action: 'export' }
                ]" />
        </div>

        <!-- Grid View -->
        <div v-else class="grid xxl:grid-cols-5 xl:grid-cols-4 lg:grid-cols-3 md:grid-cols-2 grid-cols-1 gap-6">
            <UserGridCard v-for="student in studentsArray" :key="student.id" :student="student" />

            <!-- Load More or Empty State -->
            <div v-if="studentsArray.length === 0" class="col-span-full text-center py-12">
                <i class="pi pi-users text-6xl text-gray-300 dark:text-gray-600 mb-4" />
                <p class="text-lg text-gray-500">No students found</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
