<template>
    <DataTable :value="rows" :lazy="true" :loading="loading" :paginator="true" :rows="perPage"
        :totalRecords="totalRecords" :first="(currentPage - 1) * perPage" :selectionMode="selectionMode"
        v-model:selection="selectedRows" @page="onPage" @sort="onSort" dataKey="id">
        <!-- Table Header -->
        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-2">
                <!-- Bulk Actions -->
                <div class="flex gap-2">
                    <Button v-for="action in bulkActions" :key="action.label" :label="action.label" :icon="action.icon"
                        :disabled="!selectedRows.length" size="small" @click="action.action(selectedRows)" />
                </div>

                <!-- Search -->
                <IconField>
                    <InputIcon><i class="pi pi-search" /></InputIcon>
                    <InputText v-model="filters.global" placeholder="Search..." @input="debouncedSearch" />
                </IconField>
            </div>
        </template>

        <!-- Dynamic Columns -->
        <Column v-for="col in columns" :key="col.field" :field="col.field" :header="col.header" :sortable="col.sortable"
            :style="col.style">
            <template #body="slotProps">
                <slot :name="'col-' + col.field" v-bind="slotProps">
                    {{ slotProps.data[col.field] }}
                </slot>
            </template>
        </Column>

        <!-- Empty Template -->
        <template #empty>No records found.</template>
    </DataTable>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import debounce from "lodash/debounce";
import { Column, DataTable, IconField, InputIcon, InputText } from 'primevue';

interface ColumnConfig {
    field: string;
    header: string;
    sortable?: boolean;
    style?: string;
}

interface BulkAction {
    label: string;
    icon?: string;
    action: (rows: any[]) => void;
}

const props = defineProps<{
    endpoint: string;
    columns: ColumnConfig[];
    params?: Record<string, any>;
    selectionMode?: 'single' | 'multiple';
    bulkActions?: BulkAction[];
}>();

// State
const rows = ref<any[]>([]);
const totalRecords = ref(0);
const loading = ref(false);
const perPage = ref(10);
const currentPage = ref(1);
const sortField = ref('');
const sortOrder = ref<1 | -1 | 0>(0);
const filters = ref({ global: '' });
const selectedRows = ref<any[]>([]);

// Fetch data from Laravel API
const fetchData = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(props.endpoint, {
            params: {
                page: currentPage.value,
                per_page: perPage.value,
                sort_field: sortField.value,
                sort_order: sortOrder.value,
                search: filters.value.global,
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

// Init load
fetchData();
</script>
