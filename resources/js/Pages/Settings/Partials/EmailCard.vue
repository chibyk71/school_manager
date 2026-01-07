<script setup lang="ts">
import { Avatar, Badge, Button, Card, ToggleSwitch } from 'primevue';

defineProps<{
    id: string
    title: string,
    content: string,
    icon?: string
    image?: string
    status?: boolean
}>();

const model = defineModel<boolean>();
const emit = defineEmits<{ (e: 'configure', value: string): void }>();

const openModal = (driverKey: string) => {
    emit('configure', driverKey)
}
</script>

<template>
    <Card class="card">
        <template #title>
            <div class="card-header flex items-center justify-between border-0 mb-3 pb-0">
                <div class="flex items-center">
                    <Avatar :icon :image size="large" class="mr-2" />
                    <h6>{{ title }}</h6>
                </div>
                <Badge :value="status ? 'Connected' : 'Not Connected'" :severity="status ? 'success' : 'secondary'" />
            </div>
        </template>
        <template #footer>
            <div class="flex justify-between items-center">
                <div>
                    <Button severity="secondary" variant="outlined" @click="() => openModal(id)" icon="ti ti-tool"
                        label="View Integration" />
                </div>
                <ToggleSwitch v-model="model" />
            </div>
        </template>
        <template #content>
            <p> {{ content }}</p>
        </template>
    </Card>
</template>
