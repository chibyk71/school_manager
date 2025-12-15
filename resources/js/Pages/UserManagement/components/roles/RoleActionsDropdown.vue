<script setup lang="ts">
import { Button } from 'primevue';

const emit = defineEmits(['edit', 'manage-permissions', 'delete']);

defineProps<{
    role: any;
}>();
</script>

<template>
    <div class="flex items-center justify-end gap-1">
        <Button icon="pi pi-pencil" severity="secondary" text rounded size="small" v-tooltip.top="'Edit Role'"
            @click="emit('edit')" />
        <Button icon="pi pi-shield" severity="info" text rounded size="small" v-tooltip.top="'Manage Permissions'"
            @click="emit('manage-permissions')" />
        <Button icon="pi pi-trash" severity="danger" text rounded size="small" v-tooltip.top="'Delete Role'"
            @click="emit('delete')" />
    </div>
</template>