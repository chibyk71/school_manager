<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Button, Card, Column, DataTable, DatePicker, IconField, InputIcon, Select } from 'primevue';
import { ref } from 'vue';
    defineProps({
        roles: []
    })

    const selected = ref([])
</script>

<template>
    <Head title="Roles" />
    <AuthenticatedLayout title="Roles And Permissions" :crumb="[{label:'User Management'},{label:'Roles'}]">
        <Card class="card">
            <template #title class="card-header d-flex align-items-center justify-content-between flex-wrap pb-0">
                <h4 class="mb-3">Roles & Permissions List</h4>
            </template>

            <template #content class="card-body p-0 py-3">
                <!-- Role Permission List -->
                 <DataTable v-model:selection="selected" :global-filter-fields="['name']" :value="roles">
                    <Column selection-mode="multiple" />
                    <Column field="name" header="Role Name" />
                    <Column header="Display Name" />
                    <Column header="Action">
                        <template #body>
                            <div class="flex items-center gap-x-3">
                                <Button size="small" v-tooltip="`Edit Role`" icon="ti ti-edit-circle" severity="secondary" variant="outlined" />

                                <!-- peermission modal trigger -->
                                <Button size="small" v-tooltip="`Change Permission`" icon="ti ti-shield" variant="outlined" severity="info"  />

                                <Button v-tooltip="`Delete Role`" size="small" icon="ti ti-trash-x" severity="danger" variant="outlined"/>
                            </div>
                        </template>
                    </Column>
                    <template #header>
                        <div class="flex items-center justify-end gap-4 flex-wrap">
                            <IconField>
                                <InputIcon icon="ti ti-calender" />
                                <DatePicker />
                            </IconField>
                            <Select :options="['Ascending','Descending','Date Added']" model-value="Ascending" />
                        </div>
                    </template>
                 </DataTable>
                <!-- /Role Permission List -->
            </template>
        </Card>
    </AuthenticatedLayout>
</template>
