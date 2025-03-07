<script setup lang="ts">
import { Avatar, Column, DataTable } from 'primevue';


</script>

<template>
    <div class="grid md:grid-cols-2 xxl:grid-cols-4">
        <div class="flex items-center justify-between rounded border p-3 mb-3 bg-white">
            <div class="ml-2">
                <p class="text-base/none font-light">Total Net Salary</p>
                <h5>$5,55,410</h5>
            </div>
            <Avatar icon="ti ti-user-dollar" size="large" class="bg-surface-300/40" />
        </div>


        <div class="flex items-center justify-between rounded border p-3 mb-3 bg-white">
            <div class="ml-2">
                <p class="text-base/none font-light">Total Gross Salary</p>
                <h5>$5,58,380</h5>
            </div>
            <Avatar icon="ti ti-moneybag" size="large" class="bg-green-300/40" />
        </div>

        <div class="flex items-center justify-between rounded border p-3 mb-3 bg-white">
            <div class="ml-2">
                <p class="text-base/none font-light">Total Deduction</p>
                <h5>$2,500</h5>
            </div>
            <Avatar icon="ti ti-arrow-big-down-lines" size="large" class="bg-yellow-300/40" />
        </div>

    </div>

    <!-- Payroll List -->
     <DataTable class="mt-3">
        <Column selectionMode="multiple" />
        <Column header="ID" />
        <Column header="Date" />
        <Column header="Payment Method"></Column>
        <Column header="Net Salary"></Column>
        <template #header>
            <h5>Salary</h5>
        </template>
     </DataTable>
    <!-- /Payroll List -->
</template>
