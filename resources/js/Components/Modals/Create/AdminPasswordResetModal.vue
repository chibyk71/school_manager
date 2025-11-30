<!-- resources/js/Components/Modals/Create/AdminPasswordResetModal.vue -->
<script setup lang="ts">
import { useModalForm } from '@/composables/useModalForm'
import { modals } from '@/helpers';
import UserTypeBadge from '@/Pages/UserManagement/components/UserTypeBadge.vue';
import { useToast } from 'primevue';
import { ref, computed, getCurrentInstance } from 'vue'


defineProps<{
    user: {
        id: string
        full_name: string
        email: string
        type: 'student' | 'staff' | 'guardian'
        avatar_url?: string
    }
}>()

// Reactive password fields (not part of Inertia form)
const password = ref('')
const confirmPassword = ref('')
const toast = useToast();

// Password strength
const strength = computed(() => {
    const p = password.value
    if (!p) return { level: 0, label: '', color: '' }
    if (p.length < 6) return { level: 1, label: 'Weak', color: 'bg-red-500' }
    if (p.length < 10) return { level: 2, label: 'Fair', color: 'bg-orange-500' }
    if (/[A-Z]/.test(p) && /[0-9]/.test(p) && /[^A-Za-z0-9]/.test(p))
        return { level: 4, label: 'Strong', color: 'bg-green-500' }
    return { level: 3, label: 'Good', color: 'bg-blue-500' }
})

const passwordsMatch = computed(() =>
    !confirmPassword.value || password.value === confirmPassword.value
)

// useModalForm handles Inertia submission
const { form, submit, isLoading } = useModalForm({
    password: '',
    password_confirmation: ''
}, {
    url: route('api.users.reset-password-admin', useProps().user.id),
    method: 'post',
    successMessage: `Password changed successfully for ${useProps().user.full_name}`,
    onSuccess: () => {
        password.value = ''
        confirmPassword.value = ''
    }
})

function useProps() {
    return getCurrentInstance()?.props.user as any
}

// Submit with client validation
const handleSubmit = () => {
    if (password.value.length < 8) {
        toast.add({ severity: 'warn', summary: 'Weak Password', detail: 'Password must be at least 8 characters', life: 4000 })
        return
    }
    if (!passwordsMatch.value) {
        toast.add({ severity: 'warn', summary: 'Mismatch', detail: 'Passwords do not match', life: 4000 })
        return
    }

    form.password = password.value
    form.password_confirmation = confirmPassword.value
    submit()
}
</script>

<template>
    <div class="p-6 space-y-6">
        <!-- User Info -->
        <div
            class="flex items-center gap-5 p-5 bg-gradient-to-r from-primary/5 to-primary/10 dark:from-gray-800 dark:to-gray-900 rounded-xl border border-primary/20">
            <img :src="user.avatar_url || '/images/avatar-placeholder.png'"
                class="w-20 h-20 rounded-full border-4 border-white dark:border-gray-800 shadow-lg object-cover" />
            <div>
                <h3 class="text-xl font-bold">{{ user.full_name }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ user.email }}</p>
                <UserTypeBadge :type="user.type" size="normal" class="mt-2" />
            </div>
        </div>

        <!-- Warning -->
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <i class="pi pi-exclamation-triangle text-yellow-600 dark:text-yellow-400 text-xl mt-0.5" />
                <div>
                    <p class="font-medium text-yellow-800 dark:text-yellow-200">Admin Password Reset</p>
                    <p class="text-sm text-yellow-700 dark:text-yellow-300">
                        You are changing this user's password directly. The user will be logged out of all devices.
                    </p>
                </div>
            </div>
        </div>

        <!-- New Password -->
        <div>
            <label class="block text-sm font-medium mb-2">New Password <span class="text-red-600">*</span></label>
            <InputText v-model="password" type="password" placeholder="Enter strong password..."
                autocomplete="new-password" class="w-full" fluid />
            <small v-if="form.errors.password" class="text-red-600 text-xs">{{ form.errors.password }}</small>
        </div>

        <!-- Strength Meter -->
        <div v-if="password" class="space-y-1">
            <div class="flex justify-between text-xs">
                <span>Password Strength</span>
                <span :class="strength.color.replace('bg-', 'text-') + ' font-medium'">{{ strength.label }}</span>
            </div>
            <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div :class="strength.color" class="h-full transition-all"
                    :style="{ width: strength.level * 25 + '%' }" />
            </div>
        </div>

        <!-- Confirm Password -->
        <div>
            <label class="block text-sm font-medium mb-2">Confirm Password <span class="text-red-600">*</span></label>
            <InputText v-model="confirmPassword" type="password" placeholder="Repeat password"
                :class="{ 'p-invalid': confirmPassword && !passwordsMatch }" class="w-full" fluid />
            <small v-if="form.errors.password_confirmation" class="text-red-600 text-xs">{{
                form.errors.password_confirmation }}</small>
        </div>

        <!-- Match Status -->
        <div v-if="confirmPassword" class="flex items-center gap-2 text-sm">
            <i :class="passwordsMatch ? 'pi pi-check-circle text-green-500' : 'pi pi-times-circle text-red-500'" />
            <span :class="passwordsMatch ? 'text-green-600 dark:text-green-400' : 'text-red-600'">
                {{ passwordsMatch ? 'Passwords match' : 'Passwords do not match' }}
            </span>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
            <Button label="Cancel" severity="secondary" @click="modals.close()" :disabled="isLoading" />
            <Button label="Change Password" severity="danger" icon="pi pi-key" :loading="isLoading"
                :disabled="!passwordsMatch || password.length < 8 || isLoading" @click="handleSubmit" />
        </div>
    </div>
</template>
