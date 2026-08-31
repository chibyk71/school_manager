<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    enrollment: Record<string, any>;
    readiness: {
        ready: boolean;
        blockers: string[];
        details: Record<string, any>;
    };
}>();

const metaBiodata = props.enrollment.meta?.biodata ?? {};

const biodataForm = useForm({
    biodata: {
        first_name: metaBiodata.first_name ?? '',
        middle_name: metaBiodata.middle_name ?? '',
        last_name: metaBiodata.last_name ?? '',
        email: metaBiodata.email ?? '',
        phone: metaBiodata.phone ?? '',
        date_of_birth: metaBiodata.date_of_birth ?? '',
        gender: metaBiodata.gender ?? '',
        title: metaBiodata.title ?? '',
        nationality: metaBiodata.nationality ?? '',
        address_line_1: metaBiodata.address_line_1 ?? '',
        address_line_2: metaBiodata.address_line_2 ?? '',
        city: metaBiodata.city ?? '',
        state: metaBiodata.state ?? '',
        postal_code: metaBiodata.postal_code ?? '',
        country: metaBiodata.country ?? '',
        profile_id: props.enrollment.meta?.profile_id ?? metaBiodata.profile_id ?? '',
        confirm_identity_update: false,
    },
});

const isIncomplete = computed(() =>
    ['draft', 'in_progress'].includes(props.enrollment.status)
);

const linkedProfileId = computed(
    () => props.enrollment.meta?.profile_id ?? biodataForm.biodata.profile_id ?? null
);

const profileQuery = ref('');
const profileResults = ref<Array<Record<string, any>>>([]);
const profileSearchError = ref('');
const searching = ref(false);

async function searchProfiles() {
    profileSearchError.value = '';
    profileResults.value = [];
    const q = profileQuery.value.trim();
    if (q.length < 2) {
        profileSearchError.value = 'Enter at least 2 characters (prefer exact email).';
        return;
    }
    searching.value = true;
    try {
        const url = route('enrollments.profiles.search') + '?q=' + encodeURIComponent(q);
        const res = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('Search failed');
        const json = await res.json();
        profileResults.value = json.data ?? [];
        if (!profileResults.value.length) {
            profileSearchError.value = 'No profiles found. Link requires an existing Profile ID.';
        }
    } catch (e) {
        profileSearchError.value = 'Unable to search profiles.';
    } finally {
        searching.value = false;
    }
}

function selectProfile(p: Record<string, any>) {
    biodataForm.biodata.profile_id = p.id;
    profileResults.value = [];
    profileQuery.value = '';
}

function clearProfileLink() {
    biodataForm.biodata.profile_id = '';
}

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
    if (!confirm('Finalize this enrollment? This will create/link the Student record.')) return;
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
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Identity link (Profile)</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Link an existing Profile when the candidate has no email, or to force a specific person.
                    Search is assistive only — identity is never resolved by name alone.
                </p>

                <div v-if="linkedProfileId" class="mt-3 rounded border border-green-200 bg-green-50 p-2 text-sm">
                    Linked Profile ID:
                    <code class="font-mono text-xs">{{ linkedProfileId }}</code>
                    <button
                        v-if="isIncomplete"
                        type="button"
                        class="ml-2 text-xs text-red-600 underline"
                        @click="clearProfileLink"
                    >
                        Clear link
                    </button>
                </div>

                <div v-if="isIncomplete" class="mt-3 space-y-2">
                    <div class="flex gap-2">
                        <input
                            v-model="profileQuery"
                            type="text"
                            placeholder="Search by exact email or name prefix"
                            class="flex-1 rounded border px-2 py-1 text-sm"
                            @keyup.enter="searchProfiles"
                        />
                        <button
                            type="button"
                            class="rounded border px-3 py-1 text-sm hover:bg-gray-50"
                            :disabled="searching"
                            @click="searchProfiles"
                        >
                            {{ searching ? '…' : 'Search' }}
                        </button>
                    </div>
                    <p v-if="profileSearchError" class="text-xs text-red-600">{{ profileSearchError }}</p>
                    <ul v-if="profileResults.length" class="divide-y rounded border text-sm">
                        <li
                            v-for="p in profileResults"
                            :key="p.id"
                            class="flex cursor-pointer items-center justify-between gap-2 px-2 py-2 hover:bg-indigo-50"
                            @click="selectProfile(p)"
                        >
                            <span>
                                {{ p.last_name }}, {{ p.first_name }}
                                <span class="text-xs text-gray-500">{{ p.email || 'no email' }}</span>
                            </span>
                            <span class="font-mono text-xs text-gray-400">{{ p.id.slice(0, 8) }}…</span>
                        </li>
                    </ul>
                    <label class="block text-xs text-gray-500">
                        Or paste Profile UUID
                        <input
                            v-model="biodataForm.biodata.profile_id"
                            type="text"
                            class="mt-1 w-full rounded border px-2 py-1 font-mono text-xs"
                            placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                        />
                    </label>
                </div>
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
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Biodata (workflow → Profile on finalize)</h2>
            <p class="mt-1 text-xs text-gray-500">
                Permanent identity is written to Profile (and address via HasAddress) on finalize.
                Established identity-critical fields (email, date of birth) are not silently overwritten.
            </p>
            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3">
                <label class="text-xs">Title
                    <input v-model="biodataForm.biodata.title" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs">First name
                    <input v-model="biodataForm.biodata.first_name" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs">Middle name
                    <input v-model="biodataForm.biodata.middle_name" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs">Last name
                    <input v-model="biodataForm.biodata.last_name" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs">Email
                    <input v-model="biodataForm.biodata.email" type="email" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs">Phone
                    <input v-model="biodataForm.biodata.phone" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs">Date of birth
                    <input v-model="biodataForm.biodata.date_of_birth" type="date" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs">Gender
                    <input v-model="biodataForm.biodata.gender" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs">Nationality
                    <input v-model="biodataForm.biodata.nationality" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs col-span-2">Address line 1
                    <input v-model="biodataForm.biodata.address_line_1" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs col-span-2">Address line 2
                    <input v-model="biodataForm.biodata.address_line_2" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs">City
                    <input v-model="biodataForm.biodata.city" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs">State
                    <input v-model="biodataForm.biodata.state" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs">Postal code
                    <input v-model="biodataForm.biodata.postal_code" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
                <label class="text-xs">Country
                    <input v-model="biodataForm.biodata.country" class="mt-1 w-full rounded border px-2 py-1" :disabled="!isIncomplete" />
                </label>
            </div>
            <label v-if="isIncomplete" class="mt-3 flex items-center gap-2 text-xs text-amber-800">
                <input v-model="biodataForm.biodata.confirm_identity_update" type="checkbox" class="rounded" />
                Confirm overwrite of established Profile identity fields (email / date of birth) if they conflict
            </label>
            <button
                v-if="isIncomplete"
                type="button"
                class="mt-4 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500"
                :disabled="biodataForm.processing"
                @click="saveBiodata"
            >
                Save biodata / identity link
            </button>
        </section>

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
