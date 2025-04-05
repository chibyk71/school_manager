<script setup lang="ts">
import { modals } from '@/helpers';
import { ModalComponentDirectory } from './ModalDirectory';
import { computed } from 'vue';

    const modal = computed(()=>{
        return modals.items[0]
    });

    const ModalDirectory = ModalComponentDirectory.value;

    // Safely check if the modal id exists in ModalComponentDirectory
    const modalComponent = computed(()=> {
        return modal?.value?.id && ModalDirectory[modal.value.id] ? ModalComponentDirectory.value[modal.value.id] : undefined
    });
</script>
<template>
    <component v-if="!!modalComponent" :is="modalComponent" v-bind="modal.data" :key="modal.id" :id="modal.id" />
</template>
