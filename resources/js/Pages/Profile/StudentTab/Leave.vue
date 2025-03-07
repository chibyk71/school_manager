<script setup lang="ts">
import { Button, Card, Column, DataTable, TabPanel } from 'primevue';
</script>

<template>
    <!-- Leave Nav-->
        <div class="grid lg:grid-cols-2 xxl:grid-cols-4 gap-3">
            <Card class="card flex-1" v-for="i in 3">
                <template #content>
                    <h5 class="">Medical Leave (10)</h5>
                    <div class="flex items-center flex-wrap">
                        <p class="border-r pr-2 mr-2 mb-0">Used : 5</p>
                        <p class="mb-0">Available : 5</p>
                    </div>
                </template>
            </Card>
            <div class="p-card border">
                <div class="p-card-body gap-0">
                    <h5 class="">Casual Leave (12)</h5>
                    <div class="flex items-center flex-wrap">
                        <p class="border-r pr-2 mr-2 mb-0">Used : 1</p>
                        <p class="mb-0">Available : 11</p>
                    </div>
                </div>
            </div>
        </div>

        <Card class="mt-4">
            <template #title>
                <div class="card-header flex items-center justify-between flex-wrap pb-0">
                    <h4 class="mb-3">Leaves</h4>
                    <Button icon="" label="Apply Leave" />
                </div>
            </template>
            <template #content>
                <!-- Leaves List -->
                    <DataTable>
                    <Column sortable header="Leave Type" />
                    <Column sortable header="Leave Date" />
                    <Column header="No. Of Days" />
                    <Column  header="Applied On" />
                    <Column header="Status" />
                    </DataTable>
                <!-- /Leaves List -->
            </template>
        </Card>
</template>
