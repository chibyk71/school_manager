<script lang="ts" setup>
import { Link } from '@inertiajs/vue3';
import { Button, Breadcrumb, type ButtonProps, ButtonEmits } from 'primevue';

defineProps<{
    title: string,
    crumb: {
        icon?: string,
        label?: string,
        url?: string
    }[],
    buttons?: Array<(ButtonProps & Partial<ButtonEmits>)>
}>()
</script>

<template>
    <!-- Page Header -->
    <div class="md:flex block items-center justify-between mb-3 pt-14">
        <div class="my-auto mb-2">
            <h3 class="page-title text-xl/none text-color font-semibold mb-1">{{ title }}</h3>
            <Breadcrumb class="p-0 bg-transparent dark:bg-transparent text-color" :home="{ icon: 'pi pi-home', url: '/' }" :model="crumb">
                <template #item="slotProps">
                    <li class="breadcrumb-item">
                        <Link v-if="slotProps.item.url" :href="slotProps.item.url">
                            <i v-if="slotProps.item.icon" :class="slotProps.item.icon"></i>
                            {{ slotProps.item.label }}
                        </Link>
                        <span v-else>
                            <i v-if="slotProps.item.icon" :class="slotProps.item.icon"></i>
                            {{ slotProps.item.label }}
                        </span>
                    </li>
                </template>
            </Breadcrumb>
        </div>
        <div class="flex xl:my-auto right-content items-center flex-wrap gap-2">
            <div class="mb-2" v-for="button in buttons">
                <Button v-bind="button" />
            </div>
        </div>
    </div>
    <!-- /Page Header -->
</template>
