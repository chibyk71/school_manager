<script setup lang="ts">
import { modals, useSubmitForm } from '@/helpers';
import { InertiaForm } from '@inertiajs/vue3';
import { Button, Dialog, DialogProps, DialogSlots } from 'primevue';
import { computed, watch, watchEffect } from 'vue';

const props = defineProps<DialogProps & {
    id: string,
    form?: InertiaForm<{}>,
    resource: string,
    resource_id?: string | number,
}>()

defineSlots<DialogSlots>()


const isvisible = computed(() => modals.items[0] == props.id)

const {submitForm} = useSubmitForm()
</script>

<template>
    <Dialog
        :visible="isvisible"
        :modal="modal"
        :header="props.header"
        :footer="props.footer"
        :closable="props.closable"
        :maximizable="props.maximizable"
        block-scroll
        class="w-full sm:w-3/4 md:w-2/4"
    >
        <template #footer>
            <div class="flex items-center justify-end gap-x-2.5">
                <Button label="Cancel" severity="secondary" @click="modals.close()" />
                <Button label="Submit" :loading="form?.processing" @click="submitForm(form!, resource, resource_id)" />
            </div>
        </template>
        <template #header></template>
        <template #maximizeicon></template>
        <template #closeicon></template>
        <slot></slot>
    </Dialog>
</template>
