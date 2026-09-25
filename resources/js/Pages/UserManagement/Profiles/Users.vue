<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import UsersDataTable from '../components/UsersDataTable.vue'

defineProps<{
    users: any[]
    columns: any[]
}>()
</script>

<template>
    <AuthenticatedLayout title="Users">
        <div class="space-y-6">
            <div
                class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                <UsersDataTable :users="users" :columns="columns" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
