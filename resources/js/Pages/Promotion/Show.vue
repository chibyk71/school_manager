<script setup lang="ts">
/**
 * Promotion/Show.vue — batch overview + lifecycle actions.
 */
import { ref } from 'vue';
import { router, useForm, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Button, Tag, ProgressBar, Card, Dialog, Textarea, Message,
} from 'primevue';
import { useConfirm } from 'primevue/useconfirm';
import type { PromotionBatch } from '@/types/promotion';
import { PROMOTION_STATUS_CONFIG } from '@/types/promotion';

const props = defineProps<{
    batch: PromotionBatch;
    can: {
        review: boolean;
        approve: boolean;
        execute: boolean;
        cancel: boolean;
    };
}>();

const confirm = useConfirm();
const showApprove = ref(false);
const showCancel = ref(false);

const approveForm = useForm({ approval_comments: '' });
const cancelForm = useForm({ reason: '' });

const statusCfg = () => {
    const cfg = PROMOTION_STATUS_CONFIG[props.batch.status];
    return cfg ?? { label: props.batch.status_label, severity: 'secondary' as const, icon: 'pi pi-circle' };
};

const submitApprove = () => {
    approveForm.post(route('promotions.approve', props.batch.id), {
        preserveScroll: true,
        onSuccess: () => {
            showApprove.value = false;
            approveForm.reset();
        },
    });
};

const submitCancel = () => {
    cancelForm.post(route('promotions.cancel', props.batch.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCancel.value = false;
            cancelForm.reset();
        },
    });
};

const executeBatch = () => {
    confirm.require({
        header: 'Execute Promotion',
        message:
            'Start executing this batch? Student class placements will be updated in the background. This cannot be undone easily.',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { label: 'Execute', severity: 'success' },
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        accept: () => {
            router.post(route('promotions.execute', props.batch.id), {}, { preserveScroll: true });
        },
    });
};

const fmt = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';
</script>

<template>
    <AuthenticatedLayout
        title="Promotion Batch"
        :crumb="[
            { label: 'Promotions', url: route('promotions.index') },
            { label: batch.name },
        ]"
    >
        <Head :title="batch.name" />

        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ batch.name }}</h1>
                    <Tag
                        :value="statusCfg().label"
                        :severity="statusCfg().severity"
                        :icon="statusCfg().icon"
                    />
                </div>
                <p class="text-gray-600 dark:text-gray-400 mt-1">
                    Session: {{ batch.academic_session?.name ?? '—' }}
                </p>
                <p v-if="batch.description" class="text-sm text-gray-500 mt-2 max-w-2xl">
                    {{ batch.description }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    label="Review Students"
                    icon="pi pi-list"
                    severity="info"
                    outlined
                    @click="router.visit(route('promotions.review', batch.id))"
                />
                <Button
                    v-if="can.approve"
                    label="Approve"
                    icon="pi pi-check"
                    severity="success"
                    @click="showApprove = true"
                />
                <Button
                    v-if="can.execute"
                    label="Execute"
                    icon="pi pi-play"
                    severity="warn"
                    @click="executeBatch"
                />
                <Button
                    v-if="can.cancel"
                    label="Cancel"
                    icon="pi pi-times"
                    severity="danger"
                    outlined
                    @click="showCancel = true"
                />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <Card class="shadow-none border border-gray-200 dark:border-gray-700">
                <template #content>
                    <div class="text-sm text-gray-500">Students</div>
                    <div class="text-2xl font-semibold tabular-nums mt-1">{{ batch.total_students }}</div>
                </template>
            </Card>
            <Card class="shadow-none border border-gray-200 dark:border-gray-700">
                <template #content>
                    <div class="text-sm text-gray-500">Processed</div>
                    <div class="text-2xl font-semibold tabular-nums mt-1">{{ batch.processed_students }}</div>
                </template>
            </Card>
            <Card class="shadow-none border border-gray-200 dark:border-gray-700">
                <template #content>
                    <div class="text-sm text-gray-500">Failed</div>
                    <div class="text-2xl font-semibold tabular-nums mt-1 text-red-600">
                        {{ batch.failed_students }}
                    </div>
                </template>
            </Card>
            <Card class="shadow-none border border-gray-200 dark:border-gray-700">
                <template #content>
                    <div class="text-sm text-gray-500 mb-2">Progress</div>
                    <ProgressBar :value="batch.progress_percentage" :showValue="true" style="height: 1.25rem" />
                </template>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <Card class="shadow-none border border-gray-200 dark:border-gray-700">
                <template #title>Initiated</template>
                <template #content>
                    <p class="font-medium">{{ batch.initiated_by?.name ?? '—' }}</p>
                    <p class="text-sm text-gray-500 mt-1">{{ fmt(batch.created_at) }}</p>
                </template>
            </Card>
            <Card class="shadow-none border border-gray-200 dark:border-gray-700">
                <template #title>Approved</template>
                <template #content>
                    <p class="font-medium">{{ batch.approved_by?.name ?? '—' }}</p>
                    <p class="text-sm text-gray-500 mt-1">{{ fmt(batch.approved_at) }}</p>
                </template>
            </Card>
            <Card class="shadow-none border border-gray-200 dark:border-gray-700">
                <template #title>Executed</template>
                <template #content>
                    <p class="font-medium">{{ batch.executed_by?.name ?? '—' }}</p>
                    <p class="text-sm text-gray-500 mt-1">{{ fmt(batch.executed_at) }}</p>
                </template>
            </Card>
        </div>

        <Dialog v-model:visible="showApprove" modal header="Approve Promotion Batch" :style="{ width: '28rem' }">
            <div class="flex flex-col gap-3 pt-2">
                <Message severity="info" :closable="false">
                    Approving unlocks execution. Overrides will no longer be allowed.
                </Message>
                <div>
                    <label class="block text-sm font-medium mb-1">Comments (optional)</label>
                    <Textarea v-model="approveForm.approval_comments" rows="3" class="w-full" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" text @click="showApprove = false" />
                <Button
                    label="Approve"
                    icon="pi pi-check"
                    severity="success"
                    :loading="approveForm.processing"
                    @click="submitApprove"
                />
            </template>
        </Dialog>

        <Dialog v-model:visible="showCancel" modal header="Cancel Promotion Batch" :style="{ width: '28rem' }">
            <div class="flex flex-col gap-3 pt-2">
                <Message severity="warn" :closable="false">
                    Cancelling stops this batch. Provide a clear reason for the audit trail.
                </Message>
                <div>
                    <label class="block text-sm font-medium mb-1">Reason</label>
                    <Textarea
                        v-model="cancelForm.reason"
                        rows="3"
                        class="w-full"
                        :invalid="!!cancelForm.errors.reason"
                    />
                    <small v-if="cancelForm.errors.reason" class="text-red-500">
                        {{ cancelForm.errors.reason }}
                    </small>
                </div>
            </div>
            <template #footer>
                <Button label="Back" severity="secondary" text @click="showCancel = false" />
                <Button
                    label="Cancel Batch"
                    icon="pi pi-times"
                    severity="danger"
                    :loading="cancelForm.processing"
                    @click="submitCancel"
                />
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
