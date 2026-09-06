<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { Select } from 'primevue';

type Option = { id: string; name: string; is_current?: boolean; class_level_id?: string | null };

type ReportFilters = {
    academic_session_id?: string | null;
    status?: string | string[] | null;
    class_level_id?: string | null;
    class_section_id?: string | null;
    source?: string | null;
    origin?: string | null;
    has_application?: string | null;
    finalized?: string | boolean | null;
    review_state?: string | null;
    acceptance_state?: string | null;
    date_from?: string | null;
    date_to?: string | null;
    deadline_from?: string | null;
    deadline_to?: string | null;
};

const props = defineProps<{
    applications: Record<string, any>;
    admissions: Record<string, any>;
    enrollments: Record<string, any>;
    placement: Record<string, any>;
    funnel: Record<string, number>;
    filters: ReportFilters;
    academicSessions?: Option[];
    classLevels?: Option[];
    classSections?: Option[];
}>();

const form = reactive({
    academic_session_id: props.filters.academic_session_id ?? '',
    status: Array.isArray(props.filters.status) ? (props.filters.status[0] ?? '') : (props.filters.status ?? ''),
    class_level_id: props.filters.class_level_id ?? '',
    class_section_id: props.filters.class_section_id ?? '',
    source: props.filters.source ?? '',
    origin: props.filters.origin ?? '',
    has_application: props.filters.has_application ?? '',
    finalized: props.filters.finalized === true || props.filters.finalized === '1' || props.filters.finalized === 'true'
        ? '1'
        : props.filters.finalized === false || props.filters.finalized === '0' || props.filters.finalized === 'false'
            ? '0'
            : '',
    review_state: props.filters.review_state ?? '',
    acceptance_state: props.filters.acceptance_state ?? '',
    date_from: (props.filters.date_from ?? '').toString().slice(0, 10),
    date_to: (props.filters.date_to ?? '').toString().slice(0, 10),
    deadline_from: (props.filters.deadline_from ?? '').toString().slice(0, 10),
    deadline_to: (props.filters.deadline_to ?? '').toString().slice(0, 10),
});

const sessionOptions = computed(() =>
    (props.academicSessions ?? []).map((s) => ({
        label: s.is_current ? `${s.name} (current)` : s.name,
        value: s.id,
    })),
);

const classLevelOptions = computed(() =>
    (props.classLevels ?? []).map((l) => ({ label: l.name, value: l.id })),
);

const classSectionOptions = computed(() => {
    const all = props.classSections ?? [];
    const level = form.class_level_id;
    const rows = level ? all.filter((s) => !s.class_level_id || s.class_level_id === level) : all;
    return rows.map((s) => ({ label: s.name, value: s.id }));
});

watch(() => form.class_level_id, () => {
    if (!form.class_section_id) return;
    if (!classSectionOptions.value.some((o) => o.value === form.class_section_id)) {
        form.class_section_id = '';
    }
});

const filterParams = computed(() => {
    const p: Record<string, string> = {};
    Object.entries(form).forEach(([k, v]) => {
        if (v !== null && v !== undefined && String(v).trim() !== '') p[k] = String(v);
    });
    return p;
});

function applyFilters() {
    router.get(route('lifecycle.reports'), filterParams.value, { preserveState: true, replace: true });
}

function clearFilters() {
    Object.keys(form).forEach((k) => { (form as any)[k] = ''; });
    router.get(route('lifecycle.reports'), {}, { preserveState: true, replace: true });
}

function exportUrl(section: string, format: string = 'csv') {
    return route('lifecycle.reports.export', { section, format, ...filterParams.value });
}

const funnelOrder = ['applications', 'applications_approved', 'admissions', 'admissions_accepted', 'enrollments', 'enrollments_finalized'];
const funnelLabel = (key: string) => String(key).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const sectionUtilization = computed(() => Array.isArray(props.placement?.section_utilization) ? props.placement.section_utilization : []);
const pct = (rate: number | null | undefined) =>
    rate != null && !Number.isNaN(Number(rate)) ? `${(Number(rate) * 100).toFixed(1)}%` : '—';
</script>

<template>
    <Head title="Lifecycle Reports" />
    <div class="space-y-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Lifecycle Reports</h1>
                <p class="mt-1 text-sm text-gray-500">School-scoped applications → admissions → enrollments → placement</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="exportUrl('applications')" class="rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600">Export applications</a>
                <a :href="exportUrl('admissions')" class="rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600">Export admissions</a>
                <a :href="exportUrl('enrollments')" class="rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600">Export enrollments</a>
                <a :href="exportUrl('placements')" class="rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600">Export placements</a>
                <a :href="exportUrl('funnel')" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow hover:bg-indigo-500">Export funnel</a>
            </div>
        </div>

        <form class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800" @submit.prevent="applyFilters">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Filters</h2>
                <p class="text-xs text-gray-500">Applied server-side to reports and exports</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="block text-xs font-medium text-gray-600 dark:text-gray-300">
                    Academic session
                    <Select v-model="form.academic_session_id" :options="sessionOptions" optionLabel="label" optionValue="value" placeholder="Any session" showClear class="mt-1 w-full" />
                </div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">
                    Status
                    <input v-model="form.status" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900" placeholder="e.g. submitted, offered" />
                </label>
                <div class="block text-xs font-medium text-gray-600 dark:text-gray-300">
                    Class level
                    <Select v-model="form.class_level_id" :options="classLevelOptions" optionLabel="label" optionValue="value" placeholder="Any class level" showClear class="mt-1 w-full" />
                </div>
                <div class="block text-xs font-medium text-gray-600 dark:text-gray-300">
                    Section
                    <Select v-model="form.class_section_id" :options="classSectionOptions" optionLabel="label" optionValue="value" placeholder="Any section" showClear class="mt-1 w-full" />
                </div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Source
                    <input v-model="form.source" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900" />
                </label>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Origin
                    <select v-model="form.origin" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900">
                        <option value="">Any</option>
                        <option value="application">Application</option>
                        <option value="admission">Admission</option>
                        <option value="direct">Direct</option>
                    </select>
                </label>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Has application
                    <select v-model="form.has_application" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900">
                        <option value="">Any</option>
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>
                </label>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Finalized
                    <select v-model="form.finalized" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900">
                        <option value="">Any</option>
                        <option value="1">Finalized</option>
                        <option value="0">Incomplete</option>
                    </select>
                </label>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Review state
                    <select v-model="form.review_state" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900">
                        <option value="">Any</option>
                        <option value="awaiting">Awaiting review</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </label>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Acceptance state
                    <select v-model="form.acceptance_state" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900">
                        <option value="">Any</option>
                        <option value="awaiting">Awaiting acceptance</option>
                        <option value="accepted">Accepted</option>
                        <option value="declined">Declined</option>
                        <option value="expired">Expired</option>
                    </select>
                </label>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Date from
                    <input v-model="form.date_from" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900" />
                </label>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Date to
                    <input v-model="form.date_to" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900" />
                </label>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Deadline from
                    <input v-model="form.deadline_from" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900" />
                </label>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Deadline to
                    <input v-model="form.deadline_to" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900" />
                </label>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-indigo-500">Apply filters</button>
                <button type="button" class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-gray-600" @click="clearFilters">Clear</button>
            </div>
        </form>

        <section>
            <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Lifecycle funnel</h2>
            <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
                <div v-for="key in funnelOrder.filter((k) => k in funnel)" :key="key" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ funnelLabel(key) }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ funnel[key] }}</div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Applications</h2>
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Total</dt><dd>{{ applications.total ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Approved</dt><dd>{{ applications.approved ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Approval rate</dt><dd>{{ pct(applications.approval_rate) }}</dd></div>
                    <div v-for="(n, status) in applications.by_status || {}" :key="'as-' + status" class="flex justify-between">
                        <dt class="capitalize text-gray-500">{{ String(status).replace(/_/g, ' ') }}</dt><dd>{{ n }}</dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Admissions</h2>
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Accepted</dt><dd>{{ admissions.accepted ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Declined</dt><dd>{{ admissions.declined ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Expired</dt><dd>{{ admissions.expired ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Acceptance rate</dt><dd>{{ pct(admissions.acceptance_rate) }}</dd></div>
                </dl>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Enrollments</h2>
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Total</dt><dd>{{ enrollments.total ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Finalized</dt><dd>{{ enrollments.finalized ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Incomplete</dt><dd>{{ enrollments.incomplete ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Admission origin</dt><dd>{{ enrollments.admission_origin ?? 0 }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Direct</dt><dd>{{ enrollments.direct ?? 0 }}</dd></div>
                </dl>
            </div>
        </div>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Placement capacity</h2>
                <dl class="flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-300">
                    <div>Sections with capacity: <span class="font-semibold">{{ placement.sections_with_capacity ?? 0 }}</span></div>
                    <div>Near capacity: <span class="font-semibold">{{ placement.sections_near_capacity ?? 0 }}</span></div>
                    <div>Full: <span class="font-semibold">{{ placement.sections_full ?? 0 }}</span></div>
                    <div>Current placements: <span class="font-semibold">{{ placement.current_placements ?? 0 }}</span></div>
                    <div>Unplaced active enrollments: <span class="font-semibold">{{ placement.active_enrollments_unplaced ?? 0 }}</span></div>
                </dl>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Section</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase text-gray-500">Capacity</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase text-gray-500">Placed</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase text-gray-500">Remaining</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase text-gray-500">Utilization</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="row in sectionUtilization" :key="row.section_id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ row.section }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right text-sm">{{ row.capacity }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right text-sm">{{ row.placed }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right text-sm">{{ row.remaining }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right text-sm">{{ pct(row.utilization) }}</td>
                        </tr>
                        <tr v-if="!sectionUtilization.length">
                            <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">No sections with capacity for the current filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-gray-500">Showing all {{ sectionUtilization.length }} applicable section(s) — not truncated.</p>
        </section>
    </div>
</template>
