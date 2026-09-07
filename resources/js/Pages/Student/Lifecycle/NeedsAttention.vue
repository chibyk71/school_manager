<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    items: Array<Record<string, any>>;
    total: number;
}>();

const severityClass = (s: string) => {
    switch (s) {
        case 'critical':
            return 'bg-red-100 text-red-800';
        case 'high':
            return 'bg-orange-100 text-orange-800';
        case 'medium':
            return 'bg-amber-100 text-amber-800';
        default:
            return 'bg-gray-100 text-gray-700';
    }
};

const typeLabel = (t: string) => (t || '').replace(/_/g, ' ');
</script>

<template>
    <Head title="Needs Attention" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Needs Attention</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Lifecycle items requiring staff action ({{ total }})
                </p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('lifecycle.upcoming-deadlines')" class="text-sm text-indigo-600 hover:underline">
                    Upcoming deadlines
                </Link>
                <Link :href="route('lifecycle.recently-completed')" class="text-sm text-indigo-600 hover:underline">
                    Recently completed
                </Link>
                <Link :href="route('lifecycle.reports')" class="text-sm text-indigo-600 hover:underline">
                    Reports
                </Link>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Item</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Severity</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">When</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="item in items" :key="item.type + '-' + item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                        <td class="px-4 py-3 text-sm capitalize text-gray-700 dark:text-gray-200">
                            {{ typeLabel(item.type) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ item.label }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="severityClass(item.severity)">
                                {{ item.severity }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ item.at || item.deadline || '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm">
                            <Link
                                v-if="item.route"
                                :href="route(item.route, item.route_params || {})"
                                class="text-indigo-600 hover:underline"
                            >
                                Open
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!items.length">
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                            Nothing needs attention right now.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
