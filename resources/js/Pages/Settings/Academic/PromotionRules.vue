<script setup lang="ts">
/**
 * Settings/Academic/PromotionRules.vue — thresholds used by PromotionService recommendations.
 */
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '@/Pages/Settings/Partials/SettingsLayout.vue';
import SettingsSidebar from '@/Pages/Settings/Partials/SettingsSidebar.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button, Card, InputNumber, Message } from 'primevue';
import { useSettingsNavigation } from '@/composables/useSettingsNavigation';

interface Props {
    settings: {
        fail_subject_threshold?: number;
        pass_average?: number;
        probation_average?: number;
    };
    crumbs: Array<{ label: string }>;
}

const props = defineProps<Props>();
const { academicSettingsNav } = useSettingsNavigation();

const form = useForm({
    fail_subject_threshold: props.settings.fail_subject_threshold ?? 3,
    pass_average: props.settings.pass_average ?? 40,
    probation_average: props.settings.probation_average ?? 50,
});

const submit = () => {
    form.post(route('settings.academic.promotion-rules.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AuthenticatedLayout title="Promotion Rules" :crumb="props.crumbs">
        <Head title="Promotion Rules" />

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
                                    Promotion Rules
                                </h1>
                                <p class="text-gray-600 dark:text-gray-400 mt-1">
                                    Thresholds used when computing system recommendations
                                </p>
                            </div>
                            <div class="mt-4 sm:mt-0">
                                <Button label="Save Changes" type="submit" :loading="form.processing" />
                            </div>
                        </div>

                        <Message severity="info" :closable="false" class="mb-6">
                            Recommendations: students with failed subjects ≥ threshold are
                            recommended to <strong>repeat</strong>; otherwise average ≥ pass score
                            → <strong>promote</strong>. Attendance can additionally gate promotion
                            via Attendance Rules.
                        </Message>

                        <Card class="shadow-none border border-gray-200 dark:border-gray-700 mb-4">
                            <template #content>
                                <div class="flex flex-col gap-6">
                                    <div>
                                        <label class="block text-sm font-medium mb-1">
                                            Failed subject threshold
                                        </label>
                                        <InputNumber
                                            v-model="form.fail_subject_threshold"
                                            :min="0"
                                            :max="10"
                                            showButtons
                                            class="w-full max-w-xs"
                                        />
                                        <p class="text-xs text-gray-500 mt-1">
                                            Recommend repeat when failed subjects reach this count
                                            (default 3).
                                        </p>
                                        <small v-if="form.errors.fail_subject_threshold" class="text-red-500">
                                            {{ form.errors.fail_subject_threshold }}
                                        </small>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium mb-1">
                                            Pass average (%)
                                        </label>
                                        <InputNumber
                                            v-model="form.pass_average"
                                            :min="0"
                                            :max="100"
                                            showButtons
                                            class="w-full max-w-xs"
                                        />
                                        <p class="text-xs text-gray-500 mt-1">
                                            Minimum overall average to recommend promote.
                                        </p>
                                        <small v-if="form.errors.pass_average" class="text-red-500">
                                            {{ form.errors.pass_average }}
                                        </small>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium mb-1">
                                            Probation average (%)
                                        </label>
                                        <InputNumber
                                            v-model="form.probation_average"
                                            :min="0"
                                            :max="100"
                                            showButtons
                                            class="w-full max-w-xs"
                                        />
                                        <p class="text-xs text-gray-500 mt-1">
                                            Reserved for a future probation band; must be ≥ pass
                                            average.
                                        </p>
                                        <small v-if="form.errors.probation_average" class="text-red-500">
                                            {{ form.errors.probation_average }}
                                        </small>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
