<!--
  School Profile workspace (School Management Phase 2).

  Unified configuration surface inspired by PreSkool school-profile layout:
  identity summary at top, vertical section nav, right-hand panes.
  Identity/media/addresses use existing School + Address/Media paths.
  Operational preferences use OI Settings (SchoolOperationalSettings).
  Deferred sections are placeholders only — no unsupported writes.
-->
<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import FileUpload from 'primevue/fileupload';
import { ref, computed, onMounted } from 'vue';
import { route } from 'ziggy-js';
import type { Address } from '@/types/address';
import AddressManager from '@/Components/Address/AddressManager.vue';
import TextInput from '@/Components/forms/textInput.vue';
import InputLabel from '@/Components/forms/InputLabel.vue';

interface OperationalSettings {
    timezone: string;
    date_format: string;
    time_format: string;
    week_start: string;
    locale: string;
    default_currency: string;
}

interface OperationalOptions {
    date_formats: string[];
    time_formats: string[];
    week_starts: string[];
    locales: string[];
    currencies: string[];
}

interface Props {
    school: any;
    operationalSettings: OperationalSettings;
    operationalOptions: OperationalOptions;
}

const props = defineProps<Props>();
const toast = useToast();
const page = usePage();

type SectionId =
    | 'general'
    | 'contact'
    | 'addresses'
    | 'logos'
    | 'lifecycle'
    | 'preferences'
    | 'branding'
    | 'academic'
    | 'communication'
    | 'governance'
    | 'compliance'
    | 'documents'
    | 'security'
    | 'audit';

const activeSection = ref<SectionId>('general');

const navGroups = [
    {
        heading: 'Profile',
        items: [
            { id: 'general' as SectionId, label: 'General information', icon: 'pi pi-building' },
            { id: 'contact' as SectionId, label: 'Contact', icon: 'pi pi-phone' },
            { id: 'addresses' as SectionId, label: 'Addresses', icon: 'pi pi-map-marker' },
            { id: 'logos' as SectionId, label: 'Logos & media', icon: 'pi pi-image' },
            { id: 'lifecycle' as SectionId, label: 'Lifecycle & readiness', icon: 'pi pi-sync' },
        ],
    },
    {
        heading: 'Campus, languages & time',
        items: [
            { id: 'preferences' as SectionId, label: 'Operational preferences', icon: 'pi pi-globe' },
        ],
    },
    {
        heading: 'Managed elsewhere',
        items: [
            { id: 'branding' as SectionId, label: 'Branding & templates', icon: 'pi pi-palette' },
            { id: 'academic' as SectionId, label: 'Academic structure', icon: 'pi pi-calendar' },
            { id: 'communication' as SectionId, label: 'Communication', icon: 'pi pi-envelope' },
            { id: 'governance' as SectionId, label: 'Governance', icon: 'pi pi-users' },
            { id: 'compliance' as SectionId, label: 'Affiliation & compliance', icon: 'pi pi-verified' },
            { id: 'documents' as SectionId, label: 'Document management', icon: 'pi pi-folder' },
            { id: 'security' as SectionId, label: 'Security', icon: 'pi pi-shield' },
            { id: 'audit' as SectionId, label: 'Audit history', icon: 'pi pi-history' },
        ],
    },
];

const profileForm = useForm({
    name: props.school?.name ?? '',
    code: props.school?.code ?? '',
    email: props.school?.email ?? '',
    phone_one: props.school?.phone_one ?? '',
    phone_two: props.school?.phone_two ?? '',
    contact_name: props.school?.contact_name ?? '',
    description: props.school?.description ?? '',
    motto: props.school?.motto ?? '',
    type: props.school?.type ?? '',
    logo: null as File | null,
    small_logo: null as File | null,
    favicon: null as File | null,
    dark_logo: null as File | null,
    dark_small_logo: null as File | null,
});

const prefsForm = useForm({
    timezone: props.operationalSettings?.timezone ?? 'UTC',
    date_format: props.operationalSettings?.date_format ?? 'd/m/Y',
    time_format: props.operationalSettings?.time_format ?? 'H:i',
    week_start: props.operationalSettings?.week_start ?? 'monday',
    locale: props.operationalSettings?.locale ?? 'en',
    default_currency: props.operationalSettings?.default_currency ?? 'NGN',
});

const logoPreview = ref(props.school?.logo_url ?? null);
const smallLogoPreview = ref(props.school?.small_logo_url ?? null);
const faviconPreview = ref(props.school?.favicon_url ?? null);
const darkLogoPreview = ref(props.school?.dark_logo_url ?? null);
const darkSmallLogoPreview = ref(props.school?.dark_small_logo_url ?? null);

const lifecycleBusy = ref(false);
const readiness = ref<any>(null);

const currentState = computed(() => {
    const s = props.school?.state;
    if (!s) return '';
    return typeof s === 'string' ? s : (s.name || String(s));
});

const stateLabel = computed(() => props.school?.state_label || currentState.value || '—');

const readinessConditions = computed(() => {
    const c = readiness.value?.conditions ?? readiness.value?.checks ?? null;
    if (!c || typeof c !== 'object') return [] as Array<{ key: string; passed: boolean; message: string | null }>;
    return Object.entries(c).map(([key, val]: [string, any]) => ({
        key,
        passed: !!(val === true || val?.passed === true || val?.ok === true),
        message: typeof val === 'object' ? (val.message ?? val.reason ?? null) : null,
    }));
});

const lifecycleActionLabels: Record<string, string> = {
    'mark-ready': 'Mark ready',
    activate: 'Activate',
    suspend: 'Suspend',
    reactivate: 'Reactivate',
    archive: 'Archive',
    recover: 'Recover',
};

function availableLifecycleActions(): string[] {
    const state = currentState.value.toLowerCase();
    const map: Record<string, string[]> = {
        draft: ['mark-ready'],
        ready: ['activate'],
        active: ['suspend', 'archive'],
        suspended: ['reactivate', 'archive'],
        archived: ['recover'],
    };
    return map[state] ?? [];
}

function postLifecycle(action: string) {
    lifecycleBusy.value = true;
    router.post(route(`schools.${action}`, props.school.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Lifecycle updated', life: 3000 });
            fetchReadiness();
        },
        onError: () => toast.add({ severity: 'error', summary: 'Lifecycle action failed', life: 4000 }),
        onFinish: () => { lifecycleBusy.value = false; },
    });
}

function fetchReadiness() {
    if (!props.school?.id) return;
    router.get(route('schools.readiness', props.school.id), {}, {
        preserveState: true,
        preserveScroll: true,
        only: [],
        onSuccess: (page: any) => {
            // readiness endpoint may redirect or return JSON; prefer props if present
        },
    });
    fetch(route('schools.readiness', props.school.id), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    })
        .then((r) => (r.ok ? r.json() : null))
        .then((data) => {
            if (data) readiness.value = data;
        })
        .catch(() => {});
}

onMounted(() => {
    fetchReadiness();
    const flash = (page.props as any)?.flash;
    if (flash?.success) {
        toast.add({ severity: 'success', summary: flash.success, life: 3000 });
    }
});

const updatePreview = (
    event: any,
    field: 'logo' | 'small_logo' | 'favicon' | 'dark_logo' | 'dark_small_logo',
    previewRef: any,
) => {
    const file = event.files?.[0];
    if (file) {
        (profileForm as any)[field] = file;
        previewRef.value = URL.createObjectURL(file);
    }
};

const clearFile = (
    field: 'logo' | 'small_logo' | 'favicon' | 'dark_logo' | 'dark_small_logo',
    previewRef: any,
) => {
    (profileForm as any)[field] = null;
    previewRef.value = props.school?.[`${field}_url`] ?? null;
};

function submitProfile() {
    profileForm
        .transform((data) => {
            const payload: Record<string, unknown> = { ...data };
            // Media only when a new File was selected
            (['logo', 'small_logo', 'favicon', 'dark_logo', 'dark_small_logo'] as const).forEach((k) => {
                if (!(payload[k] instanceof File)) delete payload[k];
            });
            return payload;
        })
        .post(route('schools.update', props.school.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => toast.add({ severity: 'success', summary: 'School profile updated', life: 3000 }),
            onError: () => toast.add({ severity: 'error', summary: 'Please correct the highlighted fields', life: 4000 }),
        });
}

function submitPreferences() {
    prefsForm.put(route('schools.operational-settings.update', props.school.id), {
        preserveScroll: true,
        onSuccess: () => toast.add({ severity: 'success', summary: 'Preferences saved', life: 3000 }),
        onError: () => toast.add({ severity: 'error', summary: 'Invalid preference values', life: 4000 }),
    });
}

const commonTimezones = [
    'UTC',
    'Africa/Lagos',
    'Africa/Accra',
    'Africa/Nairobi',
    'Africa/Johannesburg',
    'Europe/London',
    'America/New_York',
    'America/Chicago',
    'Asia/Dubai',
];
</script>

<template>
    <AuthenticatedLayout
        :title="`School profile — ${school.name}`"
        :crumb="[
            { label: 'Dashboard', url: route('dashboard') },
            { label: 'Schools', url: route('schools.index') },
            { label: school.name },
        ]"
    >
        <Head :title="`School profile — ${school.name}`" />

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 space-y-6">
            <!-- Identity panel -->
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 sm:p-6">
                <div class="flex flex-col sm:flex-row gap-4 sm:items-center">
                    <div class="shrink-0">
                        <img
                            v-if="logoPreview"
                            :src="logoPreview"
                            alt="School logo"
                            class="h-16 w-16 rounded-lg object-contain border border-gray-200 bg-gray-50"
                        />
                        <div
                            v-else
                            class="h-16 w-16 rounded-lg bg-indigo-50 dark:bg-indigo-950 flex items-center justify-center text-indigo-600 font-semibold text-xl"
                        >
                            {{ (school.name || '?').charAt(0).toUpperCase() }}
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-gray-100 truncate">
                                {{ school.name }}
                            </h1>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300"
                            >
                                {{ stateLabel }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 flex flex-wrap gap-x-3 gap-y-1">
                            <span v-if="school.code">Code: {{ school.code }}</span>
                            <span v-if="school.email">{{ school.email }}</span>
                            <span v-if="school.phone_one">{{ school.phone_one }}</span>
                            <span v-if="school.type">Type: {{ school.type }}</span>
                        </p>
                    </div>
                    <div class="shrink-0">
                        <Link
                            :href="route('schools.index')"
                            class="inline-flex items-center px-3 py-2 text-sm border rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            Back to schools
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Workspace: left nav + pane -->
            <div class="grid grid-cols-12 gap-6">
                <aside class="col-span-12 lg:col-span-3 xl:col-span-2">
                    <nav
                        class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden lg:sticky lg:top-6"
                        aria-label="School profile sections"
                    >
                        <div v-for="group in navGroups" :key="group.heading" class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <p class="px-4 pt-3 pb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">
                                {{ group.heading }}
                            </p>
                            <ul class="pb-2">
                                <li v-for="item in group.items" :key="item.id">
                                    <button
                                        type="button"
                                        class="w-full flex items-center gap-2 px-4 py-2 text-sm text-left transition-colors"
                                        :class="
                                            activeSection === item.id
                                                ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 font-medium'
                                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
                                        "
                                        @click="activeSection = item.id"
                                    >
                                        <i :class="[item.icon, 'text-xs opacity-70']" />
                                        <span class="truncate">{{ item.label }}</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </nav>
                </aside>

                <main class="col-span-12 lg:col-span-9 xl:col-span-10 space-y-4">
                    <!-- General -->
                    <section v-show="activeSection === 'general'" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 sm:p-6">
                        <h2 class="text-lg font-semibold mb-4">General information</h2>
                        <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submitProfile">
                            <TextInput label="School name" id="name" v-model="profileForm.name" :error="profileForm.errors.name" required />
                            <TextInput label="School code" id="code" v-model="profileForm.code" :error="profileForm.errors.code" />
                            <TextInput label="Type" id="type" v-model="profileForm.type" :error="profileForm.errors.type" hint="Existing school type only; additional dimensions deferred" />
                            <TextInput label="Motto" id="motto" v-model="profileForm.motto" :error="profileForm.errors.motto" />
                            <div class="md:col-span-2">
                                <TextInput label="Description" id="description" v-model="profileForm.description" :error="profileForm.errors.description" />
                            </div>
                            <div class="md:col-span-2 flex justify-end gap-2 pt-2">
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                                    :disabled="profileForm.processing"
                                >
                                    Save general information
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Contact -->
                    <section v-show="activeSection === 'contact'" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 sm:p-6">
                        <h2 class="text-lg font-semibold mb-4">Contact</h2>
                        <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submitProfile">
                            <TextInput label="Email" id="email" v-model="profileForm.email" :error="profileForm.errors.email" />
                            <TextInput label="Primary phone" id="phone_one" v-model="profileForm.phone_one" :error="profileForm.errors.phone_one" />
                            <TextInput label="Secondary phone" id="phone_two" v-model="profileForm.phone_two" :error="profileForm.errors.phone_two" />
                            <TextInput
                                label="Primary contact name"
                                id="contact_name"
                                v-model="profileForm.contact_name"
                                :error="profileForm.errors.contact_name"
                                hint="Contact person — not the institution name"
                            />
                            <div class="md:col-span-2 flex justify-end gap-2 pt-2">
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                                    :disabled="profileForm.processing"
                                >
                                    Save contact
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Addresses -->
                    <section v-show="activeSection === 'addresses'" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 sm:p-6">
                        <h2 class="text-lg font-semibold mb-1">Addresses</h2>
                        <p class="text-sm text-gray-500 mb-4">Managed through the Address capability. Changes save independently of the profile form.</p>
                        <AddressManager
                            owner="school"
                            :owner-id="school.id"
                            :initial-addresses="school.addresses || []"
                        />
                    </section>

                    <!-- Logos -->
                    <section v-show="activeSection === 'logos'" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 sm:p-6">
                        <h2 class="text-lg font-semibold mb-4">Logos & media</h2>
                        <form class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6" @submit.prevent="submitProfile">
                            <div v-for="item in [
                                { field: 'logo' as const, label: 'Logo', preview: logoPreview },
                                { field: 'small_logo' as const, label: 'Small logo', preview: smallLogoPreview },
                                { field: 'favicon' as const, label: 'Favicon', preview: faviconPreview },
                                { field: 'dark_logo' as const, label: 'Dark logo', preview: darkLogoPreview },
                                { field: 'dark_small_logo' as const, label: 'Dark small logo', preview: darkSmallLogoPreview },
                            ]" :key="item.field" class="space-y-2">
                                <InputLabel :value="item.label" />
                                <img
                                    v-if="item.preview"
                                    :src="item.preview"
                                    :alt="item.label"
                                    class="h-20 w-auto max-w-full object-contain border rounded bg-gray-50 dark:bg-gray-800 p-1"
                                />
                                <FileUpload
                                    mode="basic"
                                    choose-label="Choose"
                                    accept="image/*"
                                    :auto="false"
                                    custom-upload
                                    @select="(e: any) => updatePreview(e, item.field, item.field === 'logo' ? logoPreview : item.field === 'small_logo' ? smallLogoPreview : item.field === 'favicon' ? faviconPreview : item.field === 'dark_logo' ? darkLogoPreview : darkSmallLogoPreview)"
                                    class="w-full"
                                />
                            </div>
                            <div class="sm:col-span-2 lg:col-span-3 flex justify-end">
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                                    :disabled="profileForm.processing"
                                >
                                    Save media
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Lifecycle -->
                    <section v-show="activeSection === 'lifecycle'" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 sm:p-6 space-y-4">
                        <h2 class="text-lg font-semibold">Lifecycle & readiness</h2>
                        <p class="text-sm text-gray-500">
                            Current state: <span class="font-medium text-gray-800 dark:text-gray-200">{{ stateLabel }}</span>
                        </p>
                        <ul v-if="readinessConditions.length" class="space-y-1 text-sm">
                            <li
                                v-for="item in readinessConditions"
                                :key="item.key"
                                class="flex gap-2"
                                :class="item.passed ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                            >
                                <span class="font-mono text-xs uppercase shrink-0 w-40">{{ item.key }}</span>
                                <span>{{ item.passed ? 'Passed' : (item.message || 'Not met') }}</span>
                            </li>
                        </ul>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="action in availableLifecycleActions()"
                                :key="action"
                                type="button"
                                class="px-3 py-1.5 text-sm border rounded-lg disabled:opacity-50 hover:bg-gray-50 dark:hover:bg-gray-800"
                                :disabled="lifecycleBusy"
                                @click="postLifecycle(action)"
                            >
                                {{ lifecycleActionLabels[action] || action }}
                            </button>
                            <p v-if="availableLifecycleActions().length === 0" class="text-xs text-gray-500">
                                No lifecycle actions available for this state.
                            </p>
                        </div>
                    </section>

                    <!-- Operational preferences -->
                    <section v-show="activeSection === 'preferences'" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 sm:p-6">
                        <h2 class="text-lg font-semibold mb-1">Operational preferences</h2>
                        <p class="text-sm text-gray-500 mb-4">
                            School-scoped settings stored via the Settings infrastructure. These do not change the server timezone or rewrite historical currencies.
                        </p>
                        <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submitPreferences">
                            <div>
                                <InputLabel for="timezone" value="Timezone" />
                                <select
                                    id="timezone"
                                    v-model="prefsForm.timezone"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 shadow-sm text-sm"
                                >
                                    <option v-for="tz in commonTimezones" :key="tz" :value="tz">{{ tz }}</option>
                                    <option v-if="prefsForm.timezone && !commonTimezones.includes(prefsForm.timezone)" :value="prefsForm.timezone">
                                        {{ prefsForm.timezone }}
                                    </option>
                                </select>
                                <p v-if="prefsForm.errors.timezone" class="mt-1 text-xs text-red-600">{{ prefsForm.errors.timezone }}</p>
                            </div>
                            <div>
                                <InputLabel for="date_format" value="Date format" />
                                <select
                                    id="date_format"
                                    v-model="prefsForm.date_format"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 shadow-sm text-sm"
                                >
                                    <option v-for="f in operationalOptions.date_formats" :key="f" :value="f">{{ f }}</option>
                                </select>
                                <p v-if="prefsForm.errors.date_format" class="mt-1 text-xs text-red-600">{{ prefsForm.errors.date_format }}</p>
                            </div>
                            <div>
                                <InputLabel for="time_format" value="Time format" />
                                <select
                                    id="time_format"
                                    v-model="prefsForm.time_format"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 shadow-sm text-sm"
                                >
                                    <option v-for="f in operationalOptions.time_formats" :key="f" :value="f">{{ f }}</option>
                                </select>
                                <p v-if="prefsForm.errors.time_format" class="mt-1 text-xs text-red-600">{{ prefsForm.errors.time_format }}</p>
                            </div>
                            <div>
                                <InputLabel for="week_start" value="Week starts on" />
                                <select
                                    id="week_start"
                                    v-model="prefsForm.week_start"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 shadow-sm text-sm"
                                >
                                    <option v-for="d in operationalOptions.week_starts" :key="d" :value="d">{{ d }}</option>
                                </select>
                                <p v-if="prefsForm.errors.week_start" class="mt-1 text-xs text-red-600">{{ prefsForm.errors.week_start }}</p>
                            </div>
                            <div>
                                <InputLabel for="locale" value="Locale / language" />
                                <select
                                    id="locale"
                                    v-model="prefsForm.locale"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 shadow-sm text-sm"
                                >
                                    <option v-for="l in operationalOptions.locales" :key="l" :value="l">{{ l }}</option>
                                </select>
                                <p v-if="prefsForm.errors.locale" class="mt-1 text-xs text-red-600">{{ prefsForm.errors.locale }}</p>
                            </div>
                            <div>
                                <InputLabel for="default_currency" value="Default currency" />
                                <select
                                    id="default_currency"
                                    v-model="prefsForm.default_currency"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 shadow-sm text-sm"
                                >
                                    <option v-for="c in operationalOptions.currencies" :key="c" :value="c">{{ c }}</option>
                                </select>
                                <p v-if="prefsForm.errors.default_currency" class="mt-1 text-xs text-red-600">{{ prefsForm.errors.default_currency }}</p>
                            </div>
                            <div class="md:col-span-2 flex justify-end pt-2">
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                                    :disabled="prefsForm.processing"
                                >
                                    Save preferences
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Deferred panes -->
                    <section
                        v-for="deferred in [
                            { id: 'branding', title: 'Branding & templates', body: 'Colours, letterheads and certificate templates remain owned by future branding work. Logos are managed under Logos & media.' },
                            { id: 'academic', title: 'Academic structure', body: 'Academic year, sessions and terms are owned by the Academic module. This profile does not store an independent academic-year field.' },
                            { id: 'communication', title: 'Communication', body: 'Email, SMS and notification configuration live under Settings → Communication.' },
                            { id: 'governance', title: 'Governance', body: 'Board and management details beyond the school contact person are not modelled in this phase.' },
                            { id: 'compliance', title: 'Affiliation & compliance', body: 'Licensing and compliance tracking are deferred.' },
                            { id: 'documents', title: 'Document management', body: 'Use File Manager for school documents. Versioning and compliance reminders are out of scope here.' },
                            { id: 'security', title: 'Security', body: 'Authentication and related security settings remain under their own settings modules.' },
                            { id: 'audit', title: 'Audit history', body: 'Full audit views are deferred. Activity logging continues via existing infrastructure.' },
                        ]"
                        v-show="activeSection === deferred.id"
                        :key="deferred.id"
                        class="bg-white dark:bg-gray-900 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 shadow-sm p-5 sm:p-6"
                    >
                        <div class="flex items-start gap-3">
                            <span class="inline-flex items-center rounded-full bg-amber-50 dark:bg-amber-950 text-amber-800 dark:text-amber-200 px-2.5 py-0.5 text-xs font-medium">
                                Managed elsewhere / coming later
                            </span>
                        </div>
                        <h2 class="text-lg font-semibold mt-3">{{ deferred.title }}</h2>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 max-w-2xl">{{ deferred.body }}</p>
                    </section>
                </main>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
