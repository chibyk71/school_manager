<script setup lang="ts">
import { StudentMenu } from '@/store';
import { Link } from '@inertiajs/vue3';
import { Avatar, Badge, Button, Card } from 'primevue';
import { reactive, useTemplateRef } from 'vue';

   const props = defineProps<{
        avatar: string,
        name: string,
        level: string,
        description: {[x:string]:any}[],
        enrollment_id: string|number,
        id?:string,
        phone: string|number,
        email: string,
        status: boolean,
    }>()

    let detail = reactive(props.description)

    const dropdownref = useTemplateRef('options')
</script>

<template>
    <Card>
        <template #content class="card-body">
            <div class="bg-surface-300/35 rounded-xl p-3 mb-3">
                <Link href="/student-details" class="flex items-center">
                    <Avatar :image="avatar" size="large" shape="circle" />
                    <div class="mx-2">
                        <h5 class="text-dark line-clamp-1 leading-none font-semibold">{{ name }}</h5>
                        <p class="text-sm font-normal">{{ level }}</p>
                    </div>
                </Link>
            </div>
            <div class="flex items-center justify-between gap-x-2">
                <div v-for="value in detail">
                    <p class="text-xs/none font-light">{{ value }}</p>
                    <!-- <p class="font-semibold text-sm/none dark:text-dark">{{ value }}</p> -->
                </div>
            </div>
        </template>
        <template #title>
            <div class="card-header flex items-center justify-between">
                <a href="student-details.html" class="text-primary">{{ enrollment_id }}</a>
                <div class="flex items-center">
                    <Badge :severity="status? `success`: 'danger'">{{status? 'Active': 'Inactive'}}</Badge>
                    <Button size="small" icon="ti ti-dots-vertical" @click="(e) => dropdownref?.toggle(e)"
                        severity="secondary" class="ml-2" />
                    <Menu :model="StudentMenu" popup ref="options" />
                </div>
            </div>
        </template>
        <template #footer>
            <div class="card-footer flex items-center justify-between">
                <div class="flex items-center gap-x-1.5">
                    <Button rounded size="small" icon="ti ti-brand-hipchat" variant="outlined" severity="secondary" />
                    <Button v-if="phone" as="a" :href="`tel:${phone}`" rounded size="small" icon="ti ti-phone" variant="outlined" severity="secondary" />
                    <Button v-if="email" as="a" :href="`mailto:${email}`" rounded size="small" icon="ti ti-mail" variant="outlined" severity="secondary" />
                </div>
                <!-- TODO: link to details page -->
                <Button size="small" icon="ti ti-arrow-right" v-tooltip="'View Details'" severity="secondary" />
            </div>
        </template>
    </Card>
</template>
