<script setup lang="ts">
/**
 * Settings/Academic/PromotionNotifications.vue — channel toggles per promotion event.
 */
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue';
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button, Card, ToggleSwitch, Message } from 'primevue';
import { useSettingsNavigation } from '@/composables/useSettingsNavigation';

type Channels = { database: boolean; mail: boolean; sms: boolean };

interface Props {
    settings: {
        batch_ready_for_approval?: Channels;
        batch_approved?: Channels;
        batch_completed?: Channels;
        student_outcome?: Channels;
    };
    crumbs: Array<{ label: string }>;
}

const defaults = (): Channels => ({ database: true, mail: true, sms: false });

const props = defineProps<Props>();
const { academicSettingsNav } = useSettingsNavigation();

const form = useForm({
    batch_ready_for_approval: {
        ...defaults(),
        ...(props.settings.batch_ready_for_approval ?? {}),
    },
    batch_approved: {
        ...defaults(),
        ...(props.settings.batch_approved ?? {}),
    },
    batch_completed: {
        database: true,
        mail: true,
        sms: true,
        ...(props.settings.batch_completed ?? {}),
    },
    student_outcome: {
        database: true,
        mail: true,
        sms: true,
        ...(props.settings.student_outcome ?? {}),
    },
});

const events: { key: keyof typeof form; title: string; description: string }[] = [
    {
        key: 'batch_ready_for_approval',
        title: 'Batch ready for review',
        description: 'When population finishes and the batch is ready for staff review.',
    },
    {
        key: 'batch_approved',
        title: 'Batch approved',
        description: 'When a reviewer approves the batch for execution.',
    },
    {
        key: 'batch_completed',
        title: 'Batch completed',
        description: 'When execution finishes for all students in the batch.',
    },
    {
        key: 'student_outcome',
        title: 'Student outcome',
        description: 'Per-student notification of final promote / repeat / graduate result.',
    },
];

const submit = () => {
    form.post(route('settings.academic.promotion-notifications.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AuthenticatedLayout title="Promotion Notifications" :crumb="props.crumbs">
        <Head title="Promotion Notifications" />

        <SettingsLayout>
            <template #left>
                <SettingsSidebar title="Academic" :items="academicSettingsNav" />
            </template>

            <template #main>
                <div class="max-w-3xl">
                    <form @submit.prevent="submit">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 pb-6 border-b border-gray-200 dark:border-gray-700"
                        >
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                    Promotion Notifications
                                </h1>
                                <p class="text-gray-600 dark:text-gray-400 mt-1">
                                    Choose channels for each promotion lifecycle event
                                </p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <Button label="Save Changes" type="submit" :loading="form.processing" />
                            </div>
                        </div>

                        <Message severity="info" :closable="false" class="mb-6">
                            SMS requires a configured gateway. Mail and in-app (database)
                            notifications use your existing notification stack.
                        </Message>

                        <div class="flex flex-col gap-4">
                            <Card
                                v-for="evt in events"
                                :key="evt.key"
                                class="shadow-none border border-gray-200 dark:border-gray-700"
                            >
                                <template #title>{{ evt.title }}</template>
                                <template #subtitle>{{ evt.description }}</template>
                                <template #content>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <ToggleSwitch v-model="form[evt.key].database" />
                                            <span class="text-sm">In-app</span>
                                        </label>
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <ToggleSwitch v-model="form[evt.key].mail" />
                                            <span class="text-sm">Email</span>
                                        </label>
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <ToggleSwitch v-model="form[evt.key].sms" />
                                            <span class="text-sm">SMS</span>
                                        </label>
                                    </div>
                                </template>
                            </Card>
                        </div>
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
