<!-- resources/js/Pages/Admin/Users/components/UserActionsDropdown.vue -->
<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { computed } from 'vue'
import { useDeleteResource, usePopup } from '@/helpers'
import { MenuItemCommandEvent } from 'primevue/menuitem'

const props = defineProps<{
    user: {
        id: string
        full_name: string
        email: string
        is_active: boolean
        type: 'student' | 'staff' | 'guardian'
        can_delete: boolean
        can_edit: boolean
        can_reset_password: boolean
        can_assign_role: boolean
    }
}>()

const emit = defineEmits<{
    (e: 'toggle-status'): void
    (e: 'open-role-modal'): void
    (e: 'open-school-sync'): void
    (e: 'open-reset-password'): void
}>()

const { deleteResource } = useDeleteResource()
const confirm = useConfirm()
const toast = useToast()
const { toggle } = usePopup('user-actions-dropdown')

console.log(props.user.type);


// Menu items – dynamic based on user state & permissions
const menuItems = computed(() => {
    const items = []

    // 1. View Profile (always shown)
    items.push({
        label: 'View Profile',
        icon: 'pi pi-eye',
        command: () => {
            // const routeName = props.user.type === 'student'
            //     ? 'students.show'
            //     : props.user.type === 'staff'
            //         ? 'staff.show'
            //         : 'guardians.show'
            // TODO use real profile route
            router.visit(route('students.show', props.user.id))
        }
    })

    // // 2. Edit Email (only if allowed)
    // if (props.user.can_edit) {
    //     items.push({
    //         label: 'Edit Email',
    //         icon: 'pi pi-pencil',
    //         command: () => {
    //             // We'll build quick inline edit later – for now, open modal
    //             console.log('Edit email for', props.user.id)
    //         }
    //     })
    // }

    // // 3. Reset Password
    // if (props.user.can_reset_password) {
    //     items.push({
    //         label: 'Send Password Reset',
    //         icon: 'pi pi-key',
    //         command: () => emit('open-reset-password')
    //     })
    // }

    // // 4. Assign Role & Department
    // if (props.user.can_assign_role) {
    //     items.push({
    //         label: 'Assign Role & Department',
    //         icon: 'pi pi-user-edit',
    //         command: () => emit('open-role-modal')
    //     })
    // }

    // // 5. Sync Schools
    // items.push({
    //     label: 'Sync to Schools',
    //     icon: 'pi pi-building',
    //     command: () => emit('open-school-sync')
    // })

    // // 6. Activate / Deactivate
    // items.push({
    //     label: props.user.is_active ? 'Deactivate User' : 'Activate User',
    //     icon: props.user.is_active ? 'pi pi-ban' : 'pi pi-check-circle',
    //     command: () => {
    //         confirm.require({
    //             message: `Are you sure you want to ${props.user.is_active ? 'deactivate' : 'activate'} this user?`,
    //             header: props.user.is_active ? 'Deactivate User' : 'Activate User',
    //             icon: 'pi pi-exclamation-triangle',
    //             acceptLabel: props.user.is_active ? 'Deactivate' : 'Activate',
    //             acceptProps: { severity: props.user.is_active ? 'danger' : 'success' },
    //             rejectProps: { severity: 'secondary', outlined: true },
    //             accept: async () => {
    //                 await router.patch(route('api.users.toggle-status', props.user.id), {
    //                     active: !props.user.is_active
    //                 })
    //                 toast.add({
    //                     severity: 'success',
    //                     summary: 'Success',
    //                     detail: `User ${props.user.is_active ? 'deactivated' : 'activated'} successfully`,
    //                     life: 3000
    //                 })
    //                 emit('toggle-status')
    //             }
    //         })
    //     }
    // })

    // // 7. Delete (only if allowed)
    // if (props.user.can_delete) {
    //     items.push({
    //         separator: true
    //     }, {
    //         label: 'Delete User',
    //         icon: 'pi pi-trash',
    //         class: 'text-red-600 dark:text-red-400',
    //         command: () => {
    //             deleteResource('users', [props.user.id])
    //         }
    //     })
    // }

    return items
})
</script>

<template>
    <div class="inline-block">
        <Button icon="pi pi-ellipsis-v" severity="secondary" text rounded size="small" class="w-9 h-9" @click="toggle"
            aria-haspopup="true" aria-controls="user_actions_menu" />

        <Menu ref="user-actions-dropdown" :model="menuItems" :popup="true" id="user_actions_menu" class="w-64">
            <template #item="{ item, props: itemProps }">
                <!-- <a v-if="item.command" v-bind="itemProps" :href="void(0)"  class="flex items-center gap-3 px-4 py-3 text-sm hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" :class="{ 'text-red-600 dark:text-red-400 font-medium': typeof item.label === 'string' && item.label.includes('Delete') }"
                    @click="(e)=> item.command?.(e as unknown as MenuItemCommandEvent)">
                    <i :class="item.icon" />
                    <span>{{ item.label }}</span>
                </a>
                <Link v-else-if="item.to" :href="item.to" v-bind="itemProps"
                    class="flex items-center gap-3 px-4 py-3 text-sm hover:bg-gray-100 dark:hover:bg-gray-800">
                    <i :class="item.icon" />
                    <span>{{ item.label }}</span>
                </Link> -->
            </template>
        </Menu>
    </div>
</template>

<style scoped lang="postcss">
:deep(.p-menu .p-menuitem-link) {
    @apply rounded-md;
}
</style>
