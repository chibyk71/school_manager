<script setup lang="ts">
import InputWrapper from '@/Components/inputs/InputWrapper.vue';
import { useDeleteResource } from '@/helpers';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { Button, Card, Column, DataTable, Dialog, FileUpload, useToast } from 'primevue';
import { ref } from 'vue';

type School = {
    id: string,
    name: string,
    slug: string,
    logo: string,
    phone_one: string,
    phone_two: string,
}

const { deleteResource } = useDeleteResource()

const Toast = useToast();
const props = defineProps<{schools:Array<School>}>()

const selectedSchools = ref([]);

const isEditingId = ref<string>(''),
    modalVisible = ref(false);

const form = useForm({
    name: '',
    slug: '',
    logo: '',
    phone_one: '',
    phone_two: '',
})

const openEditModal =(schoolData: School) => {
    modalVisible.value = true;
    isEditingId.value = schoolData.id;
    setTimeout(() => {
        form.name = schoolData.name;
        form.slug = schoolData.slug;
        form.logo = schoolData.logo;
        form.phone_one = schoolData.phone_one;
        form.phone_two = schoolData.phone_two;
    }, 500);
}



const submit = () => {
    form.post(route(isEditingId ? 'school.update' : 'school.store', isEditingId.value), {
        onFinish(e) {
            modalVisible.value = false;
            isEditingId.value = ""
        },
        onSuccess() {
            Toast.add({ severity: 'success', summary: 'Success', detail: 'School created successfully.', life: 3000 });
        },
        onError() {
            Toast.add({ severity: 'error', summary: 'Error', detail: 'There was an error, creating the School', life: 3000 });
        }
    })
}
</script>

<template>
    <AuthenticatedLayout title="Schools" :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'School' }]"
        :buttons="[{ label: 'Add School', icon: 'ti ti-school', onClick: (e) => modalVisible = !modalVisible },
        { label: 'Delete Selected', icon: 'ti ti-trash', severity: 'danger', class: !selectedSchools.length ? 'hidden' : '' }
        ]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable v-model:selection="selectedSchools" :value="schools" stripedRows>
                    <Column selection-mode="multiple" />
                    <Column header="ID" field="id" />
                    <Column header="Name" field="name" sortable></Column>
                    <Column header="Unique Code" field="slug" sortable></Column>
                    <Column header="Logo" field="logo" />
                    <Column header="Phone One" field="phone_one" />
                    <Column header="Phone Two" field="phone_two" />
                    <Column header="Action">
                        <template #body="slotProps">
                            <div class="flex items-center gap-x-2">
                                <Button @click="openEditModal(slotProps.data)" icon="ti ti-edit" size="small" />
                                <Button @click="deleteResource('school', slotProps.data.id)" icon="ti ti-trash" size="small" severity="danger" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>

    <Dialog header="Create School" modal block-scroll :visible="modalVisible">
        <form @submit.prevent="submit" id="school_form" action="" method="post" class="w-[600px]">
            <InputWrapper :error="form.errors.name" v-model="form.name" required label=" School Name" name="name"
                placeholder="School's Full Name" field_type="text">
            </InputWrapper>

            <InputWrapper label="Short Name" v-model="form.slug" :error="form.errors.slug" name="slug"
                placeholder="Unique Identifier" field_type="text">
            </InputWrapper>

            <InputWrapper :error="form.errors.logo" label="Logo" name="logo" field_type="file">
                <template #input="props">
                    <FileUpload v-model="form.logo" />
                </template>
            </InputWrapper>

            <InputWrapper :error="form.errors.phone_one" v-model="form.phone_one" required label="Phone One"
                name="phone_one" placeholder="Phone One" field_type="tel">
            </InputWrapper>

            <InputWrapper :error="form.errors.phone_two" v-model="form.phone_two" label="Phone Two" name="phone_two"
                placeholder="Phone Two" field_type="tel">
            </InputWrapper>
        </form>
        <!-- <template #container="slotProps"></template> -->
        <template #closeicon="slotProps">
            <Button size="small" icon="ti ti-x" severity="secondary" @click="modalVisible = false" />
        </template>
        <template #footer>
            <div class="flex items-center justify-end gap-x-2.5">
                <Button type="reset" form="school_form" severity="secondary" label="Cancel"
                    @click="modalVisible = false" />
                <Button :loading="form.processing" type="submit" label="Save" form="school_form" />
            </div>
        </template>
    </Dialog>
</template>
