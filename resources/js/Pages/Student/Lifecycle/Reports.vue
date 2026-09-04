<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    applications: Record<string, any>;
    admissions: Record<string, any>;
    enrollments: Record<string, any>;
    funnel: Record<string, number>;
    filters: { academic_session_id?: string | null };
}>();
</script>

<template>
    <Head title="Lifecycle Reports" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Lifecycle Reports</h1>
                <p class="mt-1 text-sm text-gray-500">School-scoped application → admission → enrollment funnel</p>
            </div>
            <Link
                :href="route('lifecycle.reports.export', { section: 'funnel' })"
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-indigo-500"
            >
                Export CSV
            </Link>
        </div>

        <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-6">
            <div v-for="(value, key) in funnel" :key="key" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ String(key).replace(/_/g, ' ') }}</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ value }}</div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Applications</h2>
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Total</dt><dd>{{ applications.total }}</dd></div>
                    <div v-for="(n, status) in applications.by_status" :key="status" class="flex justify-between">
                        <dt class="capitalize text-gray-500">{{ status }}</dt><dd>{{ n }}</dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Admissions</h2>
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Accepted</dt><dd>{{ admissions.accepted }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Declined</dt><dd>{{ admissions.declined }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Expired</dt><dd>{{ admissions.expired }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Acceptance rate</dt>
                        <dd>{{ admissions.acceptance_rate != null ? (admissions.acceptance_rate * 100).toFixed(1) + '%' : '—' }}</dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Enrollments</h2>
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Finalized</dt><dd>{{ enrollments.finalized }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Incomplete</dt><dd>{{ enrollments.incomplete }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Admission origin</dt><dd>{{ enrollments.admission_origin }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Direct</dt><dd>{{ enrollments.direct }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</template>
