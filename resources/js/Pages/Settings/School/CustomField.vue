<script setup lang="ts">
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import SettingsLayout from '../Partials/SettingsLayout.vue';
import { Button, Column, DataTable } from 'primevue';

</script>

<template>
    <AuthenticatedLayout title="Custom Field" :crumb="[{label:'Setting'},{label:'School'},{label:'Custom Field'}]">
        <SettingsLayout>
            <template #left>
            </template>
            <template #main>
                <div class="mx-3">
                    <form action="custom-fields.html">
                        <div class="flex items-center justify-between flex-wrap border-b pt-3 mb-3">
                            <div class="mb-3">
                                <h5 class="mb-1">Custom Fields</h5>
                                <p>Custom Fields configuration</p>
                            </div>
                            <div class="mb-3">
                                <Button severity="secondary" icon="pi pi-plus" label="Add"></Button>
                            </div>
                        </div>
                        <div class="block">
                            <DataTable :value="[]">
                                <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
                                <Column field="resource" header="Resource"></Column>
                                <Column field="label" header="Label"></Column>
                                <Column field="type" header="Type"></Column>
                                <Column field="required" header="Required"></Column>
                                <Column field="order" header="Order"></Column>
                                <Column field="default" header="Default"></Column>
                                <Column field="group" header="Group"></Column>
                                <Column field="status" header="Status"></Column>
                                <Column field="action" header="Action"></Column>
                            </DataTable>
                        </div>
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
