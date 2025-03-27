<script setup lang="ts">
import { modals, useSubmitForm } from '@/helpers';
import { InertiaForm } from '@inertiajs/vue3';
import { Button, Dialog, DialogProps, DialogSlots } from 'primevue';
import { computed } from 'vue';

const props = defineProps<DialogProps & {
    id: string,
    form?: InertiaForm<{}>,
    resource: string,
    resource_id?: string | number,
}>()

defineSlots<DialogSlots>()

const modalData = computed(() => modals.items[0]?.data || {});

const isvisible = computed(() => modals.items[0]?.id == props.id);

const { submitForm } = useSubmitForm()

const resource_id = computed(() => props.resource_id ?? modalData.value?.['resource_id'])

</script>

<template>
    <Dialog :visible="isvisible" :modal="modal" :header="props.header" :footer="props.footer"
        :maximizable="props.maximizable" block-scroll class="w-full sm:w-3/4 md:w-2/4">
        <template #footer>
            <div class="flex items-center justify-end gap-x-2.5">
                <Button label="Cancel" severity="secondary" @click="modals.close()" >
                  <template #loadingicon="slotProps"></template>
                </Button>
                <Button label="Submit" :loading="form?.processing" @click="submitForm(form!, resource, resource_id, {
                    onSuccess: (props) => $emit('success', props),
                    onError: (errors) => $emit('error', errors),
                })" />
            </div>
        </template>
        <template #header></template>
        <template #maximizeicon></template>
        <template #closeicon>
            <Button icon="pi pi-times" class="p-button-text" text secondary rounded @click="modals.close()" />
        </template>
        <slot></slot>
    </Dialog>
</template>
