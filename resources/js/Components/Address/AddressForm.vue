<!--
  AddressForm — single Address representation (Phase 4).
  Persistence-agnostic: no owner knowledge, no API submits.
  Uses Dynamic Enum for type and AsyncSelect for location hierarchy.
-->
<script setup lang="ts">
import { watch } from 'vue';
import { InputText, Message, Checkbox } from 'primevue';
import AsyncSelect from '@/Components/forms/AsyncSelect.vue';
import DynamicEnumField from '@/Components/forms/DynamicEnumField.vue';
import type { AddressFormData } from '@/types/address';

const props = defineProps<{
    errors?: Partial<Record<keyof AddressFormData, string[] | string>>;
    disabled?: boolean;
    /** Show is_primary checkbox (embedded create only; managed uses explicit set/unset). */
    showPrimary?: boolean;
}>();

const form = defineModel<AddressFormData>({ required: true });

const getErrorMessage = (field: keyof AddressFormData): string | undefined => {
    const err = props.errors?.[field];
    return Array.isArray(err) ? err[0] : err;
};

// Cascade: clear dependents when parent changes
watch(
    () => form.value.country_id,
    (next, prev) => {
        if (next !== prev) {
            form.value.state_id = null;
            form.value.city_id = null;
        }
    }
);
watch(
    () => form.value.state_id,
    (next, prev) => {
        if (next !== prev) {
            form.value.city_id = null;
        }
    }
);
</script>

<template>
    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">Address type</label>
            <DynamicEnumField
                v-model="form.type"
                model="App\\Models\\Address"
                property="type"
                :disabled="disabled"
                placeholder="Select type"
            />
            <Message v-if="getErrorMessage('type')" severity="error" size="small" class="mt-1">
                {{ getErrorMessage('type') }}
            </Message>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Address line 1 <span class="text-red-500">*</span></label>
            <InputText
                v-model="form.address_line_1"
                class="w-full"
                :disabled="disabled"
                :invalid="!!getErrorMessage('address_line_1')"
                placeholder="Street address"
            />
            <Message v-if="getErrorMessage('address_line_1')" severity="error" size="small" class="mt-1">
                {{ getErrorMessage('address_line_1') }}
            </Message>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Address line 2</label>
            <InputText
                v-model="form.address_line_2"
                class="w-full"
                :disabled="disabled"
                placeholder="Apartment, suite, etc."
            />
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Landmark</label>
            <InputText
                v-model="form.landmark"
                class="w-full"
                :disabled="disabled"
                placeholder="Near …"
            />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Country</label>
                <AsyncSelect
                    v-model="form.country_id"
                    url="/api/countries"
                    option-label="name"
                    option-value="id"
                    placeholder="Select country"
                    :disabled="disabled"
                    :search-params="{}"
                />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">State</label>
                <AsyncSelect
                    v-model="form.state_id"
                    url="/api/states"
                    option-label="name"
                    option-value="id"
                    placeholder="Select state"
                    :disabled="disabled || !form.country_id"
                    :search-params="{ 'filters[country_id]': form.country_id }"
                />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">City</label>
                <AsyncSelect
                    v-model="form.city_id"
                    url="/api/cities"
                    option-label="name"
                    option-value="id"
                    placeholder="Select city"
                    :disabled="disabled || !form.state_id"
                    :search-params="{ 'filters[state_id]': form.state_id }"
                />
            </div>
        </div>

        <div v-if="!form.city_id">
            <label class="block text-sm font-medium mb-1">City (text)</label>
            <InputText
                v-model="form.city_text"
                class="w-full"
                :disabled="disabled"
                placeholder="Free-text locality when city is not listed"
            />
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Postal code</label>
            <InputText
                v-model="form.postal_code"
                class="w-full"
                :disabled="disabled"
            />
        </div>

        <div v-if="showPrimary" class="flex items-center gap-2">
            <Checkbox v-model="form.is_primary" binary :disabled="disabled" input-id="addr-primary" />
            <label for="addr-primary" class="text-sm">Set as primary address</label>
        </div>
    </div>
</template>
