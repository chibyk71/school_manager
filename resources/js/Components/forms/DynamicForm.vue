<!-- resources/js/components/forms/DynamicForm.vue -->
<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FieldCategory from './FieldCategory.vue';
import { useCustomFields } from '@/composables/useCustomFields';
import type { CustomField } from '@/types/form';
import { Message, ProgressSpinner } from 'primevue';

const props = defineProps<{
    /** Laravel model name (e.g. "Student", "Teacher") */
    model: string;

    /** Entity ID for edit forms */
    entityId?: number | string;

    /** Submit URL */
    submitUrl: string;

    /** HTTP method */
    method?: 'post' | 'put' | 'patch';

    /** Optional initial data override */
    initialData?: Record<string, any>;
}>();

const emit = defineEmits<{
    (e: 'submitted'): void;
    (e: 'cancelled'): void;
}>();

// ------------------------------------------------------------------
// Fetch custom fields + pre-filled values
// ------------------------------------------------------------------
const { categories, initialValues: values, loading, error, refetch } = useCustomFields({ model: props.model, entityId: props.entityId });

// ------------------------------------------------------------------
// Inertia form
// ------------------------------------------------------------------
const inertiaForm = useForm({
    // Merge: pre-filled values → initialData prop → empty
    ...(values.value || {}),
    ...(props.initialData || {}),
});

const submitting = ref(false);

// ------------------------------------------------------------------
// Form data (reactive proxy for v-model binding)
// ------------------------------------------------------------------
const formData = ref<Record<string, any>>({});

watch(values, (newValues) => {
    formData.value = { ...newValues, ...props.initialData };
}, { immediate: true });

watch(formData, (newData) => {
    inertiaForm.transform((data) => ({ ...data, ...newData }));
}, { deep: true });

// ------------------------------------------------------------------
// Submit handler
// ------------------------------------------------------------------
const submit = () => {
    submitting.value = true;
    inertiaForm.clearErrors();

    const httpMethod = props.method || (props.entityId ? 'put' : 'post');

    inertiaForm[httpMethod](props.submitUrl, {
        onSuccess: () => {
            submitting.value = false;
            emit('submitted');
        },
        onError: () => {
            submitting.value = false;
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
};

// ------------------------------------------------------------------
// Computed: All errors from Inertia
// ------------------------------------------------------------------
const allErrors = computed(() => {
    const errors: Record<string, string> = {};
    Object.keys(inertiaForm.errors).forEach((key) => {
        if (inertiaForm.errors[key]) {
            errors[key] = inertiaForm.errors[key];
        }
    });
    return errors;
});
</script>

<template>
    <form @submit.prevent="submit" class="space-y-8">
        <!-- Loading State -->
        <div v-if="loading" class="flex flex-col items-center py-12">
            <ProgressSpinner />
            <p class="mt-4 text-gray-600">Loading form fields...</p>
        </div>

        <!-- API Error -->
        <Message v-else-if="error" severity="error" :closable="false">
            {{ error }}
            <button type="button" @click="refetch" class="ml-4 underline">Retry</button>
        </Message>

        <!-- Form Content -->
        <div v-else-if="categories.length > 0">
            <!-- Render each category -->
            <FieldCategory v-for="category in categories" :key="category.name" :category="category" :model="formData"
                :errors="allErrors" />

            <!-- Submit Button -->
            <div class="flex justify-end gap-4 mt-10">
                <button type="button" @click="emit('cancelled')"
                    class="px-6 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </button>

                <button type="submit" :disabled="submitting || inertiaForm.processing"
                    class="px-8 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 disabled:opacity-70 transition flex items-center gap-2">
                    <ProgressSpinner v-if="submitting || inertiaForm.processing" style="width:18px;height:18px"
                        strokeWidth="5" />
                    <span>{{ props.entityId ? 'Update' : 'Create' }}</span>
                </button>
            </div>
        </div>

        <!-- No Fields -->
        <Message v-else severity="info" :closable="false">
            No custom fields defined for this form.
        </Message>
    </form>
</template>

<style scoped>
:deep(.p-accordion-header) {
    @apply text-lg font-semibold;
}

:deep(.p-accordion-content) {
    @apply p-6 bg-gray-50 dark:bg-gray-900/50 rounded-b-lg;
}
</style>
