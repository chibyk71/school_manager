<!--
  AddressManager — embedded + managed modes (Phase 4).

  Progressive disclosure (both modes):
    0 → inline empty AddressForm
    1 → inline populated AddressForm + “Add another address”
    2+ → cards/list; Add/Edit dialog

  Embedded: <AddressManager v-model="form.addresses" />
    - Form binds directly to model[0]; parent submit reads the array (no Apply step).

  Managed:  <AddressManager owner="school" :owner-id="school.id" />
    - Inline single form is a local draft synced from capability; Save issues create/PATCH.
    - Cards use capability mutations (update / delete / setPrimary / unsetPrimary).
-->
<script setup lang="ts">
import { ref, computed, watch, onMounted, toRef } from 'vue';
import { Button, Tag, Message, Dialog, ConfirmDialog } from 'primevue';
import { useConfirm } from 'primevue/useconfirm';
import AddressForm from './AddressForm.vue';
import {
    type Address,
    type AddressFormData,
    type AddressInput,
    emptyAddressFormData,
    addressToFormData,
} from '@/types/address';
import { useAddressCapability } from '@/composables/useAddressCapability';

const props = withDefaults(
    defineProps<{
        owner?: string;
        ownerId?: string | number | null;
        canView?: boolean;
        canMutate?: boolean;
        /** Preloaded addresses for managed mode (skips fetch when provided). */
        initialAddresses?: Address[];
        disabled?: boolean;
    }>(),
    {
        canView: true,
        canMutate: true,
        initialAddresses: undefined,
        disabled: false,
    }
);

/** Embedded mode: parent owns the array. */
const model = defineModel<AddressInput[]>({ default: () => [] });

const isManaged = computed(() => Boolean(props.owner && props.ownerId));

/**
 * Capability for managed persistence. Instantiated always for simpler composition;
 * embedded mode never calls its mutation helpers.
 */
const capability = useAddressCapability({
    owner: props.owner ?? '',
    ownerId: toRef(props, 'ownerId'),
    canView: toRef(props, 'canView'),
    canMutate: toRef(props, 'canMutate'),
});

const displayList = computed<AddressInput[]>(() => {
    if (isManaged.value) {
        return capability.addresses.value.map((a) => ({
            ...addressToFormData(a),
            id: a.id,
        }));
    }
    return model.value ?? [];
});

const count = computed(() => displayList.value.length);

/** Shared progressive-disclosure rule: 0 or 1 → inline form; 2+ → cards. */
const useInlineSingle = computed(() => count.value <= 1);

/** Embedded 0/1: bind form directly to model[0] so School submit sees live form state. */
const singleEmbedded = computed({
    get(): AddressFormData {
        const list = model.value ?? [];
        if (list.length === 0) {
            return emptyAddressFormData();
        }
        return addressToFormData(list[0]);
    },
    set(v: AddressFormData) {
        const list = [...(model.value ?? [])];
        if (list.length === 0) {
            model.value = [{ ...v }];
        } else {
            list[0] = { ...v, id: list[0].id };
            model.value = list;
        }
    },
});

/**
 * Managed 0/1: local draft synced from capability — never mutates embedded model.
 * Save issues create (0) or PATCH (1) through the capability.
 */
const managedSingle = ref<AddressFormData>(emptyAddressFormData());
const managedSingleId = ref<string | null>(null);
const managedSingleDirty = ref(false);

function syncManagedSingleFromCapability() {
    if (!isManaged.value) return;
    const addrs = capability.addresses.value;
    if (addrs.length === 1) {
        managedSingle.value = addressToFormData(addrs[0]);
        managedSingleId.value = String(addrs[0].id);
        managedSingleDirty.value = false;
    } else if (addrs.length === 0) {
        managedSingle.value = emptyAddressFormData();
        managedSingleId.value = null;
        managedSingleDirty.value = false;
    }
    // length >= 2: cards mode — leave draft alone
}

watch(
    () => capability.addresses.value,
    () => syncManagedSingleFromCapability(),
    { deep: true }
);

watch(managedSingle, () => {
    if (isManaged.value && useInlineSingle.value) {
        managedSingleDirty.value = true;
    }
}, { deep: true });

function ensureEmbeddedSlot() {
    if (isManaged.value) return;
    if (!model.value?.length) {
        model.value = [emptyAddressFormData()];
    }
}

onMounted(async () => {
    if (isManaged.value) {
        if (props.initialAddresses?.length) {
            capability.addresses.value = [...props.initialAddresses];
            syncManagedSingleFromCapability();
        } else if (props.canView && props.ownerId) {
            try {
                await capability.list();
                syncManagedSingleFromCapability();
            } catch {
                /* capability.error holds message */
            }
        }
    } else {
        ensureEmbeddedSlot();
    }
});

watch(
    () => props.ownerId,
    async (id, prev) => {
        if (isManaged.value && id && id !== prev && props.canView) {
            try {
                await capability.list();
                syncManagedSingleFromCapability();
            } catch {
                /* capability.error */
            }
        }
    }
);

// Dialog state (add / edit for 2+)
const editingIndex = ref<number | null>(null);
const draft = ref<AddressFormData>(emptyAddressFormData());
const formErrors = ref<Partial<Record<keyof AddressFormData, string>>>({});
const saving = ref(false);
const serverError = ref<string | null>(null);
const showAddDialog = ref(false);
const actionError = ref<string | null>(null);
const confirm = useConfirm();

function mapServerErrors(e: unknown): boolean {
    const ax = e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } };
    if (ax?.response?.data?.errors) {
        const mapped: Partial<Record<keyof AddressFormData, string>> = {};
        for (const [k, v] of Object.entries(ax.response.data.errors)) {
            mapped[k as keyof AddressFormData] = Array.isArray(v) ? v[0] : String(v);
        }
        formErrors.value = mapped;
        return true;
    }
    serverError.value =
        ax?.response?.data?.message || (e as Error).message || 'Save failed';
    return false;
}

/** Managed inline Save: create when none, PATCH when one exists. */
async function saveManagedSingle() {
    if (!isManaged.value || !props.canMutate) return;
    saving.value = true;
    formErrors.value = {};
    serverError.value = null;
    actionError.value = null;
    try {
        if (managedSingleId.value) {
            const { is_primary: _omit, ...payload } = managedSingle.value;
            await capability.update(managedSingleId.value, payload);
        } else {
            await capability.create(managedSingle.value);
        }
        syncManagedSingleFromCapability();
        managedSingleDirty.value = false;
    } catch (e: unknown) {
        mapServerErrors(e);
        actionError.value = serverError.value || capability.error.value || 'Save failed';
    } finally {
        saving.value = false;
    }
}

function startEdit(index: number) {
    const item = displayList.value[index];
    draft.value = addressToFormData(item);
    editingIndex.value = index;
    formErrors.value = {};
    serverError.value = null;
    showAddDialog.value = true;
}

function startAdd() {
    draft.value = emptyAddressFormData();
    editingIndex.value = null;
    formErrors.value = {};
    serverError.value = null;
    showAddDialog.value = true;
}

function cancelEdit() {
    showAddDialog.value = false;
    editingIndex.value = null;
}

async function saveDraft() {
    saving.value = true;
    formErrors.value = {};
    serverError.value = null;
    try {
        if (isManaged.value) {
            if (editingIndex.value !== null) {
                const existing = capability.addresses.value[editingIndex.value];
                const { is_primary: _omit, ...payload } = draft.value;
                await capability.update(existing.id, payload);
            } else {
                await capability.create(draft.value);
            }
            syncManagedSingleFromCapability();
        } else {
            const next = [...(model.value ?? [])];
            if (editingIndex.value !== null) {
                next[editingIndex.value] = {
                    ...draft.value,
                    id: next[editingIndex.value]?.id,
                };
            } else {
                next.push({ ...draft.value });
            }
            model.value = next;
        }
        showAddDialog.value = false;
        editingIndex.value = null;
    } catch (e: unknown) {
        mapServerErrors(e);
    } finally {
        saving.value = false;
    }
}

function removeAt(index: number) {
    const item = displayList.value[index];
    confirm.require({
        message: 'Delete this address? This cannot be undone.',
        header: 'Confirm delete',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            actionError.value = null;
            if (isManaged.value && item.id) {
                try {
                    await capability.remove(String(item.id));
                    syncManagedSingleFromCapability();
                } catch (e: unknown) {
                    actionError.value =
                        capability.error.value || (e as Error).message || 'Delete failed';
                }
            } else {
                const next = [...(model.value ?? [])];
                next.splice(index, 1);
                model.value = next.length ? next : [emptyAddressFormData()];
            }
        },
    });
}

async function togglePrimary(index: number) {
    if (!isManaged.value || !props.canMutate) return;
    const item = capability.addresses.value[index];
    if (!item) return;
    actionError.value = null;
    try {
        if (item.is_primary) {
            await capability.unsetPrimary(item.id);
        } else {
            await capability.setPrimary(item.id);
        }
        syncManagedSingleFromCapability();
    } catch (e: unknown) {
        actionError.value =
            capability.error.value || (e as Error).message || 'Primary update failed';
    }
}

const showAddAnother = computed(() => {
    if (isManaged.value) {
        return count.value >= 1 && props.canMutate;
    }
    return count.value >= 1 && Boolean(displayList.value[0]?.address_line_1);
});
</script>

<template>
    <div class="address-manager space-y-4">
        <ConfirmDialog />

        <Message v-if="isManaged && capability.error.value" severity="error" class="mb-2">
            {{ capability.error.value }}
        </Message>
        <Message v-if="actionError" severity="error" class="mb-2">
            {{ actionError }}
        </Message>

        <div v-if="isManaged && capability.loading.value" class="text-sm text-surface-500">
            Loading addresses…
        </div>

        <!-- 0 or 1: inline form (both modes) -->
        <template v-if="useInlineSingle">
            <!-- Embedded: live bind to parent model -->
            <template v-if="!isManaged">
                <AddressForm
                    v-model="singleEmbedded"
                    :disabled="disabled"
                    :show-primary="true"
                />
            </template>
            <!-- Managed: local draft; Save → create or PATCH -->
            <template v-else>
                <AddressForm
                    v-model="managedSingle"
                    :errors="formErrors"
                    :disabled="disabled || saving"
                    :show-primary="count === 0"
                />
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        :label="managedSingleId ? 'Save changes' : 'Save address'"
                        size="small"
                        :loading="saving"
                        :disabled="disabled || !managedSingle.address_line_1 || !canMutate"
                        @click="saveManagedSingle"
                    />
                    <span
                        v-if="managedSingleId && managedSingle.is_primary"
                        class="text-sm text-surface-500"
                    >
                        <Tag value="Primary" severity="info" />
                    </span>
                </div>
            </template>
        </template>

        <!-- 2+: cards -->
        <template v-else>
            <div
                v-for="(item, index) in displayList"
                :key="item.id ?? index"
                class="border border-surface-200 rounded-lg p-4 flex flex-col md:flex-row md:items-start md:justify-between gap-3"
            >
                <div class="min-w-0 flex-1">
                    <div class="font-medium truncate">
                        {{ item.address_line_1 || 'Untitled address' }}
                        <Tag v-if="item.is_primary" value="Primary" severity="info" class="ml-2" />
                    </div>
                    <div class="text-sm text-surface-500 mt-1">
                        {{ [item.city_text, item.postal_code, item.type].filter(Boolean).join(' · ') }}
                    </div>
                </div>
                <div v-if="canMutate || !isManaged" class="flex flex-wrap gap-2 shrink-0">
                    <Button
                        label="Edit"
                        size="small"
                        severity="secondary"
                        text
                        :disabled="disabled"
                        @click="startEdit(index)"
                    />
                    <Button
                        v-if="isManaged && canMutate && !item.is_primary"
                        label="Set primary"
                        size="small"
                        severity="secondary"
                        text
                        :disabled="disabled"
                        @click="togglePrimary(index)"
                    />
                    <Button
                        v-if="isManaged && canMutate && item.is_primary"
                        label="Unset primary"
                        size="small"
                        severity="secondary"
                        text
                        :disabled="disabled"
                        @click="togglePrimary(index)"
                    />
                    <Button
                        label="Delete"
                        size="small"
                        severity="danger"
                        text
                        :disabled="disabled"
                        @click="removeAt(index)"
                    />
                </div>
            </div>
        </template>

        <Button
            v-if="showAddAnother"
            label="Add another address"
            icon="pi pi-plus"
            size="small"
            outlined
            :disabled="disabled"
            @click="startAdd"
        />

        <Dialog
            v-model:visible="showAddDialog"
            :header="editingIndex !== null ? 'Edit address' : 'Add address'"
            modal
            class="w-full max-w-lg"
            :closable="!saving"
        >
            <Message v-if="serverError" severity="error" class="mb-3">{{ serverError }}</Message>
            <AddressForm
                v-model="draft"
                :errors="formErrors"
                :disabled="saving || disabled"
                :show-primary="!isManaged"
            />
            <template #footer>
                <Button label="Cancel" severity="secondary" text :disabled="saving" @click="cancelEdit" />
                <Button label="Save" :loading="saving" @click="saveDraft" />
            </template>
        </Dialog>
    </div>
</template>
