<!-- resources/js/Components/Modals/Create/AssignRoleDepartmentModal.vue -->
<script setup lang="ts">
import { useModalForm } from '@/composables/useModalForm'
import { modals } from '@/helpers';
import UserTypeBadge from '@/Pages/UserManagement/components/UserTypeBadge.vue';
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';

// Props from modals.open('assign-role-department', { user })
const props = defineProps<{
    user: {
        id: string
        full_name: string
        avatar_url?: string
        email: string
        type: 'student' | 'staff' | 'guardian'
        current_department?: string
        current_roles?: string[]
    }
}>()

// All form logic moved to useModalForm
const { form, submit, isLoading } = useModalForm<{
    department_id: string
    role_ids: string[]
}>({
    department_id: '',
    role_ids: [],
}, {
    url: route('api.users.assign-role-department', props.user.id),
    method: 'post',
    successMessage: 'Role(s) and department assigned successfully',
    reload: ['users'],
})

// Load departments & roles
const departments = ref<any[]>([])
const roles = ref<any[]>([])
const loadingRoles = ref(false)

onMounted(async () => {
    const { data } = await axios.get(route('api.departments.index'), {
        params: { per_page: 100, with: 'roles' }
    })
    departments.value = data.data || []
})

watch(() => form.department_id, (id) => {
    if (!id) {
        roles.value = []
        form.role_ids = []
        return
    }
    const dept = departments.value.find(d => d.id === id)
    roles.value = dept?.roles || []
    form.role_ids = (dept?.id === props.user.current_department) ? props.user.current_roles || [] : []
})
</script>

<template>
    <div class="p-6 space-y-6">
        <!-- User Header -->
        <div class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <img :src="user.avatar_url || '/avatar.png'" class="w-16 h-16 rounded-full object-cover" />
            <div>
                <h3 class="font-semibold text-lg">{{ user.full_name }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ user.email }} •
                    <UserTypeBadge :type="user.type" size="small" />
                </p>
            </div>
        </div>

        <!-- Department -->
        <div>
            <label class="block text-sm font-medium mb-2">Department <span class="text-red-600">*</span></label>
            <Select v-model="form.department_id" :options="departments" option-label="name" option-value="id"
                placeholder="Select department..." class="w-full" fluid />
            <small v-if="form.errors.department_id" class="text-red-600 text-xs">{{ form.errors.department_id }}</small>
        </div>

        <!-- Roles -->
        <div v-if="form.department_id">
            <label class="block text-sm font-medium mb-2">Role(s) <span class="text-red-600">*</span></label>
            <MultiSelect v-model="form.role_ids" :options="roles" option-label="display_name" option-value="id"
                placeholder="Select roles..." display="chip" class="w-full" fluid>
                <template #option="{ option }">
                    <div class="flex justify-between w-full">
                        <span>{{ option.display_name }}</span>
                        <small class="text-gray-500">{{ option.name }}</small>
                    </div>
                </template>
            </MultiSelect>
            <small v-if="form.errors.role_ids" class="text-red-600 text-xs">{{ form.errors.role_ids }}</small>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end gap-3 pt-4 border-t">
            <Button label="Cancel" severity="secondary" @click="modals.close()" />
            <Button label="Assign" :loading="isLoading" :disabled="!form.department_id || form.role_ids.length === 0"
                @click="submit" />
        </div>
    </div>
</template>
