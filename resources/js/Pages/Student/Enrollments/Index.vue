<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    enrollments: {
        data: Array<Record<string, any>>;
        links: any[];
        meta?: any;
    };
    filters: { status?: string };
    statuses: string[];
}>();

const statusLabel = (s: string) => s.replace(/_/g, ' ');

function filterStatus(status: string | null) {
    router.get(route('enrollments.index'), status ? { status } : {}, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Enrollments" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Enrollments</h1>
                <p class="mt-1 text-sm text-gray-500">Phase 4 — enrollment workflow (Student created on finalize)</p>
            </div>
            <Link
                :href="route('enrollments.index')"
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-indigo-500"
            >
                Refresh
            </Link>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-full px-3 py-1 text-xs font-medium"
                :class="!filters.status ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700'"
                @click="filterStatus(null)"
            >
                All
            </button>
            <button
                v-for="s in statuses"
                :key="s"
                type="button"
                class="rounded-full px-3 py-1 text-xs font-medium capitalize"
                :class="filters.status === s ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700'"
                @click="filterStatus(s)"
            >
                {{ statusLabel(s) }}
            </button>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Session</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Student</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Started</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="row in enrollments.data" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="whitespace-nowrap px-4 py-3 text-sm capitalize text-gray-900 dark:text-gray-100">
                            {{ statusLabel(row.status) }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                            {{ row.academic_session?.name ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                            <template v-if="row.student">
                                {{ row.student.first_name }} {{ row.student.last_name }}
                            </template>
                            <span v-else class="italic text-gray-400">Pending identity</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                            {{ row.started_at ? new Date(row.started_at).toLocaleDateString() : '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <Link
                                :href="route('enrollments.show', row.id)"
                                class="font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                Open
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!enrollments.data?.length">
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No enrollments found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
