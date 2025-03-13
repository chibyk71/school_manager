<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Column, DataTable, DatePicker, IconField, InputIcon, InputText } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Expenses" :crumb="[{label:'Dashboard'},{label:'Finance'},{label:'Expense'}]" :button="[{label:'Add Expense', icon:'ti ti-plus-circle'}]">
        <DataTable>
            <Column selection-mode="multiple" />
            <Column header="ID" />
            <Column header="Expense Title" sortable />
            <Column header="Category" sortable />
            <Column header="Date" sortable />
            <Column header="Description" />
            <Column header="Invoice Id" />
            <Column header="Amount" sortable />
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
