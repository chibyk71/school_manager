<script setup lang="ts">
import { modals } from '@/helpers';
import { ModalComponentDirectory } from './ModalDirectory';
import { computed, defineAsyncComponent, provide } from 'vue';

const modal = computed(() => {
    return modals.items[0]
});

const modalData = computed(() => modal.value?.data);
const modalId = computed(() => modal.value?.id);

provide('data', modalData);

const modalComponent = computed(() => {
    const directory = ModalComponentDirectory.value;
    return modalId.value && directory[modalId.value] ? defineAsyncComponent(directory[modalId.value]) : undefined;
});

</script>
<template>
    <component
        v-if="!!modalComponent"
        :is="modalComponent"
        :key="modalId"
        :id="modalId"
        v-bind="modalData"
    />
</template>
