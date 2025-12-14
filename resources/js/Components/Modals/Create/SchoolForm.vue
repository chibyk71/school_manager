<template>
    <form @submit.prevent="submitForm" class="space-y-6">
        <!-- School Name -->
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">School Name</label>
            <InputText id="name" v-model="localSchool.name" class="mt-1 block w-full" required aria-required="true" />
            <small v-if="errors.name" class="text-red-500">{{ errors.name[0] }}</small>
        </div>

        <!-- School Code -->
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">School Code</label>
            <InputText id="code" v-model="localSchool.code" class="mt-1 block w-full" required aria-required="true" />
            <small v-if="errors.code" class="text-red-500">{{ errors.code[0] }}</small>
        </div>

        <!-- Address Composite -->
        <div class="border p-4 rounded-md">
            <h3 class="text-lg font-medium mb-4">Address</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Street
                        Address</label>
                    <Textarea id="address" v-model="localSchool.address.address" rows="3" class="mt-1 block w-full"
                        required />
                </div>
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700 dark:text-gray-300">City</label>
                    <InputText id="city" v-model="localSchool.address.city" class="mt-1 block w-full" required />
                </div>
                <div>
                    <label for="state" class="block text-sm font-medium text-gray-700 dark:text-gray-300">State</label>
                    <InputText id="state" v-model="localSchool.address.state" class="mt-1 block w-full" required />
                </div>
                <div>
                    <label for="postal_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Postal
                        Code</label>
                    <InputText id="postal_code" v-model="localSchool.address.postal_code" class="mt-1 block w-full" />
                </div>
                <div class="md:col-span-2">
                    <label for="country"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Country</label>
                    <Dropdown id="country" v-model="localSchool.address.country_id" :options="countries"
                        optionLabel="name" optionValue="id" class="w-full" required />
                </div>
            </div>
            <small v-if="errors.address" class="text-red-500">{{ errors.address[0] }}</small>
        </div>

        <!-- Timezone -->
        <div>
            <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Default
                Timezone</label>
            <Dropdown id="timezone" v-model="localSchool.timezone" :options="timezones" optionLabel="label"
                optionValue="value" class="w-full" filter required placeholder="Select Timezone" />
            <small v-if="errors.timezone" class="text-red-500">{{ errors.timezone[0] }}</small>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-4">
            <Button label="Cancel" class="p-button-text" @click="$emit('cancel')" />
            <Button type="submit" label="Save" class="p-button-primary" :loading="submitting" />
        </div>
    </form>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const props = defineProps({
    school: { type: Object, default: null },
});
const emit = defineEmits(['submit', 'cancel']);

const toast = useToast();
const localSchool = ref({
    name: '',
    code: '',
    address: { address: '', city: '', state: '', postal_code: '', country_id: null },
    timezone: '',
});
const errors = ref({});
const submitting = ref(false);
const countries = ref([]); // Fetch from API
const timezones = ref([]); // IANA timezones from backend

watch(() => props.school, (newSchool) => {
    if (newSchool) {
        localSchool.value = { ...newSchool, address: newSchool.primary_address || {} };
    }
}, { immediate: true });

onMounted(async () => {
    try {
        const [countriesRes, timezonesRes] = await Promise.all([
            axios.get('/api/countries'),
            axios.get('/api/timezones'), // Assume endpoint returns getIanaTimezones()
        ]);
        countries.value = countriesRes.data;
        timezones.value = timezonesRes.data.map(tz => ({ label: tz, value: tz }));
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load options', life: 3000 });
    }
});

const submitForm = async () => {
    submitting.value = true;
    errors.value = {};
    try {
        emit('submit', localSchool.value);
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors;
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Form submission failed', life: 3000 });
        }
    } finally {
        submitting.value = false;
    }
};
</script>
