<script lang="ts" setup>
import { useTrashedToggle } from '@/composables/useTrashedToggle';
import { Link } from '@inertiajs/vue3';
import { Button, Breadcrumb, type ButtonProps, ButtonEmits } from 'primevue';

defineProps<{
    title: string;
    crumb: {
        icon?: string;
        label?: string;
        url?: string;
    }[];
    buttons?: Array<ButtonProps & Partial<ButtonEmits>>;
    canSeeTrashed?: boolean;
}>();

const trashedApi = useTrashedToggle();
</script>

<template>
    <div class="mb-4 flex flex-col gap-3 sm:mb-5 md:flex-row md:items-center md:justify-between">
        <div class="min-w-0">
            <h3 class="page-title mb-1 text-xl font-semibold text-dark dark:text-dark-text-primary">
                {{ title }}
            </h3>
            <Breadcrumb
                class="!bg-transparent p-0 text-sm text-color dark:!bg-transparent"
                :home="{ icon: 'pi pi-home', url: '/' }"
                :model="crumb"
            >
                <template #item="slotProps">
                    <li class="breadcrumb-item">
                        <Link v-if="slotProps.item.url" :href="slotProps.item.url" class="inline-flex items-center gap-1">
                            <i v-if="slotProps.item.icon" :class="slotProps.item.icon" />
                            {{ slotProps.item.label }}
                        </Link>
                        <span v-else class="inline-flex items-center gap-1">
                            <i v-if="slotProps.item.icon" :class="slotProps.item.icon" />
                            {{ slotProps.item.label }}
                        </span>
                    </li>
                </template>
            </Breadcrumb>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                v-if="trashedApi && canSeeTrashed"
                :icon="trashedApi.showTrashed ? 'pi pi-refresh' : 'pi pi-trash'"
                :severity="trashedApi.showTrashed ? 'primary' : 'secondary'"
                :outlined="!trashedApi.showTrashed"
                @click="trashedApi.toggleTrashed"
            />

            <template v-for="(button, idx) in buttons" :key="idx">
                <Button v-bind="button" />
            </template>
        </div>
    </div>
</template>
