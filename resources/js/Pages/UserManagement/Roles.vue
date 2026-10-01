<script setup lang="ts">
/**
 * Roles management index — Permission Phase 5.
 * Displays effective role catalogue; create/edit/lifecycle/permissions via dialogs.
 * Frontend does not compute inheritance; origin metadata comes from the backend.
 */
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdvancedDataTable from '@/Components/datatable/AdvancedDataTable.vue';
import PermissionSelector from '@/Components/permissions/PermissionSelector.vue';
import type { PermissionGroup } from '@/Components/permissions/PermissionSelector.vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { BulkAction, ColumnDefinition, TableAction } from '@/types/datatables';

type EffectiveRoleRow = {
    id: string;
    role_id?: string;
    name: string;
    display_name: string | null;
    description: string | null;
    disabled: boolean;
    origin: 'tenant' | 'local';
    is_inherited: boolean;
    is_local: boolean;
    permissions_count?: number;
    presentation_name?: string;
};

const props = defineProps<{
    columns: ColumnDefinition<any>[];
    data: EffectiveRoleRow[];
    meta?: {
        currentPage: number;
        perPage: number;
        total: number;
        lastPage: number;
    };
    capabilities?: {
        can_manage?: boolean;
    };
}>();

const toast = useToast();
const confirm = useConfirm();

const canManage = computed(() => props.capabilities?.can_manage !== false);

const initialTableResponse = computed(() => {
    if (!props.meta || !props.columns?.length) return null;
    return { data: props.data ?? [], columns: props.columns as any, meta: props.meta };
});

const enhancedColumns = computed(() => [...(props.columns || [])]);

const formVisible = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const formSaving = ref(false);
const formErrors = ref<Record<string, string>>({});
const formTarget = ref<EffectiveRoleRow | null>(null);
const form = reactive({
    name: '',
    display_name: '',
    description: '',
});

function openCreate() {
    formMode.value = 'create';
    formTarget.value = null;
    form.name = '';
    form.display_name = '';
    form.description = '';
    formErrors.value = {};
    formVisible.value = true;
}

function openEdit(row: EffectiveRoleRow) {
    formMode.value = 'edit';
    formTarget.value = row;
    form.name = row.name;
    form.display_name = row.display_name ?? row.presentation_name ?? '';
    form.description = row.description ?? '';
    formErrors.value = {};
    formVisible.value = true;
}

async function submitForm() {
    formSaving.value = true;
    formErrors.value = {};
    try {
        if (formMode.value === 'create') {
            const payload: Record<string, unknown> = {
                display_name: form.display_name,
                description: form.description || null,
            };
            if (form.name.trim()) {
                payload.name = form.name.trim();
            }
            await axios.post(route('admin.roles.store'), payload);
            toast.add({ severity: 'success', summary: 'Role created', life: 3000 });
        } else if (formTarget.value) {
            await axios.put(route('admin.roles.update', formTarget.value.id), {
                display_name: form.display_name,
                description: form.description || null,
            });
            toast.add({ severity: 'success', summary: 'Role updated', life: 3000 });
        }
        formVisible.value = false;
        router.reload({ only: ['data', 'meta', 'columns'] });
    } catch (e: any) {
        const errors = e?.response?.data?.errors;
        if (errors) {
            formErrors.value = Object.fromEntries(
                Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : String(v)]),
            );
        } else {
            toast.add({
                severity: 'error',
                summary: 'Save failed',
                detail: e?.response?.data?.message ?? 'Unexpected error',
                life: 5000,
            });
        }
    } finally {
        formSaving.value = false;
    }
}

function confirmToggleStatus(row: EffectiveRoleRow) {
    const enabling = row.disabled;
    confirm.require({
        header: enabling ? 'Enable role' : 'Disable role',
        message: enabling
            ? `Enable “${row.display_name || row.name}”? It will become available for assignment again.`
            : `Disable “${row.display_name || row.name}”? Existing assignments remain, but the role will no longer be assignable.`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: enabling ? 'Enable' : 'Disable',
        acceptClass: enabling ? 'p-button-success' : 'p-button-warning',
        accept: async () => {
            try {
                await axios.patch(route('admin.roles.status', row.id), {
                    disabled: !enabling,
                });
                toast.add({
                    severity: 'success',
                    summary: enabling ? 'Role enabled' : 'Role disabled',
                    life: 3000,
                });
                router.reload({ only: ['data', 'meta'] });
            } catch (e: any) {
                toast.add({
                    severity: 'error',
                    summary: 'Status update failed',
                    detail: e?.response?.data?.message ?? e?.response?.data?.errors?.role?.[0] ?? 'Unexpected error',
                    life: 6000,
                });
            }
        },
    });
}

function confirmDelete(row: EffectiveRoleRow) {
    if (row.is_inherited) {
        confirm.require({
            header: 'Cannot delete tenant role',
            message:
                'This role belongs to your tenant and cannot be deleted from this school. You can disable it for this school or contact your tenant administrator to remove the tenant role.',
            icon: 'pi pi-info-circle',
            acceptLabel: 'OK',
            rejectClass: 'hidden',
            accept: () => {},
        });
        return;
    }

    const header = 'Delete or reset role';
    const message = `Remove “${row.display_name || row.name}”? If this is a customized school version of a tenant role, the school will use the tenant role again. Roles with assignments cannot be deleted.`;

    confirm.require({
        header,
        message,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Confirm',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(route('admin.roles.destroy', row.id));
                toast.add({ severity: 'success', summary: 'Role removed', life: 3000 });
                router.reload({ only: ['data', 'meta'] });
            } catch (e: any) {
                toast.add({
                    severity: 'error',
                    summary: 'Delete failed',
                    detail:
                        e?.response?.data?.message ??
                        e?.response?.data?.errors?.role?.[0] ??
                        'Unexpected error',
                    life: 7000,
                });
            }
        },
    });
}

const permVisible = ref(false);
const permLoading = ref(false);
const permSaving = ref(false);
const permRole = ref<EffectiveRoleRow | null>(null);
const permGroups = ref<PermissionGroup[]>([]);
const selectedPermissionIds = ref<Array<number | string>>([]);

async function openPermissions(row: EffectiveRoleRow) {
    permRole.value = row;
    permVisible.value = true;
    permLoading.value = true;
    permGroups.value = [];
    selectedPermissionIds.value = [];
    try {
        const { data } = await axios.get(route('admin.roles.permissions.show', row.id));
        permGroups.value = data.permission_groups ?? [];
        selectedPermissionIds.value = data.assigned_permission_ids ?? [];
    } catch (e: any) {
        toast.add({
            severity: 'error',
            summary: 'Failed to load permissions',
            detail: e?.response?.data?.message ?? 'Unexpected error',
            life: 5000,
        });
        permVisible.value = false;
    } finally {
        permLoading.value = false;
    }
}

async function savePermissions() {
    if (!permRole.value) return;
    permSaving.value = true;
    try {
        await axios.put(route('admin.roles.permissions.update', permRole.value.id), {
            permission_ids: selectedPermissionIds.value,
        });
        toast.add({ severity: 'success', summary: 'Permissions updated', life: 3000 });
        permVisible.value = false;
        router.reload({ only: ['data', 'meta'] });
    } catch (e: any) {
        toast.add({
            severity: 'error',
            summary: 'Failed to save permissions',
            detail:
                e?.response?.data?.message ??
                e?.response?.data?.errors?.permission_ids?.[0] ??
                'Unexpected error',
            life: 6000,
        });
    } finally {
        permSaving.value = false;
    }
}

const roleActions = computed<TableAction<any>[]>(() => {
    if (!canManage.value) return [];
    return [
        {
            label: 'Edit',
            icon: 'pi pi-pencil',
            handler: (row) => openEdit(row),
        },
        {
            label: 'Manage Permissions',
            icon: 'pi pi-shield',
            handler: (row) => openPermissions(row),
        },
        {
            label: 'Enable',
            icon: 'pi pi-check-circle',
            handler: (row) => confirmToggleStatus(row),
            visible: (row: EffectiveRoleRow) => !!row.disabled,
        },
        {
            label: 'Disable',
            icon: 'pi pi-ban',
            handler: (row) => confirmToggleStatus(row),
            visible: (row: EffectiveRoleRow) => !row.disabled,
        },
        {
            label: 'Delete',
            icon: 'pi pi-trash',
            severity: 'danger',
            handler: (row) => confirmDelete(row),
        },
    ];
});

const bulkActions = computed<BulkAction[]>(() => {
    if (!canManage.value) return [];
    return [
        {
            label: 'Enable Selected',
            icon: 'pi pi-check-circle',
            severity: 'success',
            action: 'enable',
            confirm: {
                message: (rows) =>
                    `Enable ${rows.length} selected role(s)? They will become available for assignment again.`,
                header: 'Enable roles',
                acceptLabel: 'Enable',
                acceptClass: 'p-button-success',
            },
            handler: async (rows) => {
                try {
                    const { data } = await axios.post(route('admin.roles.status.bulk'), {
                        ids: rows.map((r) => r.id),
                        disabled: false,
                    });
                    toast.add({
                        severity: data.failed ? 'warn' : 'success',
                        summary: data.message ?? 'Roles enabled',
                        life: 4000,
                    });
                    router.reload({ only: ['data', 'meta'] });
                } catch (e: any) {
                    toast.add({
                        severity: 'error',
                        summary: 'Bulk enable failed',
                        detail: e?.response?.data?.message ?? 'Unexpected error',
                        life: 6000,
                    });
                }
            },
        },
        {
            label: 'Disable Selected',
            icon: 'pi pi-ban',
            severity: 'warn',
            action: 'disable',
            confirm: {
                message: (rows) =>
                    `Disable ${rows.length} selected role(s)? Existing assignments remain, but the roles will no longer be assignable.`,
                header: 'Disable roles',
                acceptLabel: 'Disable',
                acceptClass: 'p-button-warning',
            },
            handler: async (rows) => {
                try {
                    const { data } = await axios.post(route('admin.roles.status.bulk'), {
                        ids: rows.map((r) => r.id),
                        disabled: true,
                    });
                    toast.add({
                        severity: data.failed ? 'warn' : 'success',
                        summary: data.message ?? 'Roles disabled',
                        life: 4000,
                    });
                    router.reload({ only: ['data', 'meta'] });
                } catch (e: any) {
                    toast.add({
                        severity: 'error',
                        summary: 'Bulk disable failed',
                        detail: e?.response?.data?.message ?? 'Unexpected error',
                        life: 6000,
                    });
                }
            },
        },
    ];
});
</script>

<template>
    <AuthenticatedLayout
        title="Roles Management"
        :crumb="[{ label: 'User Management' }, { label: 'Roles' }]"
        :buttons="
            canManage
                ? [
                      {
                          label: 'Create New Role',
                          icon: 'pi pi-plus',
                          severity: 'primary',
                          onClick: openCreate,
                      },
                  ]
                : []
        "
    >
        <div class="space-y-4">
            <p class="text-sm text-surface-500">
                Effective roles for the current authorization context. Editing an inherited tenant role
                automatically creates a school-local customization.
            </p>

            <AdvancedDataTable
                :actions="roleActions"
                endpoint="/admin/roles"
                :columns="enhancedColumns"
                :initial-response="initialTableResponse"
                :bulk-actions="bulkActions"
            />
        </div>

        <Dialog
            v-model:visible="formVisible"
            modal
            :header="formMode === 'create' ? 'Create Role' : `Edit ${formTarget?.display_name || formTarget?.name || 'Role'}`"
            class="w-full max-w-lg"
            :closable="!formSaving"
        >
            <div class="space-y-4 pt-2">
                <div v-if="formMode === 'edit'" class="space-y-1">
                    <label class="text-sm font-medium">Technical name</label>
                    <InputText :model-value="form.name" disabled class="w-full font-mono" />
                    <p class="text-xs text-surface-500">
                        This is the role's permanent technical name and cannot be changed. Change the
                        Display Name instead if you want to change how this role is presented.
                    </p>
                </div>

                <div v-else class="space-y-1">
                    <label class="text-sm font-medium">Technical name (optional)</label>
                    <InputText
                        v-model="form.name"
                        class="w-full font-mono"
                        placeholder="senior_teacher"
                        :invalid="!!formErrors.name"
                    />
                    <p class="text-xs text-surface-500">
                        Permanent technical identifier. If omitted, it is derived from the display name
                        (e.g. “Senior Teacher” → senior_teacher).
                    </p>
                    <small v-if="formErrors.name" class="text-red-500">{{ formErrors.name }}</small>
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium">Display name <span class="text-red-500">*</span></label>
                    <InputText
                        v-model="form.display_name"
                        class="w-full"
                        placeholder="Senior Teacher"
                        :invalid="!!formErrors.display_name"
                    />
                    <small v-if="formErrors.display_name" class="text-red-500">{{ formErrors.display_name }}</small>
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium">Description</label>
                    <Textarea v-model="form.description" class="w-full" rows="3" auto-resize />
                    <small v-if="formErrors.description" class="text-red-500">{{ formErrors.description }}</small>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" text severity="secondary" :disabled="formSaving" @click="formVisible = false" />
                <Button
                    :label="formMode === 'create' ? 'Create' : 'Save'"
                    :loading="formSaving"
                    @click="submitForm"
                />
            </template>
        </Dialog>

        <Dialog
            v-model:visible="permVisible"
            modal
            :header="`Permissions — ${permRole?.display_name || permRole?.name || ''}`"
            class="w-full max-w-3xl"
            :closable="!permSaving"
        >
            <div v-if="permLoading" class="py-12 text-center text-surface-500">Loading permissions…</div>
            <PermissionSelector
                v-else
                v-model="selectedPermissionIds"
                :groups="permGroups"
                :disabled="permSaving"
            />
            <template #footer>
                <Button label="Cancel" text severity="secondary" :disabled="permSaving" @click="permVisible = false" />
                <Button label="Save permissions" :loading="permSaving" :disabled="permLoading" @click="savePermissions" />
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
