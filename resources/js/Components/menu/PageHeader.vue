<script lang="ts" setup>
import { Link } from '@inertiajs/vue3';
import { Button, Breadcrumb } from 'primevue';


</script>

<template>
    <!-- Page Header -->
    <div class="md:flex block items-center justify-between mb-3">
        <div class="my-auto mb-2">
            <h3 class="page-title text-xl/none text-color font-semibold mb-1">Blank</h3>
            <Breadcrumb class="p-0 bg-transparent dark:bg-transparent text-color" :home="{ icon: 'pi pi-home', url: '/' }" :model="[{ label: 'Dashboard'}, { label: 'Content'},{ label: 'Blank', url: '/home' }]">
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
        <div class="flex xl:my-auto right-content items-center flex-wrap">
            <div class="pr-1 mb-2">
                <Button :as="Link" v-tooltip="'Refresh'" href='#' icon="pi pi-refresh" severity="secondary" variant="outlined" aria-label="Refresh" />
            </div>
            <div class="pr-1 mb-2">
                <Button :as="Link" v-tooltip="'Print'" href='#' icon="pi pi-printer" severity="secondary" variant="outlined" aria-label="Printer" />
            </div>
        </div>
    </div>
    <!-- /Page Header -->
</template>
