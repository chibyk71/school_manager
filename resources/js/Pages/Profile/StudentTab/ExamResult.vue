<script setup lang="ts">
import { ListOfAcademicYears } from '@/store';
import { Card, Column, DataTable, Select } from 'primevue';

    let sessions = ListOfAcademicYears();
</script>

<template>

    <Card class="card">
        
        <template #content>
            <DataTable>
                <Column header="Subject" />
                <Column>
                  <template #header="slotProps">
                    <H4>1<sup>st</sup> Test</H4>
                  </template>
                </Column>
                <Column>
                  <template #header="slotProps">
                    <H4>2<sup>nd</sup> Test</H4>
                  </template>
                </Column>
                <Column>
                  <template #header="slotProps">
                    <H4>3<sup>rd</sup> Test</H4>
                  </template>
                </Column>
                <Column>
                  <template #header="slotProps">
                    <H4>Exam</H4>
                  </template>
                </Column>
                <template #footer>
                    <tr class="mt-2 [&>td]:p-4">
                        <td class="bg-dark text-white">Rank : 30</td>
                        <td class="bg-dark text-white">Total : 500</td>
                        <td class="bg-dark text-white" colspan="2">Marks
                            Obtained : 395</td>
                        <td class="bg-dark text-white text-end">
                            <div class="flex items-center justify-end">
                                <span class="mr-2">Percentage :
                                    79.50</span>
                                <h6 class="font-normal text-white">Result
                                    : <span class="text-success"> Pass
                                    </span></h6>
                            </div>
                        </td>
                    </tr>
                </template>
            </DataTable>
        </template>
        <template #title>
            <div class="card-header flex items-center justify-between flex-wrap pb-0">
                <h4 class="mb-3">Exams & Results</h4>
                <Select :options="sessions" :model-value="sessions[0]" size="small" />
            </div>
        </template>
    </Card>
</template>
