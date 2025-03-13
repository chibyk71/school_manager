<!-- THIS VIEW WILL REPLACE THE INCOME PAGE. AS SUCH WILL SHOW IMCOME MADE BY THE SCHOOLAND RELATED DETAILS -->

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Column, DataTable, DatePicker, IconField, InputIcon, InputText } from 'primevue';


</script>

<template>
    <AuthenticatedLayout title="Incomes" :crumb="[{label:'Dashboard'},{label:'Finance'},{label:'Income'}]" :button="[{label:'Add Income', icon:'ti ti-plus-circle'}]">
        <DataTable>
            <Column selection-mode="multiple" />
            <Column header="ID" />
            <Column header="Income Name" sortable />
            <Column header="Source" />
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
