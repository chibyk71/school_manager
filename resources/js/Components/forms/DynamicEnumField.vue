<script setup lang="ts">
/**
 * DynamicEnumField — form control bound to a Dynamic Enum definition key.
 * Usage: <DynamicEnumField v-model="form.gender" enum-key="profile.gender" label="Gender" />
 */
import { computed, onMounted, watch } from 'vue';
import { useDynamicEnums } from '@/composables/useDynamicEnums';
import RadioButton from 'primevue/radiobutton';
import ProgressSpinner from 'primevue/progressspinner';
import { Select } from 'primevue';

interface Props {
    enumKey: string;
    label?: string;
    placeholder?: string;
    mode?: 'auto' | 'dropdown' | 'radio';
    inline?: boolean;
    disabled?: boolean;
    formError?: string;
}

const props = withDefaults(defineProps<Props>(), {
    label: '',
    placeholder: 'Select an option',
    mode: 'auto',
    inline: false,
    disabled: false,
});

const { options, loading, error, load } = useDynamicEnums();
const localValue = defineModel<string | null>({ required: true });

const renderMode = computed(() => {
    if (props.mode !== 'auto') return props.mode;
    return options.value.length <= 4 ? 'radio' : 'dropdown';
});

onMounted(async () => { await load(props.enumKey); });
watch(() => props.enumKey, async (key) => { await load(key); });
</script>

<template>
    <div class="space-y-2">
        <label v-if="label && renderMode === 'dropdown'" class="block text-sm font-medium text-gray-700">{{ label }}</label>
        <div v-if="loading" class="flex items-center justify-center py-4">
            <ProgressSpinner style="width: 32px; height: 32px" />
            <span class="ml-2 text-gray-600">Loading options...</span>
        </div>
        <div v-else-if="error" class="text-red-600 text-sm">{{ error }}</div>
        <div v-else-if="options.length === 0" class="text-gray-500 text-sm">No options available.</div>
        <Select
            v-else-if="renderMode === 'dropdown'"
            v-model="localValue"
            :options="options"
            optionLabel="label"
            optionValue="value"
            :placeholder="placeholder"
            :loading="loading"
            :disabled="disabled || loading"
            class="w-full"
            showClear
        />
        <div v-else class="space-y-3">
            <div v-if="!inline && label" class="block text-sm font-medium text-gray-700 mb-2">{{ label }}</div>
            <div :class="inline ? 'flex flex-wrap gap-6' : 'space-y-3'">
                <div v-for="option in options" :key="option.value" class="flex items-center">
                    <RadioButton
                        v-model="localValue"
                        :inputId="`de-${enumKey}-${option.value}`"
                        :value="option.value"
                        :disabled="disabled"
                    />
                    <label :for="`de-${enumKey}-${option.value}`" class="ml-2 text-sm text-gray-700 cursor-pointer select-none">
                        {{ option.label }}
                    </label>
                </div>
            </div>
        </div>
        <span v-if="!!formError" class="text-red-500 text-xs font-light">{{ formError }}</span>
    </div>
</template>
