<!-- resources/js/Pages/Admin/Users/components/BulkActionsBar.vue -->
<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Badge from 'primevue/badge'
import { useDeleteResource } from '@/helpers'

const props = defineProps<{
    count: number
}>()

const emit = defineEmits<{
    (e: 'action', action: string): void
    (e: 'clear'): void
}>()

const confirm = useConfirm()
const toast = useToast()
const { deleteResource } = useDeleteResource()

// Action options for the Select component (PrimeVue 4+)
const actionOptions = [
    { label: 'No action selected', value: null },
    { label: 'Activate Selected', value: 'activate', icon: 'pi pi-check-circle', severity: 'success' },
    { label: 'Deactivate Selected', value: 'deactivate', icon: 'pi pi-ban', severity: 'warn' },
    { label: 'Send Password Reset', value: 'reset-password', icon: 'pi pi-key', severity: 'info' },
    { label: 'Assign Role & Department', value: 'assign-role', icon: 'pi pi-user-edit', severity: 'help' },
    { label: 'Delete Selected', value: 'delete', icon: 'pi pi-trash', severity: 'danger' },
]

const selectedAction = ref<string | null>(null)

// Trigger bulk action
const performAction = () => {
    if (!selectedAction.value) return

    const action = selectedAction.value
    selectedAction.value = null // reset

    switch (action) {
        case 'activate':
            confirm.require({
                message: `Activate ${props.count} user(s)?`,
                header: 'Bulk Activation',
                icon: 'pi pi-check-circle',
                acceptLabel: 'Activate',
                acceptProps: { severity: 'success' },
                rejectProps: { severity: 'secondary', outlined: true },
                accept: () => {
                    router.patch(route('api.users.bulk-activate'), { ids: selectedUserIds.value })
                    toast.add({ severity: 'success', summary: 'Activated', detail: `${props.count} users activated`, life: 4000 })
                    emit('clear')
                }
            })
            break

        case 'deactivate':
            confirm.require({
                message: `Deactivate ${props.count} user(s)?`,
                header: 'Bulk Deactivation',
                icon: 'pi pi-ban',
                acceptLabel: 'Deactivate',
                acceptProps: { severity: 'warn' },
                accept: () => {
                    router.patch(route('api.users.bulk-deactivate'), { ids: selectedUserIds.value })
                    toast.add({ severity: 'warn', summary: 'Deactivated', detail: `${props.count} users deactivated`, life: 4000 })
                    emit('clear')
                }
            })
            break

        case 'reset-password':
            confirm.require({
                message: `Send password reset links to ${props.count} user(s)?`,
                header: 'Bulk Password Reset',
                icon: 'pi pi-key',
                accept: () => {
                    router.post(route('api.users.bulk-reset-password'), { ids: selectedUserIds.value })
                    toast.add({ severity: 'info', summary: 'Sent', detail: 'Password reset emails sent', life: 5000 })
                    emit('clear')
                }
            })
            break

        case 'assign-role':
            emit('action', 'assign-role')
            break

        case 'delete':
            deleteResource('users', selectedUserIds.value)
            emit('clear')
            break
    }
}

// Dummy – will be replaced by real selected IDs from parent (UsersDataTable)
const selectedUserIds = computed(() => {
    // In real use: passed from parent via v-model or emit
    // For now, placeholder – parent will bind this
    return []
})
</script>

<template>
    <div
        class="sticky top-0 z-10 -mt-4 mb-4 bg-gradient-to-r from-primary/5 to-primary/10 dark:from-gray-800 dark:to-gray-900 border-b border-primary/20 dark:border-gray-700 shadow-lg transition-all duration-300">
        <div class="flex items-center justify-between px-6 py-4">
            <!-- Left: Selection Info -->
            <div class="flex items-center gap-4">
                <Badge :value="count" severity="info" class="text-lg font-bold" />
                <span class="font-medium text-gray-700 dark:text-gray-300">
                    {{ count }} user{{ count > 1 ? 's' : '' }} selected
                </span>
            </div>

            <!-- Right: Actions -->
            <div class="flex items-center gap-3">
                <!-- Action Selector (PrimeVue Select – NOT deprecated Dropdown) -->
                <Select v-model="selectedAction" :options="actionOptions" optionLabel="label" optionValue="value"
                    placeholder="Choose bulk action..." class="w-80" :pt="{
                        root: { class: 'h-11' },
                        input: { class: 'text-sm' }
                    }">
                    <template #option="slotProps">
                        <div class="flex items-center gap-3">
                            <i :class="slotProps.option.icon" />
                            <span>{{ slotProps.option.label }}</span>
                        </div>
                    </template>
                    <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center gap-3">
                            <i :class="actionOptions.find(o => o.value === slotProps.value)?.icon" />
                            <span>{{actionOptions.find(o => o.value === slotProps.value)?.label}}</span>
                        </div>
                        <span v-else class="text-gray-500">Choose bulk action...</span>
                    </template>
                </Select>

                <!-- Execute Button -->
                <Button label="Apply" :disabled="!selectedAction" severity="primary" size="small" class="font-medium"
                    @click="performAction" />

                <!-- Clear Selection -->
                <Button icon="pi pi-times" severity="secondary" text rounded size="small"
                    v-tooltip.top="'Clear selection'" @click="emit('clear')" />
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.p-select) {
    @apply bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700;
}
</style>
