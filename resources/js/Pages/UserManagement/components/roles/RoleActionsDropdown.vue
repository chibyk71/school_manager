<script setup lang="ts">
import { Button } from 'primevue';

defineProps<{
    role: any;
    onEdit: () => void;
    onManagePermissions: () => void;
    onDelete: () => void;
}>();
</script>

<template>
    <div class="flex items-center justify-between gap-2 w-full flex-row">
        <Button icon="pi pi-pencil" severity="secondary" rounded outlined v-tooltip.top="'Edit Role'"
            :onClick="() => onEdit()" />
        <Button icon="pi pi-shield" severity="info" rounded outlined v-tooltip.top="'Manage Permissions'"
            :onClick="() => onManagePermissions()" />
        <Button icon="pi pi-trash" severity="danger" rounded outlined v-tooltip.top="'Delete Role'"
            :onClick="() => onDelete()" />
    </div>
</template>