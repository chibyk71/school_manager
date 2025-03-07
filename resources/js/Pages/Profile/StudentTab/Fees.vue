<script setup lang="ts">
import { ListOfAcademicYears } from '@/store';
import { Card, Column, DataTable, Select } from 'primevue';

const sessions = ListOfAcademicYears();

</script>

<template>
    <Card>
        <template #title>
            <div class="flex items-center justify-between flex-wrap pb-0">
            <h4 class="mb-3">Fees</h4>
            <div class="flex items-center flex-wrap">
                <Select :options="sessions" :model-value="sessions[0]" class="mr-2"  />
            </div>
        </div>
        </template>
        <template #content>
            <DataTable scrollable>
                <Column header="Fees Group" >
                  <template #filterfooter="slotProps"></template>
                </Column>
                <Column header="Fees Code" >
                </Column>
                <Column header="Due Date" />
                <Column header="Amount " />
                <Column header="Status" />
                <Column header="Date Paid" />
                <Column header="Ref. ID" />
                <Column header="Discount" />
                <Column header="Fine" />
            </DataTable>
        </template>
    </Card>
</template>
