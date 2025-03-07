<script lang="ts" setup>
import { Avatar, Button, Card } from 'primevue';
const props = defineProps({
    name: {
        type: String,
        default: 'BirthDayCertyificate.pdf',
    },
    canDownload: {
        type: Boolean,
        required: false,
        default: true
    }
})

</script>

<template>
    <Card>
        <template #title class="card-header">
            <h5>Documents</h5>
        </template>
        <template #content class="card-body">
            <div v-for="i in 2" class="bg-surface-100/50 border rounded flex items-center justify-between mb-3 p-2">
                <div class="flex items-center overflow-hidden">
                    <Avatar icon="ti ti-pdf" />
                    <div class="ml-2">
                        <p class="line-clamp-1 font-medium text-dark">{{ name }}</p>
                    </div>
                </div>
                <Button v-if="canDownload" icon="ti ti-download" severity="contrast" size="small" />
            </div>
        </template>
    </Card>
</template>
