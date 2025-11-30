<!-- resources/js/Pages/Admin/Users/Index.vue -->
<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import UsersDataTable from './components/UsersDataTable.vue'
import BulkActionsBar from './components/BulkActionsBar.vue'
import { modals } from '@/helpers'
import { router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { ref } from 'vue'

const toast = useToast()
const selectedUsers = ref<any[]>([])

// Open modals from actions
const openRoleModal = (user: any) => {
    modals.open('assign-role-department',{
        data: { user }
    })
}

const openPasswordReset = (user: any) => {
    modals.open('admin-password-reset',{
        data: { user }
    })
}

const openSchoolSync = (user: any) => {
    modals.open('school-sync', {
        data: { user }
    })
}

// Bulk action handler (from BulkActionsBar)
const handleBulkAction = (action: string) => {
    const ids = selectedUsers.value.map(u => u.id)

    switch (action) {
        case 'reset-password':
            // Optional: open a bulk password reset modal later
            router.post(route('api.users.bulk-reset-password'), { ids }, {
                onSuccess: () => {
                    toast.add({ severity: 'info', summary: 'Sent', detail: 'Password reset links sent', life: 5000 })
                }
            })
            break
        case 'assign-role':
            // Future: bulk role assign modal
            toast.add({ severity: 'info', summary: 'Coming Soon', detail: 'Bulk role assignment', life: 3000 })
            break
    }
}
</script>

<template>
    <AuthenticatedLayout title="User Management" :crumb="[{ label: 'Admin', url: '/admin' },{ label: 'Users' }]">
        <!-- Header -->
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">User Management</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Manage all users across schools — students, staff, and parents.
                    </p>
                </div>

                <!-- Future: Create User via Profile Type -->
                <div class="flex items-center gap-3">
                    <Button label="Create Student" icon="pi pi-user-plus" severity="success" size="small"
                        @click="router.visit(route('students.create'))" />
                    <Button label="Create Staff" icon="pi pi-briefcase" severity="info" size="small"
                        @click="router.visit(route('staff.create'))" />
                    <Button label="Create Parent" icon="pi pi-users" severity="help" size="small"
                        @click="router.visit(route('guardians.create'))" />
                </div>
            </div>
        </template>

        <!-- Main Content -->
        <div class="space-y-6">
            <!-- Bulk Actions Bar (Sticky when selection active) -->
            <BulkActionsBar v-if="selectedUsers.length" :count="selectedUsers.length" @action="handleBulkAction"
                @clear="selectedUsers = []" />

            <!-- Users Table -->
            <div
                class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <UsersDataTable v-model:selected-rows="selectedUsers" @open-role-modal="openRoleModal"
                    @open-reset-password="openPasswordReset" @open-school-sync="openSchoolSync" />
            </div>

            <!-- Stats Footer (Optional) -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-5 rounded-xl shadow-md">
                    <div class="text-3xl font-bold">1,284</div>
                    <div class="text-sm opacity-90">Total Users</div>
                </div>
                <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white p-5 rounded-xl shadow-md">
                    <div class="text-3xl font-bold">892</div>
                    <div class="text-sm opacity-90">Active</div>
                </div>
                <div class="bg-gradient-to-r from-purple-500 to-indigo-600 text-white p-5 rounded-xl shadow-md">
                    <div class="text-3xl font-bold">312</div>
                    <div class="text-sm opacity-90">Staff</div>
                </div>
                <div class="bg-gradient-to-r from-orange-500 to-red-600 text-white p-5 rounded-xl shadow-md">
                    <div class="text-3xl font-bold">756</div>
                    <div class="text-sm opacity-90">Students</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>