<script setup>
import { ref, computed, watch } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
// import AppLayout from '@/Layouts/AppLayout.vue'
import { format } from 'date-fns'

// Props from controller
const props = defineProps({
    logs: Object,
    filters: Object,
    schools: Array
})

// Reactive filters
const search = ref(props.filters.search ?? '')
const channel = ref(props.filters.channel ?? '')
const success = ref(props.filters.success ?? '')
const provider = ref(props.filters.provider ?? '')
const schoolId = ref(props.filters.school_id ?? '')

// Debounced search
watch(search, (value) => {
    router.get(route('admin.notifications.history'), { search: value, ...getFilters() }, {
        preserveState: true,
        replace: true
    })
}, { debounce: 500 })

// Other filters
watch([channel, success, provider, schoolId], () => {
    router.get(route('admin.notifications.history'), getFilters(), {
        preserveState: true,
        replace: true
    })
})

const getFilters = () => ({
    channel: channel.value || null,
    success: success.value || null,
    provider: provider.value || null,
    school_id: schoolId.value || null,
    search: search.value || null
})

// Channel icons & colors
const channelInfo = (channel) => {
    switch (channel) {
        case 'sms': return { icon: '📱', color: 'green', label: 'SMS' }
        case 'mail': return { icon: '✉️', color: 'blue', label: 'Email' }
        default: return { icon: '🔔', color: 'gray', label: channel }
    }
}

// Success badge
const statusBadge = (success) => {
    return success
        ? { text: 'Delivered', color: 'bg-green-100 text-green-800' }
        : { text: 'Failed', color: 'bg-red-100 text-red-800' }
}
</script>

<template>
    <AppLayout title="Notification History">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Communication History</h1>
                <p class="mt-2 text-gray-600">View all SMS, Email, and future notifications sent across all schools</p>
            </div>

            <!-- Filters -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Search</label>
                        <input v-model="search" type="text" placeholder="Phone, email, message..."
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Channel</label>
                        <select v-model="channel"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All Channels</option>
                            <option value="sms">SMS</option>
                            <option value="mail">Email</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select v-model="success" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">All</option>
                            <option value="1">Delivered</option>
                            <option value="0">Failed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Provider</label>
                        <input v-model="provider" type="text" placeholder="e.g. multitexter"
                            class="mt-1 block w-full rounded-md border-gray-300" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">School</label>
                        <select v-model="schoolId" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="">All Schools</option>
                            <option v-for="school in schools" :key="school.id" :value="school.id">
                                {{ school.name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button @click="router.get(route('admin.notifications.history'))"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Results Table -->
            <div class="bg-white shadow overflow-hidden rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Channel</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Recipient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Message</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Provider</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ format(new Date(log.created_at), 'MMM dd, yyyy HH:mm') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-2xl">{{ channelInfo(log.channel).icon }}</span>
                                <span class="ml-2 text-sm font-medium">{{ channelInfo(log.channel).label }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ log.recipient }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 max-w-md truncate">
                                {{ log.message }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                    {{ log.provider || '—' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="statusBadge(log.success).color"
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                    {{ statusBadge(log.success).text }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ logs.from }} to {{ logs.to }} of {{ logs.total }} results
                        </div>
                        <div class="flex space-x-2">
                            <Link v-for="link in logs.links" :key="link.label" :href="link.url" v-html="link.label"
                                :class="{ 'px-3 py-2 bg-blue-600 text-white rounded': link.active, 'px-3 py-2 text-gray-700 hover:bg-gray-100 rounded': !link.active && link.url }"
                                class="disabled:opacity-50" :disabled="!link.url">
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
