<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Column, DataTable, DatePicker, IconField, InputIcon, InputText } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Transactions" :crumb="[{label:'Dashboard'},{label:'Finance'},{label:'Transactions'}]">
        <DataTable>
            <Column selection-mode="multiple" />
            <Column header="ID" />
            <Column header="Description" />
            <Column header="Transaction Date" />
            <Column header="Amount" sortable />
            <Column header="Transaction Type" />
            <Column header="Payment Method" sortable />
            <Column header="Status" >
              <template #filterapply="slotProps"></template>
            </Column>
            <Column header="Action" />
            <template #header>
                <div class="flex items-center">
                    <h4>Expense List</h4>
                    <div class="flex items-center">
                        <IconField>
                            <InputIcon class="ti ti-search" />
                            <InputText placeholder="Search table" />
                        </IconField>
                        <DatePicker selectionMode="range" />
                    </div>
                </div>
            </template>
        </DataTable>
    </AuthenticatedLayout>
</template>
