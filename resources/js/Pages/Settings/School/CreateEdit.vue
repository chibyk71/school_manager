<!-- resources/js/Pages/Settings/Schools/CreateEdit.vue -->
<!--
CreateEdit.vue – School create/edit with Phase 4 Address integration.

- Create: AddressManager embedded mode (v-model addresses[]) submitted with the school form.
- Edit: AddressManager managed mode (owner=school) — independent Address API mutations.
- First address is not auto-primary; is_primary comes from form data when set.
-->

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import Dropdown from 'primevue/dropdown';
import FileUpload from 'primevue/fileupload';
import ProgressSpinner from 'primevue/progressspinner';
import { ref, computed, watch } from 'vue';
import { route } from 'ziggy-js';
import type { Address, AddressFormData, AddressInput } from '@/types/address';
import { emptyAddressFormData } from '@/types/address';
import AddressManager from '@/Components/Address/AddressManager.vue';
import TextInput from '@/Components/forms/textInput.vue';
import { Select } from 'primevue';
import InputLabel from '@/Components/forms/InputLabel.vue';

interface Props {
    school?: any; // null on create; on edit includes addresses collection for AddressManager
    countries: Array<{ id: number; name: string }>;
    timezones: string[];
}

const props = defineProps<Props>();

const toast = useToast();
const page = usePage();

const isEdit = computed(() => !!props.school);
const pageTitle = computed(() => isEdit.value ? 'Edit School' : 'Create New School');

// Embedded addresses for create only (managed mode on edit uses AddressManager owner context)
const embeddedAddresses = ref<AddressInput[]>([emptyAddressFormData()]);

// Inertia form – matches Store/UpdateSchoolRequest expectations
const form = useForm({
    name: props.school?.name ?? '',
    code: props.school?.code ?? '',
    email: props.school?.email ?? '',
    phone_one: props.school?.phone_one ?? '',
    phone_two: props.school?.phone_two ?? '',
    type: props.school?.type ?? 'private',
    is_active: props.school?.is_active ?? true,

    // Nested addresses[] on create (embedded AddressManager). Edit uses managed API.
    addresses: [] as AddressInput[],

    // Media files (File objects or null)
    logo: null,
    small_logo: null,
    favicon: null,
    dark_logo: null,
    dark_small_logo: null,
});

const submit = () => {
    const url = isEdit.value
        ? route('schools.update', props.school.id)
        : route('schools.store');

    form.transform((data) => ({
        ...data,
        // Create: persist embedded addresses with the school. Edit: addresses managed independently.
        addresses: isEdit.value
            ? undefined
            : (embeddedAddresses.value || []).filter((a) => a.address_line_1),
        // Inertia needs _method for PUT/PATCH on edit
        ...(isEdit.value ? { _method: 'put' } : {}),
    })).submit(isEdit.value ? 'put' : 'post', url, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: `School ${isEdit.value ? 'updated' : 'created'} successfully.`,
                life: 5000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Validation Failed',
                detail: 'Please check the form fields.',
                life: 6000,
            });
        },
    });
};

// File preview URLs (reactive for live preview on upload)
const logoPreview = ref(props.school?.logo_url ?? null);
const smallLogoPreview = ref(props.school?.small_logo_url ?? null);
const faviconPreview = ref(props.school?.favicon_url ?? null);
const darkLogoPreview = ref(props.school?.dark_logo_url ?? null);
const darkSmallLogoPreview = ref(props.school?.dark_small_logo_url ?? null);

const updatePreview = (event: any, field: 'logo' | 'small_logo' | 'favicon' | 'dark_logo' | 'dark_small_logo', previewRef: any) => {
    const file = event.files?.[0] || null;
    form[field] = file;

    if (file) {
        previewRef.value = URL.createObjectURL(file);
    } else {
        previewRef.value = props.school?.[`${field}_url`] ?? null;
    }
};

const clearFile = (field: 'logo' | 'small_logo' | 'favicon' | 'dark_logo' | 'dark_small_logo', previewRef: any) => {
    form[field] = null;
    previewRef.value = props.school?.[`${field}_url`] ?? null;
};

const schoolTypeOptions = [
    { label: 'Private', value: 'private' },
    { label: 'Government', value: 'government' },
    { label: 'Community', value: 'community' }
];
</script>

<template>
    <AuthenticatedLayout :title="pageTitle"
        :crumb="[{ label: 'Settings', icon: 'pi pi-cog' }, { label: 'School', icon: 'pi pi-building' }, { label: pageTitle }]">
        <SettingsLayout hasSidebar>
            <template #main>
                <div class="max-w-5xl mx-auto">
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-8">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-8">
                            {{ pageTitle }}
                        </h1>

                        <form @submit.prevent="submit" class="space-y-12">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-6">
                                    School Information
                                </h2>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <TextInput label="School Name" id="name" v-model="form.name" autofocus
                                        :error="form.errors.name" required />

                                    <TextInput label="School Code" id="code" v-model="form.code"
                                        :error="form.errors.code" required />

                                    <TextInput label="School Email" id="email" type="email" v-model="form.email"
                                        :error="form.errors.email" required />

                                    <div>
                                        <InputLabel for="type" value="School Type" required />
                                        <Select id="type" v-model="form.type" :options="schoolTypeOptions"
                                            optionLabel="label" optionValue="value" placeholder="Select school type"
                                            class="w-full" :class="{ 'p-invalid': form.errors.type }" filter>
                                            <template #option="slotProps">
                                                {{ slotProps.option.label }}
                                            </template>
                                        </Select>
                                        <span v-if="form.errors.type" class="text-red-600 text-sm mt-1 block">{{
                                            form.errors.type }}</span>
                                    </div>

                                    <TextInput label="Primary Phone" id="phone_one" :error="form.errors.phone_one"
                                        v-model="form.phone_one" />

                                    <TextInput label="Secondary Phone" id="phone_two" :error="form.errors.phone_two"
                                        v-model="form.phone_two" />

                                    <div class="col-span-1 md:col-span-2 flex items-center">
                                        <div class="flex items-center">
                                            <input type="checkbox" id="is_active" v-model="form.is_active"
                                                class="w-4 h-4 text-primary-600 bg-gray-100 border-gray-300 rounded focus:ring-primary-500 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" />
                                            <InputLabel for="is_active" value="Active School"
                                                class="ml-2 mb-0 !text-base !font-normal" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Addresses: embedded on create, managed on edit (Phase 4) -->
                            <div class="mt-8">
                                <h3 class="text-lg font-semibold mb-3">Addresses</h3>
                                <AddressManager
                                    v-if="!isEdit"
                                    v-model="embeddedAddresses"
                                />
                                <AddressManager
                                    v-else
                                    owner="school"
                                    :owner-id="school.id"
                                    :initial-addresses="school.addresses || school.address || []"
                                    :can-view="true"
                                    :can-mutate="true"
                                />
                            </div>

                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-6">
                                    Branding & Logos
                                </h2>
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-8">
                                    <div class="space-y-3">
                                        <InputLabel value="Main Logo (450x450px max)" class="mb-2" />
                                        <div
                                            class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center hover:border-primary-400 transition-colors">
                                            <img v-if="logoPreview" :src="logoPreview"
                                                class="mx-auto h-24 w-24 object-contain rounded-lg bg-gray-50"
                                                alt="Logo preview" />
                                            <FileUpload mode="basic" accept="image/*" :maxFileSize="5000000"
                                                chooseLabel="Upload"
                                                @select="(e) => updatePreview(e, 'logo', logoPreview)" class="w-full" />
                                            <p v-if="!logoPreview" class="text-xs text-gray-500 mt-1">JPEG, PNG, SVG
                                                (Max 5MB)</p>
                                        </div>
                                        <button v-if="logoPreview" type="button" @click="clearFile('logo', logoPreview)"
                                            class="text-red-600 hover:text-red-700 text-sm font-medium">Remove</button>
                                    </div>

                                    <div class="space-y-3">
                                        <InputLabel value="Small Logo (450x450px max)" class="mb-2" />
                                        <div
                                            class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center hover:border-primary-400 transition-colors">
                                            <img v-if="smallLogoPreview" :src="smallLogoPreview"
                                                class="mx-auto h-24 w-24 object-contain rounded-lg bg-gray-50"
                                                alt="Small logo preview" />
                                            <FileUpload mode="basic" accept="image/*" :maxFileSize="5000000"
                                                chooseLabel="Upload"
                                                @select="(e) => updatePreview(e, 'small_logo', smallLogoPreview)"
                                                class="w-full" />
                                            <p v-if="!smallLogoPreview" class="text-xs text-gray-500 mt-1">JPEG, PNG,
                                                SVG (Max 5MB)</p>
                                        </div>
                                        <button v-if="smallLogoPreview" type="button"
                                            @click="clearFile('small_logo', smallLogoPreview)"
                                            class="text-red-600 hover:text-red-700 text-sm font-medium">Remove</button>
                                    </div>

                                    <div class="space-y-3">
                                        <InputLabel value="Favicon (32x32px)" class="mb-2" />
                                        <div
                                            class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center hover:border-primary-400 transition-colors">
                                            <img v-if="faviconPreview" :src="faviconPreview"
                                                class="mx-auto h-16 w-16 object-contain rounded-lg bg-gray-50"
                                                alt="Favicon preview" />
                                            <FileUpload mode="basic"
                                                accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml"
                                                :maxFileSize="2000000" chooseLabel="Upload"
                                                @select="(e) => updatePreview(e, 'favicon', faviconPreview)"
                                                class="w-full" />
                                            <p v-if="!faviconPreview" class="text-xs text-gray-500 mt-1">PNG, ICO, SVG
                                                (Max 2MB)</p>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <InputLabel value="Dark Mode Logo (450x450px max)" class="mb-2" />
                                        <div
                                            class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center hover:border-primary-400 transition-colors">
                                            <img v-if="darkLogoPreview" :src="darkLogoPreview"
                                                class="mx-auto h-24 w-24 object-contain rounded-lg bg-gray-50"
                                                alt="Dark logo preview" />
                                            <FileUpload mode="basic" accept="image/*" :maxFileSize="5000000"
                                                chooseLabel="Upload"
                                                @select="(e) => updatePreview(e, 'dark_logo', darkLogoPreview)"
                                                class="w-full" />
                                            <p v-if="!darkLogoPreview" class="text-xs text-gray-500 mt-1">JPEG, PNG, SVG
                                                (Max 5MB)</p>
                                        </div>
                                        <button v-if="darkLogoPreview" type="button"
                                            @click="clearFile('dark_logo', darkLogoPreview)"
                                            class="text-red-600 hover:text-red-700 text-sm font-medium">Remove</button>
                                    </div>

                                    <div class="space-y-3">
                                        <InputLabel value="Dark Small Logo (450x450px max)" class="mb-2" />
                                        <div
                                            class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center hover:border-primary-400 transition-colors">
                                            <img v-if="darkSmallLogoPreview" :src="darkSmallLogoPreview"
                                                class="mx-auto h-24 w-24 object-contain rounded-lg bg-gray-50"
                                                alt="Dark small logo preview" />
                                            <FileUpload mode="basic" accept="image/*" :maxFileSize="5000000"
                                                chooseLabel="Upload"
                                                @select="(e) => updatePreview(e, 'dark_small_logo', darkSmallLogoPreview)"
                                                class="w-full" />
                                            <p v-if="!darkSmallLogoPreview" class="text-xs text-gray-500 mt-1">JPEG, PNG, SVG
                                                (Max 5MB)</p>
                                        </div>
                                        <button v-if="darkSmallLogoPreview" type="button"
                                            @click="clearFile('dark_small_logo', darkSmallLogoPreview)"
                                            class="text-red-600 hover:text-red-700 text-sm font-medium">Remove</button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <Link :href="route('schools.index')"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900">
                                    Cancel
                                </Link>
                                <button type="submit" :disabled="form.processing"
                                    class="px-6 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700 disabled:opacity-50">
                                    {{ form.processing ? 'Saving…' : (isEdit ? 'Update School' : 'Create School') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
