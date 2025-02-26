<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import { Button, Card, CascadeSelect, InputChips, InputGroup, InputGroupAddon, InputText, MultiSelect, Select, ToggleSwitch } from 'primevue';
import { ref } from 'vue';
import { ListOfAcademicYears } from '@/store';

const allowedFileTypes = ref([
    { label: 'Images', value: ['jpg', 'jpeg', 'png', 'gif'] },
    { label: 'Documents', value: ['pdf', 'doc', 'docx', 'txt'] },
    { label: 'Spreadsheets', value: ['xls', 'xlsx', 'csv'] },
    { label: 'Presentations', value: ['ppt', 'pptx'] },
    { label: 'Videos', value: ['mp4', 'avi', 'mov'] },
    { label: 'Audio', value: ['mp3', 'wav', 'aac'] }
]);

let props = defineProps({})

function grouplabel(params: string[]) {
    return params.map((val)=> { return {cname:val}});
}

let academisyears = ListOfAcademicYears()

const form = useForm({
    language: 'English',
    language_switcher: true,
    time_zone: "UTC 5:30",
    date_format: 'dd/mm/yyyy',
    time_format: "12 hours",
    financial_year: academisyears[academisyears.length - 1],
    starting_month: 'January',
    currency: "Naria",
    currency_position: "Start",
    decimal_seperator: '.',
    thousand_separator: ',',
    allowed_file_types: [],
    max_file_upload_size: '5'
});

const submit = () => {
    form.post(route('website.localization.post'))
}
</script>

<template>
    <AuthenticatedLayout title="Localization Setting" :crumb="[{label:'Settings'},{label:'School'},{label:'Localization'}]" :buttons="[{icon: 'ti ti-refresh', severity: 'secondary', size:'small'}]">
        <Head title="Localization Setting" />
        <SettingsLayout>
            <template #left>
                <div class="pt-3 flex flex-col list-group mb-4 dark:[&>a]:text-primary-emphasis-alt">
                    <a href="company-settings.html" class="block rounded p-2">Company Settings</a>
                    <Link :href="route('website.localization')" class="block rounded p-2 active">Localization</Link>
                </div>
            </template>
            <template #main>
                <div class="border-start pl-3">
                    <form action="" @submit.prevent="submit">
                        <div
                            class="flex items-center justify-between flex-wrap border-bottom pt-3 mb-3">
                            <div class="mb-3">
                                <h5 class="mb-1">Localization</h5>
                                <p>Collection of settings for user environment</p>
                            </div>
                            <div class="mb-3">
                                <Button severity="secondary" size="small" class="mr-2" type="button">Cancel</Button>
                                <Button class="" size="small" type="submit">Save</Button>
                            </div>
                        </div>
                        <div class="md:flex block">
                            <div class="flex-1 space-y-4">
                                <Card>

                                  <template #title>
                                    <h5>Basic Information</h5>
                                  </template>
                                  <template #content>
                                    <div class="block xl:flex items-end mb-1">
                                        <div class="mb-3 flex-1 xl:mr-3 mr-0">
                                            <label class="font-medium text-lg">Language</label>
                                            <InputGroup>
                                                <Select fluid :options="['English', 'Spanish', 'French']" v-model="form.language" />
                                                <InputGroupAddon class="p-0.5 bg-slate-300">
                                                    <Button severity="secondary" size=small><i class="ti ti-plus"></i></Button>
                                                </InputGroupAddon>
                                            </InputGroup>
                                        </div>
                                        <div class="mb-3 flex-1">
                                            <div class="flex items-center justify-between">
                                                <div class="status-title">
                                                    <h5 class="leading-none">Language Switcher</h5>
                                                    <p>To display in all pages</p>
                                                </div>
                                                <ToggleSwitch v-model="form.language_switcher" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="grid xl:grid-cols-3 grid-cols-1 gap-3 mb-4">
                                        <div class="xl:pr-0">
                                            <div class="">
                                                <label class="form-label">Timezone</label>
                                                <Select fluid class="select" :options="['UTC 5:30', 'UTC 4:30', '(UTC+11:00) INR']" v-model="form.time_zone" />
                                            </div>
                                        </div>
                                        <div class="xl:px-0">
                                            <label class="form-label">Date Format</label>
                                            <Select fluid class="select" :options="['dd/mm/yyyy', 'mm/dd/yyyy', 'yyyy/mm/dd']" v-model="form.date_format" />
                                        </div>
                                        <div class="">
                                            <label class="form-label">Time Format</label>
                                            <Select :options="['12 hours', '24 hours']" v-model="form.time_format" fluid />
                                        </div>
                                    </div>
                                    <div class="grid xl:grid-cols-2 grid-cols-1 gap-3">
                                        <div class="">
                                            <label class="form-label">Financial Year</label>
                                            <Select fluid class="select" :options="academisyears" v-model="form.financial_year">
                                            </Select>
                                        </div>
                                        <div class="">
                                            <label class="form-label">Starting Month</label>
                                            <Select fluid class="select" :options="['January', 'February', 'March']" v-model="form.starting_month">
                                            </Select>
                                        </div>

                                    </div>
                                  </template>
                                </Card>
                                <Card class="">

                                    <template #title><h5>Currency Settings</h5></template>
                                    <template #content>
                                        <div class="grid xl:grid-cols-2 grid-cols-1 gap-3 mb-4">
                                            <div class="">
                                                <label class="form-label">Currency</label>
                                                <Select fluid :options="['Naria', 'USD','Cedis']" v-model="form.currency">
                                                </Select>
                                            </div>
                                            <div class="">
                                                <label class="form-label">Currency Position</label>
                                                <Select fluid :options="['Start', 'End']" v-model="form.currency_position">
                                                </Select>
                                            </div>
                                        </div>
                                        <div class="grid xl:grid-cols-2 grid-cols-1 gap-3">

                                            <div class="">
                                                <label class="form-label">Decimal Seperator</label>
                                                <InputText fluid v-model="form.decimal_seperator" />
                                            </div>

                                            <div class="">
                                                <label class="form-label">Thousand Seperator</label>
                                                <InputText fluid v-model="form.thousand_separator" />
                                            </div>
                                        </div>
                                    </template>
                                </Card>
                                <Card class="">
                                    <template #title><h5>File Settings</h5></template>
                                    <template #content>
                                        <div class="grid xl:grid-cols-12 grid-cols-1 gap-4 items-center mb-3">
                                            <div class="xl:col-span-6">
                                                <div class="">
                                                    <h6 class="text-lg/none font-medium">Allowed Files</h6>
                                                    <p>Select allowed files</p>
                                                </div>
                                            </div>
                                            <div class="xl:col-span-6">
                                                <MultiSelect fluid v-model="form.allowed_file_types" :options="allowedFileTypes" optionLabel="cname" option-group-label='label' :option-group-children="(data)=>grouplabel(data.value)" placeholder="Select a City" >

                                                </MultiSelect>
                                            </div>
                                        </div>
                                        <div class="grid xl:grid-cols-12 grid-cols-1 gap-4 items-center">
                                            <div class="xl:col-span-6">
                                                <h6 class="text-lg/none font-medium">Maximum File Size</h6>
                                                <p>Select max size of files</p>
                                            </div>
                                            <div class="xl:col-span-6">
                                                <InputGroup>
                                                    <InputText v-model="form.max_file_upload_size" type="number" fluid />
                                                    <InputGroupAddon>
                                                        MB
                                                    </InputGroupAddon>
                                                </InputGroup>
                                            </div>
                                        </div>
                                    </template>
                                </Card>
                            </div>
                        </div>
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>

<style lang="postcss">
    .form-label {
        @apply text-gray-700 dark:text-surface-200 font-medium text-lg;
    }
</style>
