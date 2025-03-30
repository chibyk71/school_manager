<script setup lang="ts">
import CustomSelect from '@/Components/inputs/customSelect.vue';
import InputWrapper from '@/Components/inputs/InputWrapper.vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from 'primevue';
import { inject } from "vue";

const dialogRef = inject<{ value: { close: (e: Event) => void } }>("dialogRef");

interface DialogRef {
    value: {
        close: (e: Event) => void;
    };
}

const closeDialog = (e: Event): void => {
    (dialogRef as DialogRef)?.value.close(e);
};

const props = defineProps({
    teachers: Array<string>,
    subjects: Array<string>,
    classes: Array<number>,
})

const form = useForm({
    teacher_id: '',
    subject_id: '',
    class_id: '',
    status: '',
})
</script>

<template>
    <form action="" method="post" style="width: 50vw">
        <InputWrapper label="Select Teacher" name="teacher_id" placeholder="Select Teacher" required :error="form.errors.teacher_id" field_type="select">
            <template #input="data">
                <CustomSelect v-bind="data" resource="teacher" v-model="form.teacher_id" />
            </template>
        </InputWrapper>

        <InputWrapper label="Select Subject" name="subject_id" placeholder="Select Subject" required :error="form.errors.subject_id" field_type="select">
            <template #input="data">
                <CustomSelect v-bind="data" resource="subject" v-model="form.subject_id" />
            </template>
        </InputWrapper>
        <InputWrapper label="Select Class" name="class_id" placeholder="Select Class" required :error="form.errors.class_id" field_type="select">
            <template #input="data">
                <CustomSelect v-bind="data" resource="class" v-model="form.class_id" />
            </template>
        </InputWrapper>

        <div class="flex items-center justify-end mt-4 gap-x-2">
            <Button type="button" label="Cancel" class="bg-gray-200 text-gray-700 hover:bg-gray-300" @click="closeDialog" />
            <Button type="submit" label="Save" class="mr-2" :loading="form.processing"  />
        </div>
    </form>
</template>
