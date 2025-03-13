<script setup lang="ts">
import InputWrapper from '@/Components/inputs/InputWrapper.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Avatar, Button, Card, CascadeSelect, Checkbox, DatePicker, IconField, InputIcon, Menu, Popover } from 'primevue';
import { useTemplateRef } from 'vue';

let menufilter = useTemplateRef('menufilter')
</script>

<template>
    <AuthenticatedLayout title="Notice Board"
        :crumb="[{ label: 'Dashboard' }, { label: 'Announcement' }, { label: 'Notice Board' }]">
        <div class="flex items-center justify-end flex-wrap mb-2 gap-3">
            <Button label="Mark All As Read" severity="secondary" />

            <IconField>
                <InputIcon><i class="ti ti-calendar"></i></InputIcon>
                <DatePicker selectionMode="range" />
            </IconField>
            <Button label="Filter" @click="(e) => menufilter?.toggle(e)" icon="ti ti-filter" severity="secondary" variant="outlined">
                <i class="ti ti-filter mr-2"></i>
                <span>Filter</span>
                <i class="ti ti-angle-left ml-2"></i>
            </Button>
            <Popover ref="menufilter" class="w-[348px]">
                <form action="notice-board.html">
                    <div class="flex items-center border-b p-3">
                        <h4>Filter</h4>
                    </div>
                    <div class="p-3 border-b pb-0">
                        <InputWrapper field_type="select" label="message To:" name="to" >
                            <template #input="{placeholder, id, name, invalid,}">
                                <CascadeSelect fluid :placeholder :id :name :invalid />
                            </template>
                        </InputWrapper>
                        <InputWrapper field_type="select" label="message To:" name="to" >
                            <template #input="{placeholder, id, name, invalid,}">
                                <DatePicker fluid :placeholder :id :name :invalid />
                            </template>
                        </InputWrapper>
                    </div>
                    <div class="p-3 flex items-center justify-end gap-x-3">
                        <Button type="reset" severity="secondary" label="Reset" />
                        <Button label="Apply" type="submit" />
                    </div>
                </form>
            </Popover>
        </div>

        <!-- Notice Board List -->
        <Card class="mb-3" v-for="i in 6">
            <template #content>
                <div class="flex justify-between">
                    <div class="flex items-center mb-3">
                        <Checkbox binary class="mr-2" />

                        <Avatar icon="ti ti-notification" class="mr-2 bg-primary-200/30" />
                        <div>
                            <Button class="text-lg/none py-1 px-0" variant="link">Classes Preparation</Button>
                            <p class="text-sm/none"><i class="ti ti-calendar mr-1"></i>Added on : 24 May 2024</p>
                        </div>
                    </div>
                    <div class="flex items-center board-action gap-x-2 mb-3">
                        <Button icon="ti ti-edit-circle" size="small" />
                        <Button icon="ti ti-trash-x" severity="danger" size="small" />
                    </div>
                </div>
            </template >
        </Card>
        <!-- Notice Board List -->

    </AuthenticatedLayout>
</template>
