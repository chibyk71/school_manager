<script setup lang="ts">
import InputWrapper from '@/Components/inputs/InputWrapper.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { FilterModes } from '@/store';
import { Button, Column, DataTable, IconField, InputIcon, InputText, Menu, MenuMethods } from 'primevue';
import { MenuItem } from 'primevue/menuitem';
import { ref } from 'vue';

const props = defineProps<{
    users: []
}>()

const dropdownref: MenuMethods[] = [];

function StudentMenu(id: string): MenuItem[] {
    return [
        {
            label: 'View Profile',
            icon: 'ti ti-user',
            command: () => {
                console.log(`View Profile for user with ID: ${id}`);
            },
        },
        {
            label: 'Change Password',
            icon: 'ti ti-key',
            command: () => {
                console.log(`Change Password for user with ID: ${id}`);
            },
        },
        {
            label: 'Disable User',
            icon: 'ti ti-trash',
            command: () => {
                console.log(`Disable User with ID: ${id}`);
            },
        },
    ];
}

const filter = { global: { value: null, matchMode: FilterModes.CONTAINS } };
</script>

<template>
    <AuthenticatedLayout title="User Management" :crumb="[{ label: 'User Management' }, { label: 'User' }]"
        :buttons="[]">
        <DataTable :filter="filter" :globalFilterFields="['name']"
            :value="users" :paginator="true" :rows="10" :rowsPerPageOptions="[5, 10, 20]" :scrollable="true"
            scrollHeight="flex" :selectionMode="'multiple'">
            <Column selection-mode="multiple" />
            <Column header="S/N">
                <template #body="slotProps">{{ slotProps.index + 1 }}</template>
            </Column>
            <Column header="Name" field="name" />
            <Column header="Email" field="email" />
            <Column header="Action">
                <template #body="slotProps">
                    <div class="flex items-center gap-x-2">
                        <Button icon="ti ti-trash" severity="danger" />
                        <Button icon="ti ti-user-pause" severity="secondary"
                            v-tooltip="`assign direct permissions`"></Button>
                        <!-- assign roles -->
                        <Button icon="ti ti-user-check" v-tooltip="`assign roles`" />
                        <Button icon="ti ti-dots" text severity="secondary" v-tooltip="`more options`" @click="(e)=>dropdownref[slotProps.index].toggle(e)" />
                        <Menu popup :ref="(e) => { dropdownref[slotProps.index] = ((e as unknown as MenuMethods)) }"
                            :model="StudentMenu(slotProps.data.id)">
                        </Menu>
                    </div>
                </template>
            </Column>
            <template #header>
                <div class="flex items-center justify-end">
                    <InputWrapper label="" name="search" field_type="text" placeholder="Search">
                        <template #input="slotProps">
                        <IconField>
                            <InputIcon class="ti ti-search" />
                            <InputText v-model="filter.global.value" v-bind="slotProps" />
                        </IconField>
                        </template>
                    </InputWrapper>
                </div>
            </template>
        </DataTable>
    </AuthenticatedLayout>
</template>
