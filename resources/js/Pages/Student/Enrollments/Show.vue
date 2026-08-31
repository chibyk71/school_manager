<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    enrollment: Record<string, any>;
    readiness: {
        ready: boolean;
        blockers: string[];
        details: Record<string, any>;
    };
}>();

const biodataForm = useForm({
    biodata: {
        first_name: props.enrollment.meta?.biodata?.first_name ?? '',
        last_name: props.enrollment.meta?.biodata?.last_name ?? '',
        email: props.enrollment.meta?.biodata?.email ?? '',
        phone: props.enrollment.meta?.biodata?.phone ?? '',
        date_of_birth: props.enrollment.meta?.biodata?.date_of_birth ?? '',
        gender: props.enrollment.meta?.biodata?.gender ?? '',
    },
});

const isIncomplete = computed(() =>
    ['draft', 'in_progress'].includes(props.enrollment.status)
);

function saveBiodata() {
    biodataForm.patch(route('enrollments.biodata', props.enrollment.id), { preserveScroll: true });
}

function satisfy(instanceId: string) {
    router.post(route('enrollments.requirements.satisfy', [props.enrollment.id, instanceId]), {}, { preserveScroll: true });
}

function waive(instanceId: string) {
    const reason = window.prompt('Waiver reason?');
    if (!reason) return;
    router.post(
        route('enrollments.requirements.waive', [props.enrollment.id, instanceId]),
        { waiver_reason: reason },
        { preserveScroll: true }
    );
}

function finalize() {
    if (!confirm('Finalize this enrollment? This will create the Student record.')) return;
    router.post(route('enrollments.finalize', props.enrollment.id));
}
</script>

<template>
    <Head :title="`Enrollment ${enrollment.id}`" />

    <div class="space-y-6 p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <Link :href="route('enrollments.index')" class="text-sm text-indigo-600 hover:underline">← Enrollments</Link>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">Enrollment</h1>
                <p class="mt-1 text-sm text-gray-500 capitalize">Status: {{ enrollment.status?.replace(/_/g, ' ') }}</p>
            </div>
            <button
                v-if="isIncomplete"
                type="button"
                class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-green-500 disabled:opacity-50"
                :disabled="!readiness.ready"
                @click="finalize"
            >
                Finalize enrollment
            </button>
        </div>

        <div v-if="!readiness.ready" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-medium">Not ready to finalize</p>
            <ul class="mt-2 list-disc pl-5">
                <li v-for="(b, i) in readiness.blockers" :key="i">{{ b }}</li>
            </ul>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Biodata (workflow)</h2>
                <p class="mt-1 text-xs text-gray-500">Permanent identity is written to Profile on finalize.</p>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <label class="text-xs">First name
                        <input v-model="biodataForm.biodata.first_name" class="mt-1 w-full rounded border px-2 py-1 text-sm" :disabled="!isIncomplete" />
                    </label>
                    <label class="text-xs">Last name
                        <input v-model="biodataForm.biodata.last_name" class="mt-1 w-full rounded border px-2 py-1 text-sm" :disabled="!isIncomplete" />
                    </label>
                    <label class="text-xs">Email
                        <input v-model="biodataForm.biodata.email" type="email" class="mt-1 w-full rounded border px-2 py-1 text-sm" :disabled="!isIncomplete" />
                    </label>
                    <label class="text-xs">Phone
                        <input v-model="biodataForm.biodata.phone" class="mt-1 w-full rounded border px-2 py-1 text-sm" :disabled="!isIncomplete" />
                    </label>
                    <label class="text-xs">Date of birth
                        <input v-model="biodataForm.biodata.date_of_birth" type="date" class="mt-1 w-full rounded border px-2 py-1 text-sm" :disabled="!isIncomplete" />
                    </label>
                    <label class="text-xs">Gender
                        <input v-model="biodataForm.biodata.gender" class="mt-1 w-full rounded border px-2 py-1 text-sm" :disabled="!isIncomplete" />
                    </label>
                </div>
                <button
                    v-if="isIncomplete"
                    type="button"
                    class="mt-4 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500"
                    :disabled="biodataForm.processing"
                    @click="saveBiodata"
                >
                    Save biodata
                </button>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Summary</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Session</dt>
                        <dd>{{ enrollment.academic_session?.name ?? enrollment.academic_session_id }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Student</dt>
                        <dd>
                            <span v-if="enrollment.student_id">Linked ({{ enrollment.student_id }})</span>
                            <span v-else class="text-amber-600">Not yet created</span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Admission</dt>
                        <dd>{{ enrollment.admission_id ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Activated</dt>
                        <dd>{{ enrollment.activated_at ?? '—' }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Requirements</h2>
            <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                <li
                    v-for="inst in enrollment.requirement_instances || []"
                    :key="inst.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"
                >
                    <div>
                        <p class="font-medium">{{ inst.definition?.name ?? inst.definition_id }}</p>
                        <p class="text-xs text-gray-500">
                            {{ inst.definition?.type }} ·
                            <span class="capitalize">{{ inst.status }}</span>
                            <span v-if="inst.definition?.is_required" class="text-red-600"> · required</span>
                        </p>
                    </div>
                    <div v-if="isIncomplete && inst.status === 'pending'" class="flex gap-2">
                        <button type="button" class="rounded border px-2 py-1 text-xs hover:bg-gray-50" @click="satisfy(inst.id)">
                            Satisfy
                        </button>
                        <button type="button" class="rounded border px-2 py-1 text-xs hover:bg-gray-50" @click="waive(inst.id)">
                            Waive
                        </button>
                    </div>
                </li>
                <li v-if="!(enrollment.requirement_instances || []).length" class="py-4 text-sm text-gray-500">
                    No requirement instances (school has no active definitions, or not yet materialized).
                </li>
            </ul>
        </section>
    </div>
</template>
