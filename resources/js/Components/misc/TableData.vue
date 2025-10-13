<template>
    <DataTable :value="rows" :alwaysShowPaginator="false" :loading="loading" :paginator="true" :rows="perPage"
        :totalRecords="totalRecords" :first="(currentPage - 1) * perPage" :selectionMode="selectionMode"
        v-model:selection="selectedRows" @page="onPage" @sort="onSort" dataKey="id"
        :rowsPerPageOptions="[5, 10, 20, 50]" :globalFilterFields="props.globalFilterFields" v-model:filters="filters"
        filterDisplay="menu">
        <!-- Table Header -->
        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-2">
                <!-- Bulk Actions -->
                <div class="flex gap-2">
                    <Button v-for="action in bulkActions" :key="action.label" :label="action.label" :icon="action.icon"
                        :disabled="!selectedRows.length" size="small" @click="action.action(selectedRows)" />
                </div>
                <div class="flex gap-2 items-center">
                    <!-- Search -->
                    <IconField>
                        <InputIcon><i class="pi pi-search" /></InputIcon>
                        <InputText v-model="filters.global.value" placeholder="Search..." @input="debouncedSearch" />
                    </IconField>

                    <!-- Column Visibility Toggle -->
                    <MultiSelect
                        v-model="hiddenColumns"
                        :options="columns"
                        optionLabel="header"
                        optionValue="field"
                        placeholder="Show/Hide Columns"
                        display="chip"
                        class="w-[14rem]"
                    />
                </div>
            </div>
        </template>

        <!-- Multi Select -->
        <Column selectionMode="multiple" headerStyle="width: 3rem" />

        <!-- Auto Columns -->
        <Column v-for="col in columns.filter(col => !hiddenColumns.includes(col.field))" :key="col.field"
            :field="col.field" :header="col.header" :filter="true" :filterPlaceholder="`Filter by ${col.header}`"
            :style="col.style" :sortable="col.sortable">
            <template #body="slotProps">
                <slot :name="`${col.field}-body`" v-bind="slotProps">
                    <span v-if="col.filterType === 'boolean'">
                        <i class="pi"
                            :class="{ 'pi-check-circle text-green-500 ': slotProps.data[col.field], 'pi-times-circle text-red-500': !slotProps.data[col.field] }"></i>
                    </span>
                    <span v-else-if="col.filterType === 'date'">
                        {{ new Date(slotProps.data[col.field]).toLocaleDateString() }}
                    </span>
                    <span v-else-if="col.filterType === 'number'">
                        {{ slotProps.data[col.field].toLocaleString() }}
                    </span>
                    <span v-else>
                        {{ slotProps.data[col.field] }}
                    </span>
                </slot>
            </template>

            <template #filter="{ filterModel, filterCallback }">
                <InputText v-if="col.filterType === 'text'" v-model="filterModel.value" type="text"
                    @input="debouncedSearch" :placeholder="`Search by ${col.header}`" />

                <MultiSelect v-if="col.filterType === 'multiselect'" v-model="filterModel.value"
                    @change="debouncedSearch" :options="col.filterOptions" optionLabel="name" placeholder="Any"
                    style="min-width: 14rem" :maxSelectedLabels="1">
                    <template v-slot:[`${col.field}-option`]="slotProps">
                        <slot :name="`${col.field}-option`" v-bind="slotProps">
                            {{ slotProps.option }}
                        </slot>
                    </template>
                </MultiSelect>

                <Select v-if="col.filterType === 'dropdown'" v-model="filterModel.value" @change="debouncedSearch"
                    :options="col.filterOptions" placeholder="Select One" style="min-width: 12rem" :showClear="true">
                    <template v-slot:[`${col.field}-option`]="slotProps">
                        <slot :name="`${col.field}-option`" v-bind="slotProps">
                            {{ slotProps.option }}
                        </slot>
                    </template>
                </Select>

                <template v-if="col.filterType === 'boolean'">
                    <label for="verified-filter" class="font-bold"> Verified </label>
                    <Checkbox v-model="filterModel.value" :indeterminate="filterModel.value === null" binary
                        :inputId="`${col.field}-filter`" />
                </template>
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
import { Button, Checkbox, Column, DataTable, IconField, InputIcon, InputText, MultiSelect, Select } from 'primevue';
import { FilterMatchMode } from '@primevue/core/api';
import { useDeleteResource } from '@/helpers';
import type { ColumnDefinition } from '@/types';

interface BulkAction {
    label: string;
    icon?: string;
    action: (rows: any[]) => void;
}

interface Filter {
    value: any;
    matchMode: string;
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
const selectedRows = ref<any[]>([]);
const hiddenColumns = defineModel<string[]>('hiddenColumns', {
    default: () => [],
});


// Filters
const filters = ref<Record<string, Filter>>({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

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
                filters: JSON.stringify(filters.value),
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
    fetchData();
}

onMounted(() => {
    props.columns.forEach(col => {
        filters.value[col.field] = {
            value: null,
            matchMode: col.matchMode || FilterMatchMode.CONTAINS,
        };
    });
});

// Watch filters for server-side updates
// watch(filters, debouncedSearch, { deep: true });
</script>
