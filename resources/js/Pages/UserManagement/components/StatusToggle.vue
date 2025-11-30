<!-- resources/js/Pages/Admin/Users/Partials/StatusToggle.vue -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { ref, watch } from 'vue'

const props = defineProps<{
    userId: string
    active: boolean
    modelValue?: boolean
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
    (e: 'update:active', value: boolean): void
}>()

const toast = useToast()
const loading = ref(false)
const internalActive = ref(props.active)

// Sync with parent if v-model used
watch(() => props.active, (val) => {
    internalActive.value = val
})

// Update server + local state
const toggle = async () => {
    if (loading.value) return

    loading.value = true
    const newStatus = !internalActive.value

    try {
        await router.patch(route('api.users.toggle-status', props.userId), {
            active: newStatus
        }, {
            preserveState: true,
            preserveScroll: true
        })

        internalActive.value = newStatus
        emit('update:modelValue', newStatus)
        emit('update:active', newStatus)

        toast.add({
            severity: newStatus ? 'success' : 'warn',
            summary: newStatus ? 'Activated' : 'Deactivated',
            detail: `User is now ${newStatus ? 'active' : 'inactive'}`,
            life: 3000
        })
    } catch (error) {
        internalActive.value = !newStatus // revert
        toast.add({
            severity: 'error',
            summary: 'Failed',
            detail: 'Could not update status',
            life: 4000
        })
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <button @click.prevent="toggle" :disabled="loading" :class="[
        'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:focus:ring-offset-gray-900',
        internalActive
            ? 'bg-primary'
            : 'bg-gray-300 dark:bg-gray-600'
    ]" :aria-pressed="internalActive" role="switch">
        <span :class="[
            'inline-block h-5 w-5 transform rounded-full bg-white shadow-lg transition-transform',
            internalActive ? 'translate-x-6' : 'translate-x-0.5',
            loading ? 'opacity-50' : ''
        ]" />
    </button>
</template>

<style scoped lang="postcss">
button[disabled] {
    @apply cursor-not-allowed opacity-70;
}
</style>
