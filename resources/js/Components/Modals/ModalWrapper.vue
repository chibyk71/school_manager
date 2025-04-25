<script setup lang="ts">
import { modals, populateForm, ResourceData, useSubmitForm } from '@/helpers';
import { InertiaForm } from '@inertiajs/vue3';
import { Button, Dialog, DialogProps, DialogSlots, ProgressSpinner } from 'primevue';
import { inject, watch } from 'vue';
import { computed } from 'vue';

const props = defineProps<DialogProps & {
    id: string,
    form?: InertiaForm<{}>,
    resource: string,
    resource_data?: ResourceData,
    loading?: boolean,
    route?: string,
}>()

defineSlots<DialogSlots & {
    submitbtn: { form: InertiaForm<{}> | undefined; resource: string; resource_id: string | undefined };
}>()

const modalData = computed(() => {
    const injectedData = (inject('data') as { value: ResourceData | null })?.value;
    const modalItemData = modals.items[0]?.data;

    return injectedData ?? modalItemData ?? { resource_data: { id: '' } };
});

const resourceData = computed(() => {
    return modalData.value?.resource_data ?? {};
});

const isvisible = computed(() => modals.items[0]?.id == props.id);

const { submitForm } = useSubmitForm()

const resource_id = computed(() => props.resource_data?.id ?? modalData.value?.resource_data?.id),
    header = props.header ?? (resource_id && props.form ? `Edit ${props.resource.toLocaleUpperCase()}` : `Create ${props.resource}`)

watch(() => resourceData.value, (newVal) => {
        if (newVal && props.form) {
            setTimeout(() => {
                populateForm(newVal as ResourceData, props.form!)
            }, 500);
        }
    },
    { deep: true, immediate: true }
)

</script>

<template>
    <Dialog :pt="{ content: { class: 'overflow-y-hidden relative' } }" :visible="isvisible" :modal="modal" :header
        :footer="props.footer" :maximizable="props.maximizable" block-scroll class="w-full sm:w-3/4 md:w-2/4">
        <template #footer>
            <div class="flex items-center justify-end gap-x-2.5">
                <Button label="Cancel" severity="secondary" @click="modals.close()">
                </Button>
                <slot name="submitbtn" :form="props.form" :resource="props.resource" :resource_id="resource_id">
                    <Button v-if='!!form && !loading' label="Submit" :loading="form?.processing" @click="submitForm(form!, resource, resource_id, {
                        onSuccess: (props) => $emit('success', props),
                        onError: (errors) => $emit('error', errors),
                    }, props.route)" />
                </slot>
            </div>
        </template>
        <template #header></template>
        <template #maximizeicon></template>
        <template #closeicon>
            <Button icon="pi pi-times" class="p-button-text" text secondary rounded @click="modals.close()" />
        </template>
        <div class="relative top-0 left-0 flex items-center justify-center z-50 h-[50vh] w-full" v-if="loading">
            <ProgressSpinner />
        </div>
        <PerfectScrollbar>
            <div class="mb-6">
                <slot></slot>
            </div>
        </PerfectScrollbar>
    </Dialog>
</template>

<style scoped>
    .ps {
        max-height: 75vh;
    }
</style>
