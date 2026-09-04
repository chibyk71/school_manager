<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps<{
    items: Array<Record<string, any>>;
    total: number;
    within_days: number;
}>();

function changeWindow(days: number) {
    router.get(route('lifecycle.upcoming-deadlines'), { within_days: days }, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Upcoming Deadlines" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Upcoming Deadlines</h1>
                <p class="mt-1 text-sm text-gray-500">{{ total }} deadline(s) within {{ within_days }} days</p>
            </div>
            <div class="flex gap-2">
                <button
                    v-for="d in [7, 14, 30]"
                    :key="d"
                    type="button"
                    class="rounded-full px-3 py-1 text-xs font-medium"
                    :class="within_days === d ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700'"
                    @click="changeWindow(d)"
                >
                    {{ d }}d
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Item</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Deadline</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="item in items" :key="item.type + '-' + item.id">
                        <td class="px-4 py-3 text-sm capitalize text-gray-700">{{ (item.type || '').replace(/_/g, ' ') }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ item.label }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ item.deadline }}</td>
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
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">No upcoming deadlines in this window.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
