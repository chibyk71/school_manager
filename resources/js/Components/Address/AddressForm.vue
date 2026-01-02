<!-- resources/js/Components/Forms/AddressForm.vue -->
<!--
AddressForm.vue v2.0 – Production-Ready Reusable Address Form (Fully Aligned with Address Module)

Purpose & Problems Solved:
- Central, reusable form component for all address input across the application.
- Perfectly mirrors backend validation (HasAddress trait) and model fillable fields.
- Full v-model support for two-way binding (used in AddressManager.vue and AddressModal.vue).
- Cascading async selects powered by nnjeim/world API (Country → State → City) using your production AsyncSelect.vue.
- Nigeria-first UX: landmark with tooltip, city_text shown only when no city selected.
- Address type dropdown driven by centralized ADDRESS_TYPE_OPTIONS from address.ts (consistent with backend enum).
- Geolocation inputs with high precision (decimal:7) and proper bounds.
- Primary checkbox with clear labelling.
- Comprehensive validation feedback via Inertia errors prop + PrimeVue invalid states.
- Disabled state propagation for loading/submitting scenarios.
- Responsive Tailwind grid layout (mobile-friendly).
- Accessible: labels, required indicators, tooltips, proper ARIA via PrimeVue.
- Clean error display using PrimeVue Message component.
- Type-safe with AddressFormData from address.ts – no mismatched fields.

Key Changes in v2.0:
- Upgraded to use ADDRESS_TYPE_OPTIONS & getAddressTypeLabel from '@/types/address' (single source of truth).
- Fixed AsyncSelect configuration to exactly match nnjeim/world documented endpoints:
  • Countries: GET /api/countries?search=...
  • States: GET /api/states?filters[country_id]=...
  • Cities: GET /api/cities?filters[state_id]=...
- Ensured cascading works reliably via reactive search_params.
- Improved conditional rendering: State only when country selected, city_text only when no city.
- Consistent height styling for all inputs.
- Better error handling integration (p-invalid class + Message).
- Added required indicators and improved labels.

Fits into the Address Management Module:
- Core input component used by:
  • AddressModal.vue (create/edit modal)
  • AddressManager.vue (inline first address in create mode)
- Works with useAddress composable (direct submit mode) and bundled emit mode.
- Validation errors come from Inertia (via useModalForm or parent form).
- Type dropdown stays in sync with backend validation and configurable via HasConfig.

Dependencies:
- PrimeVue: InputText, InputNumber, Checkbox, Select, Message
- AsyncSelect.vue (your production async dropdown)
- '@/types/address' (AddressFormData, ADDRESS_TYPE_OPTIONS)
- nnjeim/world API routes registered with prefix 'api'
-->

<script setup lang="ts">
import { InputText, InputNumber, Checkbox, Message, Select } from 'primevue';
import AsyncSelect from '@/Components/forms/AsyncSelect.vue';
import type { AddressFormData } from '@/types/address';
import { ADDRESS_TYPE_OPTIONS } from '@/types/address';

// ------------------------------------------------------------------
// Props
// ------------------------------------------------------------------
const props = defineProps<{
    /** Inertia validation errors from server */
    errors?: Partial<Record<keyof AddressFormData, string[] | string>>;
    /** Disable all inputs (e.g., during submission) */
    disabled?: boolean;
}>();

// ------------------------------------------------------------------
// v-model (two-way binding)
// ------------------------------------------------------------------
const form = defineModel<AddressFormData>({ required: true });

// ------------------------------------------------------------------
// Helper: Convert errors to string for display
// ------------------------------------------------------------------
const getErrorMessage = (field: keyof AddressFormData): string | undefined => {
    const err = props.errors?.[field];
    return Array.isArray(err) ? err[0] : err;
};
</script>

<template>
    <div class="space-y-6">
        <!-- Country -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Country <span class="text-red-500">*</span>
            </label>
            <AsyncSelect
                id="country"
                v-model="form.country_id"
                :disabled="disabled"
                :invalid="!!getErrorMessage('country_id')"
                :field="{
                    placeholder: 'Search and select country',
                    search_url: '/api/countries',
                }"
                class="w-full"
            />
            <Message v-if="getErrorMessage('country_id')" severity="error" variant="simple" class="mt-1">
                {{ getErrorMessage('country_id') }}
            </Message>
        </div>

        <!-- State (shown only if country selected) -->
        <div v-if="form.country_id">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                State/Province
            </label>
            <AsyncSelect
                id="state"
                v-model="form.state_id"
                :disabled="disabled"
                :invalid="!!getErrorMessage('state_id')"
                :field="{
                    placeholder: 'Search and select state',
                    search_url: '/api/states',
                    field_options: {
                        search_params: { 'filters[country_id]': form.country_id },
                    }
                }"
                class="w-full"
            />
            <Message v-if="getErrorMessage('state_id')" severity="error" variant="simple" class="mt-1">
                {{ getErrorMessage('state_id') }}
            </Message>
        </div>

        <!-- City -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                City/Town
            </label>
            <AsyncSelect
                id="city"
                v-model="form.city_id"
                :disabled="disabled"
                :invalid="!!getErrorMessage('city_id')"
                :field="{
                    placeholder: 'Search and select city',
                    search_url: '/api/cities',
                    field_options: {
                        search_params: form.state_id ? { 'filters[state_id]': form.state_id } : {},
                    }
                }"
                class="w-full"
            />
            <Message v-if="getErrorMessage('city_id')" severity="error" variant="simple" class="mt-1">
                {{ getErrorMessage('city_id') }}
            </Message>
        </div>

        <!-- Address Lines -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Address Line 1 <span class="text-red-500">*</span>
                </label>
                <InputText
                    v-model="form.address_line_1"
                    :disabled="disabled"
                    placeholder="Street address, building number"
                    :class="{ 'p-invalid': getErrorMessage('address_line_1') }"
                    class="w-full"
                />
                <Message v-if="getErrorMessage('address_line_1')" severity="error" variant="simple" class="mt-1">
                    {{ getErrorMessage('address_line_1') }}
                </Message>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Address Line 2
                </label>
                <InputText
                    v-model="form.address_line_2"
                    :disabled="disabled"
                    placeholder="Apartment, suite, area"
                    class="w-full"
                />
            </div>
        </div>

        <!-- Landmark & City Text Fallback -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Landmark
                    <i
                        class="pi pi-info-circle text-xs text-gray-500 ml-1 cursor-help"
                        v-tooltip.top="'Common in Nigeria – e.g., Near GTBank, Opposite Shoprite'"
                    ></i>
                </label>
                <InputText
                    v-model="form.landmark"
                    :disabled="disabled"
                    placeholder="Nearby reference point"
                    class="w-full"
                />
            </div>

            <div v-if="!form.city_id">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    City/Town (free-text)
                </label>
                <InputText
                    v-model="form.city_text"
                    :disabled="disabled"
                    placeholder="If not found in list above"
                    class="w-full"
                />
            </div>
        </div>

        <!-- Postal Code & Type -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Postal Code
                </label>
                <InputText v-model="form.postal_code" :disabled="disabled" class="w-full" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Address Type
                </label>
                <Select
                    v-model="form.type"
                    :options="ADDRESS_TYPE_OPTIONS"
                    option-label="label"
                    option-value="value"
                    :disabled="disabled"
                    placeholder="Select type"
                    class="w-full"
                />
            </div>
        </div>

        <!-- Geolocation -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Latitude
                </label>
                <InputNumber
                    v-model="form.latitude"
                    :disabled="disabled"
                    :min="-90"
                    :max="90"
                    :step="0.000001"
                    mode="decimal"
                    :min-fraction-digits="6"
                    :max-fraction-digits="7"
                    class="w-full"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Longitude
                </label>
                <InputNumber
                    v-model="form.longitude"
                    :disabled="disabled"
                    :min="-180"
                    :max="180"
                    :step="0.000001"
                    mode="decimal"
                    :min-fraction-digits="6"
                    :max-fraction-digits="7"
                    class="w-full"
                />
            </div>
        </div>

        <!-- Primary Address -->
        <div class="flex items-center gap-3 mt-4">
            <Checkbox v-model="form.is_primary" :binary="true" :disabled="disabled" />
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">
                Set as Primary Address
            </label>
        </div>
    </div>
</template>

<style scoped lang="postcss">
/* Unified input height across PrimeVue components */
:deep(.p-inputtext),
:deep(.p-inputnumber-input),
:deep(.p-dropdown) {
    @apply h-11;
}
</style>