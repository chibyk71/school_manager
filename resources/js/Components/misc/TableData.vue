<template>
    <DataTable :value="rows" :alwaysShowPaginator="false" :loading="loading" :paginator="true" :rows="perPage"
        :totalRecords="totalRecords" :first="(currentPage - 1) * perPage" :selectionMode="selectionMode"
        v-model:selection="selectedRows" @page="onPage" @sort="onSort" dataKey="id"
        :rowsPerPageOptions="[5, 10, 20, 50]" :globalFilterFields="props.globalFilterFields" :lazy="true"
        v-model:filters="filters" filterDisplay="menu">
        <!-- Table Header -->
        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-2">
                <!-- Bulk Actions -->
                <div class="flex gap-2">
                    <Button v-for="action in bulkActions" :key="action.label" :label="action.label" :icon="action.icon"
                        :disabled="!selectedRows.length" size="small" @click="action.action(selectedRows)" />
                    <Button label="Delete All" severity="danger" icon="pi pi-trash" :disabled="!selectedRows.length"
                        size="small" @click="() => deleteResource('custom-field', selectedRows.map(row => row.id))" />
                </div>

                <!-- Search -->
                <IconField>
                    <InputIcon><i class="pi pi-search" /></InputIcon>
                    <InputText v-model="filters.global.value" placeholder="Search..." @input="debouncedSearch" />
                </IconField>
            </div>
        </template>

        <!-- Multi Select -->
        <Column selectionMode="multiple" headerStyle="width: 3rem" />

        <!-- Auto Columns -->
        <Column v-for="col in columns" :key="col.field" :field="col.field" :header="col.header" :filter="true" :filterPlaceholder="`Filter by ${col.header}`" :style="col.style">
            <template #body="{ data }">
                {{ data[col.field] }}
            </template>

            <template #filter="{ filterModel, filterCallback }">
                <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by {{ col.header }}" />
            </template>
        </Column>

        <!-- Empty Template -->
        <template #empty>No records found.</template>
    </DataTable>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import axios from 'axios';
import debounce from "lodash/debounce";
import { Button, Column, DataTable, IconField, InputIcon, InputText } from 'primevue';
import { FilterMatchMode } from '@primevue/core/api';
import { useDeleteResource } from '@/helpers';
import type { ColumnDefinition } from '@/types';
import { filter } from 'lodash';

interface BulkAction {
    label: string;
    icon?: string;
    action: (rows: any[]) => void;
}

const props = defineProps<{
    endpoint: string;
    columns: ColumnDefinition[];
    params?: Record<string, any>;
    selectionMode?: 'single' | 'multiple';
    bulkActions?: BulkAction[];
    rows?: any[];
    globalFilterFields: string[];
}>();

// State
const rows = ref<any[]>(props.rows ?? []);
const totalRecords = ref(0);
const loading = ref(false);
const perPage = ref(10);
const currentPage = ref(1);
const sortField = ref('');
const sortOrder = ref<1 | -1 | 0>(0);
// Filters
const filters = ref<Record<string, { value: null | any; matchMode: string }>>({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});
const selectedRows = ref<any[]>([]);

const { deleteResource } = useDeleteResource();

// Fetch data from Laravel API
const fetchData = async () => {
    loading.value = true;
    try {
        const sortOrderStr = sortOrder.value === 1 ? 'asc' : sortOrder.value === -1 ? 'desc' : null;
        const { data } = await axios.get(props.endpoint, {
            params: {
                page: currentPage.value,
                per_page: perPage.value,
                sort_field: sortField.value,
                sort_order: sortOrderStr,
                search: filters.value.global.value,
                ...props.params,
            },
        });

        rows.value = data.data; // Laravel's resource format
        totalRecords.value = data.total;
    } finally {
        loading.value = false;
    }
};

// Pagination event
const onPage = (event: any) => {
    currentPage.value = event.page + 1;
    perPage.value = event.rows;
    fetchData();
};

// Sorting event
const onSort = (event: any) => {
    sortField.value = event.sortField;
    sortOrder.value = event.sortOrder;
    fetchData();
};

// Search with debounce
const debouncedSearch = debounce(() => {
    currentPage.value = 1;
    fetchData();
}, 500);

// Initial fetch
if (!props.rows || props.rows.length === 0) {
    currentPage.value++;
    fetchData();
}

onMounted(() => {
    props.columns.forEach(col => {
        filters.value[col.field] = {
            value: null,
            matchMode: col.matchMode || FilterMatchMode.CONTAINS
        };
    });

});

// Note: If you want server-side column filtering, add v-model:filters="filters" to DataTable,
// watch the filters, and send them in fetchData params.
// For example:
// watch(filters, debouncedSearch, { deep: true });
// And in params: { ...filters: filters.value } (adjust as needed for backend).
</script>
