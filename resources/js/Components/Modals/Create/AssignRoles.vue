<script setup lang="ts">
import ModalWrapper from '../ModalWrapper.vue';

const id = 'department-role-user';

</script>

<template>
    <ModalWrapper :form="form" :id="id" resource="department.role" header="Assign Department Role" :route="route('department.roles.assign', department.id)">
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
