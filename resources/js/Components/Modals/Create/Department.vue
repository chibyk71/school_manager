<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ModalWrapper from '../ModalWrapper.vue';
import InputWrapper from '@/Components/inputs/InputWrapper.vue';

const id = 'department';
type Department = {
    name: string,
    category: string,
    description: string,
}

const props = defineProps<{
    resource_data: Department,
    resource_id: string,
}>()

const form = useForm({
    name: '',
    category: '',
    description: '',
});
</script>

<template>
    <ModalWrapper :form :id resource="department" header="Create Department">
        <PerfectScrollbar>
            <form action="" method="post" class="h-full overflow-hidden relative mb-5">
                <div class="p-4">
                    <p class="text-sm text-gray-500">Fill in the details below to create a new department.</p>
                </div>
                <div class="px-4 pt-2">
                    <InputWrapper label="Department Name" field_type="text" v-model="form.name" name='name'
                        hint="The name of the department" :error="form.errors.name" />
                    <InputWrapper label="Department Category" field_type="text" v-model="form.category" name='code'
                        hint="" :error="form.errors.category" />
                    <InputWrapper label="Description" field_type="textarea" v-model="form.description" name='description'
                        hint="A brief description of the department" :error="form.errors.description" />
                </div>
            </form>
        </PerfectScrollbar>
    </ModalWrapper>
</template>
