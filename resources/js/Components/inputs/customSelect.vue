<template>
    <template v-if="multiple">
        <MultiSelect size="small" fluid display="chip" :loading="loading" ref="select" :option-label="optionLabel" :option-value="optionValue"
            @focus="onFocus" @input="onInput" :options="options" v-model="model" @keydown.enter="onEnter"
            :invalid="invalid" :placeholder="placeholder" v-bind="$attrs" multiple>
        </MultiSelect>
    </template>
    <template v-else>
        <Select fluid :loading="loading" ref="select" :option-label="optionLabel" :option-value="optionValue"
            @focus="onFocus" @input="onInput" :options="options" v-model="model" @keydown.enter="onEnter"
            :invalid="invalid" :placeholder="placeholder" v-bind="$attrs">
        </Select>
    </template>
</template>

<script setup lang="ts">
import { Select, MultiSelect } from 'primevue';
import { ref, watch, onBeforeUnmount } from 'vue';

const model = defineModel();

const props = defineProps<{
    optionLabel?: string,
    optionValue?: string,
    options?: any[],
    loading?: boolean,
    placeholder?: string,
    invalid: boolean,
    resource?: string,
    searchUrl?: string,
    searchKey?: string,
    searchDelay?: number,
    multiple?: boolean,
}>()

const optionLabel = ref(props.optionLabel || 'name');
const optionValue = ref(props.optionValue || 'id');
const searchKey = ref(props.searchKey || 'search');
const searchDelay = ref(props.searchDelay || 500);

const emit = defineEmits(['update:modelValue'])

const options = ref<any[]>([])
const searchTimeout = ref<number | undefined>(undefined)
const loading = ref<boolean>(false)

const onFocus = () => {
    if (options.value.length === 0) {
        loadOptions()
    }
}

const onInput = (event: any) => {
    clearTimeout(searchTimeout.value)
    searchTimeout.value = window.setTimeout(() => {
        loadOptions(event.target.value)
    }, searchDelay.value)
}

const onEnter = (event: any) => {
    event.preventDefault()
    if (options.value.length > 0) {
        emit('update:modelValue', options.value[0][optionValue.value])
    }
}

const loadOptions = async (search?: string) => {
    loading.value = true
    try {
        const response = await fetch(props.searchUrl ? props.searchUrl : route('options', { [searchKey.value]: search, resource: props.resource }), {
            headers: {
                'Accept': 'application/json'
            },
            credentials: 'include',
        })
        const data = await response.json()
        options.value = data.data
    } catch (error) {
        console.error(error)
    } finally {
        loading.value = false
    }
}

watch(() => props.options, (newOptions) => {
    options.value = newOptions || []
}, { immediate: true })

onBeforeUnmount(() => {
    clearTimeout(searchTimeout.value)
})
</script>
