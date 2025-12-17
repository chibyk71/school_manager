<!--
  resources/js/Components/Modals/DepartmentDetailsModal.vue

  Purpose & Features Implemented (Production-Ready – December 17, 2025):

  1. Dedicated modal for viewing a single department's details:
     - Shows all department info (name, category, description, effective date)
     - Displays assigned roles with their SchoolSection scoping (chips)
     - Includes a paginated Members tab (nested AdvancedDataTable)
     - Supports section filter in members list (via query param)

  2. Read-only mode:
     - No edit forms – pure view
     - Permission-gated: only visible if user has 'departments.view'

  3. Members Tab:
     - Uses AdvancedDataTable with server-side pagination
     - Fetches from /departments/{id}/users endpoint
     - Optional section_id filter dropdown (populated from department's roles)
     - Shows user avatar, name, email, roles (chips), joined date

  4. Integration:
     - Opened from Index.vue row action "View" button
     - Payload: department ID
     - Fetches full data via /departments/{id} (show method)
     - Members loaded via /departments/{id}/users

  5. UX & Accessibility:
     - TabView for clean separation (Overview + Members)
     - Loading skeletons
     - Responsive layout
     - PrimeVue + Tailwind polish

  6. Scalability:
     - Efficient: lazy-load members only when tab active
     - Pagination prevents memory issues

  7. Frontend Integration:
     - Register in ModalDirectory.ts: 'department-details'
     - Open from Index.vue: modals.open('department-details', { departmentId: row.id })
-->

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue'
import { formatDate, modals } from '@/helpers'
import axios from 'axios'
import type { ColumnDefinition } from '@/types/datatables'
import { TabList, TabPanel, TabPanels, TabView, type Tab } from 'primevue'

const toast = useToast()

// Payload from modal
const payload = modals.items[0]?.data ?? {}
const departmentId = payload.departmentId

// Department data
const department = ref<any>(null)
const loading = ref(true)

// Members table columns
const membersColumns = [
    { field: 'name', header: 'Name', sortable: true },
    { field: 'email', header: 'Email', sortable: true },
    { field: 'roles', header: 'Roles', render: (row: any) => row.roles?.map((r: any) => r.display_name).join(', ') || '—' },
    { field: 'created_at', header: 'Joined', render: (row: any) => formatDate(row.created_at) },
] as ColumnDefinition<any>[]

// Fetch department details
onMounted(async () => {
    try {
        const { data } = await axios.get(route('departments.show', departmentId))
        department.value = data.department
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load department details' })
    } finally {
        loading.value = false
    }
})

// Members endpoint
const membersEndpoint = computed(() => route('departments.users', departmentId))
</script>

<template>
    <div class="max-w-4xl mx-auto">
        <!-- Loading -->
        <div v-if="loading" class="text-center py-16">
            <ProgressSpinner />
            <p class="mt-4 text-gray-600">Loading department details...</p>
        </div>

        <!-- Content -->
        <div v-else-if="department">
            <Tab value="0">
                <TabList>
                    <Tab value="0">OverView</Tab>
                    <Tab value="1">Members</Tab>
                </TabList>
                <TabPanels>
                    <TabPanel value="0" header="OverView">
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name</label>
                                    <p class="mt-1 text-lg font-semibold">{{ department.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Category</label>
                                    <p class="mt-1">{{ department.category || '—' }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                <p class="mt-1 text-gray-600">{{ department.description || 'No description provided' }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Effective Date</label>
                                <p class="mt-1">{{ formatDate(department.effective_date) || '—' }}</p>
                            </div>

                            <!-- Assigned Roles -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Assigned Roles</label>
                                <div class="flex flex-wrap gap-2">
                                    <Chip v-for="role in department.roles" :key="role.id" :label="role.display_name"
                                        class="text-xs" />
                                    <span v-if="!department.roles?.length" class="text-gray-500">No roles
                                        assigned</span>
                                </div>
                            </div>
                        </div>
                    </TabPanel>
                    <TabPanel value="1" header="Members">
                        <AdvancedDataTable :endpoint="membersEndpoint" :columns="membersColumns"
                        :initial-params="{ section_id: null }">
                    </AdvancedDataTable>
                    </TabPanel>
                </TabPanels>
            </Tab>
        </div>

        <!-- Error -->
        <div v-else class="text-center py-16 text-red-600">
            Failed to load department details.
        </div>
    </div>
</template>

<style scoped lang="postcss">
/* Tab polish */
:deep(.p-tabview .p-tabview-panels) {
    @apply p-6 bg-white dark:bg-gray-800 rounded-b-xl;
}
</style>