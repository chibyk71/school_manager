<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ModalWrapper from '../ModalWrapper.vue';
import InputWrapper from '@/Components/inputs/InputWrapper.vue';
import CustomSelect from '@/Components/inputs/customSelect.vue';
import { Button } from 'primevue';

const id = 'department-role';

const props = defineProps<{
    department: {
        id: string,
        name: string,
        roles: {id:string}[],
    },
    resource_id: string,
}>()

const form = useForm<{roles: string[], department: string}>({
    department: props.department.id ?? '',
    roles: [],
})

function mapRoleIds(): string[] {
    return props.department.roles.map(role => role.id);
}

</script>
<template>
    <ModalWrapper :form :id resource="department.role" header="Assign Department Role" :route="route('department.roles.assign', department.id)">
        <form action="" method="post" class="h-full overflow-hidden relative mb-5" id="department-role">
            <div class="p-4">
                <p class="text-sm text-gray-500">Fill in the details below to assign a role to a department.</p>
            </div>
            <div class="px-4 pt-2">
                <InputWrapper label="Department Name" field_type="text" name='name' :default_value="department.name"
                    hint="The name of the department" :error="form.errors.department" noedit />
                <InputWrapper label="Roles" field_type="select" name='roles'>
                    <template #input="slotProps">
                        <CustomSelect @fetch-success="()=> form.roles = mapRoleIds()" resource="role" fluid v-model="form.roles" multiple v-bind="slotProps" />
                    </template>
                </InputWrapper>
            </div>
        </form>
    </ModalWrapper>
</template>
