<script setup lang="ts">
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import { Head } from '@inertiajs/vue3';
    import { Button, InputGroup, InputGroupAddon, InputNumber } from 'primevue';
    import SettingsLayout from '../Partials/SettingsLayout.vue';

    defineProps<{
        settings : {
            title: string,
            value: string,
            status?: boolean
        }[]
    }>();

</script>

<template>
    <Head title="Tax Setting" />
    <AuthenticatedLayout title="Tax Settings" :crumb="[{ label: 'Settings' }, { label: 'Finance' }, { label: 'Tax' }]">
        <SettingsLayout>
            <template #main>
                <div class="pl-3">
                    <form action="tax-rates.html">
                        <div class="flex items-center justify-between flex-wrap border-b pt-3 mb-3">
                            <div class="mb-3">
                                <h5 class="mb-1">Tax Rates</h5>
                                <p>Tax Rates Configuration</p>
                            </div>
                            <div class="mb-3">
                                <Button icon="ti ti-plus" severity="secondary" variant="outlined" class="mr-2.5" />
                                <Button label="Save" class="" type="submit"></Button>
                            </div>
                        </div>
                        <div class="grid xxl:grid-cols-3 xl:grid-cols-2 gap-4">
                            <!-- TODO: repeat this block for every item in the settings array and add the form submit function -->
                            <div class="mb-3">
                                <label class="form-label text-lg font-semibold">VAT (%)</label>
                                <InputGroup>
                                    <InputNumber size="small" :model-value="16" />
                                    <InputGroupAddon>
                                        <Button size="small" icon="ti ti-edit" severity="secondary" variant="outlined" class="ml-2"></Button>
                                        <Button size="small" icon="ti ti-trash" severity="danger" variant="outlined" class="ml-2"></Button>
                                    </InputGroupAddon>
                                </InputGroup>
                            </div>
                            <div class="">
                                <div class="mb-3">
                                    <label class="form-label">GST</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control" value="14%">
                                        <div class="flex items-center position-absolute end-0 top-0 pr-2 mt-2">
                                            <a href="tax-rates.html#" data-bs-toggle="modal" data-bs-target="#edit_tax_rate"><i class="ti ti-edit pr-2"></i></a>
                                            <a href="tax-rates.html#" data-bs-toggle="modal" data-bs-target="#delete-modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="">
                                <div class="mb-3">
                                    <label class="form-label">HST</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control" value="12%">
                                        <div class="flex items-center position-absolute end-0 top-0 pr-2 mt-2">
                                            <a href="tax-rates.html#" data-bs-toggle="modal" data-bs-target="#edit_tax_rate"><i class="ti ti-edit pr-2"></i></a>
                                            <a href="tax-rates.html#" data-bs-toggle="modal" data-bs-target="#delete-modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>													
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>

