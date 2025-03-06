<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Avatar, Badge, Button, Card, Column, DataTable, IconField, InputIcon, InputText, Menu, MenuEmits, type MenuMethods, TabPanel, Tabs } from 'primevue';
import { ref } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { Link } from '@inertiajs/vue3';
import { StudentMenu } from '@/store';
import UserGridCard from './UserGridCard.vue';
const dropdownref = ref<(Element | MenuMethods | null)[]>([])

const view = ref<'table' | 'grid'>('table');
</script>

<template>
    <AuthenticatedLayout title="Student Management"
        :crumb="[{ label: 'Dashboard' }, { label: 'User Management' }, { label: 'Student' }]"
        :buttons="[{ icon: 'ti ti-download', severity: 'secondary' }, { label: 'Export', icon: 'ti ti-file-export', severity: 'secondary', onClick: (e: MouseEvent) => ($refs.export as MenuMethods)?.toggle(e) }, { label: 'Add Student', icon: 'ti ti-plus', severity: 'primary' }]">
        <Menu ref="export" :popup="true"
            :model="[{ label: 'Export As PDF', icon: 'ti ti-file-export' }, { label: 'Export As CSV', icon: 'ti ti-file-export' }]" />

        <!-- Filter -->
        <div
            class="bg-surface-0 dark:bg-dark p-3 border rounded-xl flex items-center justify-between flex-wrap mb-4 pb-0">
            <h4 class="mb-3 capitalize">Students {{ view }}</h4>
            <div class="flex items-center flex-wrap mb-3">
                <div class="flex items-center bg-white border rounded-xl p-1 gap-x-2 mr-2">
                    <Button icon="ti ti-list-tree" @click="view = 'table'" v-tooltip="'Table View'">
                    </Button>
                    <Button icon="ti ti-grid-dots" @click="view = 'grid'" v-tooltip="'Grid View'" />
                </div>
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText placeholder="Search Students By Name" />
                </IconField>
            </div>
        </div>
        <!-- /Filter -->
        <Tabs v-model:value="view">
            <TabPanels>
                <TabPanel value="table">
                    <!-- Student List -->
                    <DataTable :filter="{ global: { value: null, matchMode: FilterMatchMode.CONTAINS } }"
                        :globalFilterFields="['name']">
                        <Column selection-mode="multiple" />
                        <Column header="Admission Number" field="enrollment_id" />
                        <Column header="Full Name" field="name " />
                        <Column header="Section" field="section" />
                        <Column header="Class" field="class" />
                        <Column header="Gender" />
                        <Column header="Enrollment Date" />
                        <Column header="Date Of Birth" />
                        <Column header="Action">
                            <template #body="{ index }">
                                <div class="flex items-center">
                                    <Button icon="ti ti-brand-hipchat" variant="outlined" severity="secondary" />
                                    <Button icon="ti ti-phone" outlined severity="secondary" rounded />
                                    <Button outlined severity="secondary" icon="ti ti-mail" />
                                    <Button icon="ti ti-ellipse" @click="(e) => (dropdownref[index] as MenuMethods)!.toggle(e)" outlined
                                        severity="secondary" />
                                    <Menu popup :ref="(e) => {dropdownref?.push((e as unknown as MenuMethods))}" :model="StudentMenu" />

                                </div>
                            </template>
                        </Column>
                    </DataTable>
                    <!-- /Students List -->
                </TabPanel>
                <TabPanel value="grid">
                    <div class="grid xxl:grid-cols-4 xl:grid-cols-3 md:grid-cols-2 grid-cols-12 gap-4">

                        <!-- Student Grid -->
                        <UserGridCard v-for="index in 9" name="Janet Daniel" :avatar="`assets/img/students/student-0${index}.jpg`" level="III A" :description="[{role:'35013'},{gender:'male'}, {'Joined on': '10 Jan 2015'}]" status phone="093287023" email="hgsgf hgdkghkfhgkhfr" enrollment_id="AD9892434" />
                        <!-- /Student Grid -->

                        <div class="text-center col-span-full">
                            <Button icon="ti ti-loader-3" label="Load More" />
                        </div>

                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>
    </AuthenticatedLayout>
</template>
