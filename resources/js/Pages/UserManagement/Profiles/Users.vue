<!--
  resources/js/Pages/Admin/Users/Index.vue

  Main entry point for the Admin → Users management section.

  Features / Problems Solved:
  ────────────────────────────────────────────────────────────────
  • Renders the full user management interface inside AuthenticatedLayout
  • Displays breadcrumb navigation + contextual action buttons
    → Create Student / Staff / Parent (role-first creation pattern)
  • Bridges Inertia props (from HasTableQuery trait) to UsersDataTable component
  • Handles both full-load and paginated responses gracefully
  • Responsive card wrapper with dark mode support
  • Clean separation: this page only orchestrates layout & data passing
    → All table logic, columns, actions, bulk operations live in UsersDataTable.vue

  Integration Points:
  ───────────────────
  • Expects props shaped like TableQueryProps (from HasTableQuery.php)
    - data: array of users
    - totalRecords
    - currentPage, lastPage, perPage (pagination info)
    - columns: auto-generated column definitions
    - globalFilterables: fields usable in global search
  • Uses UsersDataTable as the single source of truth for table rendering & interaction
  • Buttons link to role-specific creation routes (matches backend "no direct profile create")

  Security / Best Practices:
  ──────────────────────────
  • All destructive actions (delete, toggle status, reset password) are handled
    inside UsersDataTable with proper confirmation dialogs
  • No direct form submissions or dangerous logic here — keeps page lightweight
  • Uses Inertia <Link> for SPA-friendly navigation

  Future Extensions:
  • Add trashed toggle button + ?only=trashed query param support
  • Add global search input above table (if not already in AdvancedDataTable)
  • Add quick filters (role, school, status) as tabs or dropdown
-->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

import type { TableQueryProps } from '@/types/datatables'
import UsersDataTable from '../components/UsersDataTable.vue';
import type { MenuItem } from 'primevue/menuitem';
import { Menu } from 'primevue';
import { usePopup } from '@/helpers';

// ────────────────────────────────────────────────
// Props (matches HasTableQuery return shape)
// ────────────────────────────────────────────────
const props = defineProps<TableQueryProps>()

// Safe computed values — handle both full-load and paginated responses
const users = computed(() => props.data ?? [])
const totalRecords = computed(() => props.totalRecords ?? users.value.length)

// Pass-through computed values for clarity & future-proofing
const columns = computed(() => props.columns ?? [])
const globalFilterFields = computed(() => props.globalFilterables ?? [])

const dropdownOptions = ref<MenuItem[]>([
    {
        label: 'Create Student',
        icon: 'pi pi-user-plus',
        severity: 'success',
        size: 'small',
        url: route('students.create')
    },
    {
        label: 'Create Staff',
        icon: 'pi pi-briefcase',
        severity: 'info',
        size: 'small',
        url: route('staff.create')
    },
    {
        label: 'Create Parent',
        icon: 'pi pi-users',
        severity: 'help',
        size: 'small',
        url: route('guardians.create')
    }
])

const { toggle } = usePopup('create-user-menu')
</script>

<template>

    <Head title="Users" />

    <AuthenticatedLayout title="User Management" :crumb="[
        { label: 'Admin', url: route('admin.dashboard') },
        { label: 'Users' }
    ]" :buttons="[
        {
            label: 'Create Users',
            icon: 'pi pi-users',
            severity: 'primary',
            size: 'small',
            id: 'create-users-dropdown',
            onClick: (e) => toggle(e)
        },
    ]">
        <Menu popup appendTo="body" ref="create-user-menu" :model="dropdownOptions" />
        <div class="space-y-6">
            <!-- Card wrapper for table -->
            <div
                class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                <UsersDataTable :users="users" :total-records="totalRecords" :columns="columns"
                    :global-filter-fields="globalFilterFields" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
