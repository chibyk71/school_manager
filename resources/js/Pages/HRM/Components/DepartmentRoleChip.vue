<script setup lang="ts">
import { computed, ref } from 'vue';
import { Chip, Popover } from 'primevue';

const props = defineProps<{
    roles: Array<{ id: string | number, display_name: string }>
}>();

const op = ref();

// Logical split: First 2 for the UI, the rest for the Popover
const visibleRoles = computed(() => props.roles?.slice(0, 1) || []);
const hiddenRoles = computed(() => props.roles?.slice(1) || []);

const toggleHidden = (event: Event) => {
    op.value.toggle(event);
};
</script>

<template>
    <div class="flex align-items-center flex-wrap gap-2">
        <Chip 
            v-for="role in visibleRoles" 
            :key="role.id" 
            :label="role.display_name" 
        />

        <Chip 
            v-if="hiddenRoles.length > 0" 
            :label="`+${hiddenRoles.length}`" 
            @mouseenter="toggleHidden"
            @mouseleave="toggleHidden"
            class="cursor-pointer font-bold"
            style="background-color: var(--primary-color); color: var(--primary-color-text)"
        />

        <Popover ref="op">
            <div class="flex flex-column gap-2 p-1">
                <div v-for="role in hiddenRoles" :key="role.id" class="white-space-nowrap">
                    <i class="pi pi-shield mr-2 text-primary" style="font-size: 0.8rem"></i>
                    <span>{{ role.display_name }}</span>
                </div>
            </div>
        </Popover>
    </div>
</template>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}
</style>