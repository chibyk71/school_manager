<!-- resources/js/Pages/Admin/Users/Index.vue -->
<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import UsersDataTable from './components/UsersDataTable.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue';

const props = defineProps<{
    users: any
    columns: any[]
    global_filter_fields: string[]
    can: {
        create: boolean
        edit: boolean
        delete: boolean
        reset_password: boolean
    }
}>()

// ADD THESE TWO LINES
const usersArray = computed(() => props.users?.data ?? [])
const usersTotal = computed(() => props.users?.total ?? props.users?.total ?? 0)

</script>

<template>

    <Head title="Users" />
    <AuthenticatedLayout title="User Management" :crumb="[{ label: 'Admin', url: '/admin' }, { label: 'Users' }]"
        :buttons="[{ label: 'Create Student', icon: 'pi pi-user-plus', severity: 'success', size: 'small', href: route('student.create'), as: Link }, { label: 'Create Staff', icon: 'pi pi-briefcase', severity: 'info', size: 'small', href: route('staff.create'), as: Link }, { label: 'Create Parent', icon: 'pi pi-users', severity: 'help', size: 'small', href: route('guardians.create'), as: Link }]">

        <!-- Main Content -->
        <div class="space-y-6">
            <!-- Users Table – Does Everything -->
            <div
                class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <UsersDataTable :users="usersArray" :total-records="usersTotal" :columns="columns"
                    :global-filter-fields="global_filter_fields" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>