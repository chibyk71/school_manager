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
import { ref, onBeforeUnmount } from 'vue';

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

const emit = defineEmits(['update:modelValue', 'before-fetch', 'fetch-success', 'fetch-error', 'after-fetch'])

const options = ref<any[]>([])
const searchTimeout = ref<number | undefined>(undefined)
const loading = ref<boolean>(false)


const onFocus = () => {
    if (options.value.length === 0) {
        loadOptions()
    }
}
/**
 * Handles input events by clearing the previous search timeout and setting a new one to load options with the input value.
 * This is used to debounce the search so that it's not triggered on every key press.
 */

const onInput = (event: any) => {
    clearTimeout(searchTimeout.value)
    searchTimeout.value = window.setTimeout(() => {
        loadOptions(event.target.value)
    }, searchDelay.value)
}

/**
 * Handles the enter key press event.
 * Prevents the default enter key behavior.
 * If the options list is not empty, emits an `update:modelValue` event with the first option's value.
 */
const onEnter = (event: any) => {
    event.preventDefault()
    if (options.value.length > 0) {
        emit('update:modelValue', options.value[0][optionValue.value])
    }
}

/**
 * Asynchronously loads options from a specified URL or route.
 * Initiates a fetch request to retrieve data based on the provided search term.
 * Updates the options list with the fetched data.
 *
 * @param {string} [search] - Optional search query to filter the options.
 *                            Defaults to undefined if not provided.
 * 
 * @remarks
 * If `props.searchUrl` is defined, it will be used as the request URL.
 * Otherwise, the `route('options')` with query parameters is used.
 * Handles errors by logging them to the console and ensures loading state is managed.
 */

const loadOptions = async (search?: string) => {
    emit('before-fetch')
    loading.value = true
    try {
        const response = await fetch(props.searchUrl ? props.searchUrl : route('options', { [searchKey.value]: search, resource: props.resource }), {
            headers: {
                'Accept': 'application/json'
            },
            credentials: 'include',
        })
        const data = await response.json()
        if (!response.ok) {
            throw new Error(data.message || 'Failed to load options')
        }
        options.value = data.data ?? []
        emit('fetch-success', data)
    } catch (error) {
        console.error(error)
        emit('fetch-error', error)
    } finally {
        loading.value = false
        emit('after-fetch')
    }
}

onBeforeUnmount(() => {
    clearTimeout(searchTimeout.value)
})
</script>
