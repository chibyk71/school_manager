<script setup lang="ts">
import { useDeleteResource } from '@/helpers';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button, Card, Column, DataTable, Dialog, IconField, InputIcon, InputText, Textarea, useToast } from 'primevue';
import { computed, ref } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import InputWrapper from '@/Components/inputs/InputWrapper.vue';

type Section = {
    id: number|string,
    name: string,
    display_name: string,
    description: string,
    school: {
        name: string,
        id: string,
    }
}

defineProps<{
    sections: Section[];
}>()

const { deleteResource } = useDeleteResource();
const toast = useToast();
const selectedSections = ref([]),
    selectedIds = computed(() => selectedSections.value.map((section: Section) => section.id)),
    filters = ref({  
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    }),
    showmodal = ref(false),
    editingSectionId = ref<string|number>('');

    const form = useForm({
        name: '',
        display_name: '',
        description: '',
        school_id: ''
    });

    const openEditModal = (data: Section) => {
        editingSectionId.value = data.id
        showmodal.value = true;
        setTimeout(() => {
            form.name = data.name;
            form.display_name = data.display_name;
            form.description = data.description;
            form.school_id = data.school.id;
        }, 500);
    }

    const formActionUrl = computed(() => {
        return editingSectionId.value ? route('sections.update', editingSectionId.value) : route('sections.store');
    })

</script>

<template>
    <Head title="School Sections" />
    <AuthenticatedLayout title="School Sections" :crumb="[{ label: 'Dashboard' }, { label: 'Academic' }, { label: 'School Sections' }]" :buttons="[{ label: 'Add Sections', icon: 'ti ti-school', onClick: ()=>showmodal = true},{icon:'ti ti-trash', severity:'danger', label:'Delete Selected', onClick: ()=> deleteResource('sections', selectedIds), class: selectedIds.length < 1? 'hidden': '' }
    ]">

        <!-- Guardians List -->
        <Card>
            <template #content class="">
                <DataTable v-model:filters="filters" :value="sections" v-model:selection="selectedSections" :globalFilterFields="['name','school.name']">
                    <template #header>
                        <div class="flex justify-end">
                            <IconField>
                                <InputIcon>
                                    <i class="ti ti-search" />
                                </InputIcon>
                                <InputText v-model="filters['global'].value" placeholder="Keyword Search" />
                            </IconField>
                        </div>
                    </template>
                    <Column selection-mode="multiple" />
                    <Column header="S/N" sortable>
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                        <template #filterapply="slotProps"></template>
                    </Column>
                    <Column header="Name" field="name" sortable></Column>
                    <Column header="Display Name" field="display_name"/>
                    <Column header="School" sortable>
                        <template #body="slotProps">
                            {{ slotProps.data.school?.name ?? "Nil" }}
                        </template>
                    </Column>
                    <Column header="Description" field="description"></Column>
                    <Column header="Action">
                      <template #body="slotProps">
                        <div class="flex items-center gap-x-2">
                            <Button size="small" icon="pi pi-pencil" @click="openEditModal(slotProps.data)" v-tooltip="`Edit School section`" />
                            <Button size="small" @click="deleteResource('sections', [slotProps.data.id])" icon="pi pi-trash" severity="danger" v-tooltip="`Delete School section`" />
                        </div>
                      </template>
                    </Column>
                </DataTable>
            </template>
        </Card>
        <!-- /Guardians List -->
    </AuthenticatedLayout>

    <Dialog header="Create Sections" modal :visible="showmodal" class="xl:w-2/4 sm:w-3/4 w-full" @hide="editingSectionId = ''">

        <template #closeicon="slotProps">
            <i class="ti ti-x font-semibold" @click="showmodal = false" />
        </template>

        <form action="" method="post">
            <InputWrapper v-model="form.name" :error="form.errors.name" field_type="text" name="name" required label="Section Name">
            </InputWrapper>

            <InputWrapper v-model="form.display_name" :error="form.errors.display_name" field_type="text" label="Display Name" name="display_name" placeholder="More Descriptie name To display" />

            <InputWrapper :error="form.errors.description"  name="description" label="Description" field_type="textarea">
                <template #input="{invalid}">
                    <Textarea v-model="form.description" fluid :invalid rows="3"  />
                </template>
            </InputWrapper>

        </form>
        <template #footer>
            <div class="flex items-center gap-x-3 justify-end">
                <Button label="Cancel" severity="secondary" @click="showmodal = false" />
                <Button label="Save" :loading="form.processing" @click="form.post(formActionUrl, { 
                    onSuccess: (e) => {
                        console.log(e);
                        showmodal = false
                        toast.add({ severity: 'success', summary: 'Success', detail: 'Section Created Successfully', life: 3000 })
                    },
                    onError: (e) => {
                        console.log(e);

                        toast.add({ severity: 'error', summary: 'Error', detail: 'There was an error, creating the Section', life: 3000 })
                    }
                })" />
            </div>
        </template>
    </Dialog>
</template>
