<!-- resources/js/Components/Modals/Create/SchoolSyncModal.vue -->
<script setup lang="ts">
import { useModalForm } from '@/composables/useModalForm'
import { modals } from '@/helpers';
import { MultiSelect, useToast } from 'primevue';
import { getCurrentInstance, onMounted } from 'vue'

const props = defineProps<{
    user: {
        id: string
        full_name: string
        email: string
        type: 'student' | 'staff' | 'guardian'
        avatar_url?: string
        current_schools?: Array<{ id: string; name: string; code?: string }>
    }
}>()

// useModalForm handles form, submit, toast, close, reload
const { form, submit, isLoading } = useModalForm({
    school_ids: [] as string[]
}, {
    url: route('users.sync-schools', useProps().user.id),
    method: 'post',
    successMessage: 'User schools synced successfully',
    reload: ['users'],
    onSuccess: () => {
        // Optional: extra celebration
    }
})

const toast = useToast();

function useProps() {
    return getCurrentInstance()?.props.user as any
}

// Pre-fill current schools on mount
onMounted(() => {
    form.school_ids = props.user.current_schools?.map(s => s.id) || []
})

// Submit with validation
const handleSubmit = () => {
    if (form.school_ids.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'Required',
            detail: 'Please select at least one school',
            life: 4000
        })
        return
    }
    submit()
}
</script>

<template>
    <div class="p-6 space-y-6">
        <!-- User Header -->
        <div
            class="flex items-center gap-5 p-5 bg-gradient-to-br from-indigo-500/10 to-purple-500/10 dark:from-indigo-900/30 dark:to-purple-900/30 rounded-xl border border-indigo-200 dark:border-indigo-800">
            <img :src="user.avatar_url || '/images/avatar-placeholder.png'" alt="Avatar"
                class="w-20 h-20 rounded-full border-4 border-white dark:border-gray-800 shadow-xl object-cover" />
            <div class="flex-1">
                <h3 class="text-xl font-bold">{{ user.full_name }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ user.email }}</p>
                <div class="flex items-center gap-3 mt-2">
                    <UserTypeBadge :type="user.type" size="normal" />
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Currently in {{ user.current_schools?.length || 0 }} school(s)
                    </span>
                </div>
            </div>
        </div>

        <!-- Current Schools -->
        <div v-if="user.current_schools?.length" class="space-y-2">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Currently linked to:</p>
            <div class="flex flex-wrap gap-2">
                <span v-for="school in user.current_schools" :key="school.id"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                    <i class="pi pi-check-circle text-xs" />
                    {{ school.name }}
                </span>
            </div>
        </div>

        <!-- School Multi-Select -->
        <div>
            <label class="block text-sm font-medium mb-2">
                Select Schools / Branches <span class="text-red-600">*</span>
            </label>
            <MultiSelect v-model="form.school_ids" resource="school" option-label="name" option-value="id"
                placeholder="Search and select schools..." fluid searchable class="w-full min-h-12">
                <template #option="{ option }">
                    <div class="flex items-center justify-between w-full">
                        <span class="font-medium">{{ option.name }}</span>
                        <small class="text-gray-500">{{ option.code || 'No code' }}</small>
                    </div>
                </template>
            </MultiSelect>
            <small v-if="form.errors.school_ids" class="text-red-600 text-xs mt-1">{{ form.errors.school_ids }}</small>
        </div>

        <!-- Info Box -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <i class="pi pi-info-circle text-blue-600 dark:text-blue-400 text-xl mt-0.5" />
                <div>
                    <p class="font-medium text-blue-800 dark:text-blue-200">School Sync</p>
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        Users must be linked to at least one school to access its resources. This action updates their
                        tenancy.
                    </p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
            <Button label="Cancel" severity="secondary" @click="modals.close()" :disabled="isLoading" />
            <Button label="Sync Schools" severity="primary" icon="pi pi-sync" :loading="isLoading"
                :disabled="form.school_ids.length === 0" @click="handleSubmit" />
        </div>
    </div>
</template>
