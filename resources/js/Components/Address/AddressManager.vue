<!--
  AddressManager — embedded + managed modes (Phase 4).

  Progressive disclosure (both modes):
    0 → inline empty AddressForm
    1 → inline populated AddressForm + “Add another address”
    2+ → cards/list; Add/Edit dialog

  Embedded: <AddressManager v-model="form.addresses" />
    - Form binds directly to model[0] (same object reference);
      parent submit reads the array (no Apply step).

  Managed:  <AddressManager owner="school" :owner-id="school.id" />
    - Inline single form is a local draft synced from capability; Save issues create/PATCH.
    - Cards use capability mutations (update / delete / setPrimary / unsetPrimary).
    - Managed count===1 exposes Set/Unset primary and Delete (capability endpoints).
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
        /** Preloaded addresses for managed mode (skips fetch when provided, including []). */
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

function ensureEmbeddedSlot() {
    if (isManaged.value) return;
    if (!model.value?.length) {
        model.value = [emptyAddressFormData()];
        return;
    }
    // Guarantee AddressFormData keys exist on the live slot (parent may pass {}).
    const slot = model.value[0] as AddressInput;
    if (slot.address_line_1 === undefined) {
        Object.assign(slot, emptyAddressFormData(), slot);
    }
}

// Keep one live slot for embedded inline form (0 → still one empty draft in the array).
watch(
    () => [isManaged.value, model.value?.length ?? 0] as const,
    () => {
        if (!isManaged.value) {
            ensureEmbeddedSlot();
        }
    },
    { immediate: true }
);

/**
 * Embedded 0/1: AddressForm must mutate the *same object* as model[0].
 * addressToFormData() clones — nested v-model fields would write the clone and
 * School submit would still see empty/stale addresses[]. Return the live slot.
 */
const singleEmbedded = computed({
    get(): AddressFormData {
        ensureEmbeddedSlot();
        const list = model.value ?? [];
        // Live reference: InputText/DynamicEnumField mutations write through to model[0].
        return list[0] as AddressFormData;
    },
    set(v: AddressFormData) {
        if (isManaged.value) return;
        ensureEmbeddedSlot();
        const slot = model.value![0] as AddressInput;
        // In-place assign keeps object identity so parent refs stay linked.
        Object.assign(slot, v, { id: slot.id });
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

onMounted(async () => {
    if (isManaged.value) {
        if (props.initialAddresses !== undefined) {
            // Parent already loaded this owner (including zero addresses) — skip fetch.
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
    serverError.value = null;
    formErrors.value = {};
    actionError.value = null;
    try {
        if (managedSingleId.value) {
            const { is_primary: _omit, ...payload } = managedSingle.value;
            await capability.update(managedSingleId.value, payload);
        } else {
            await capability.create(managedSingle.value);
        }
        await capability.list();
        syncManagedSingleFromCapability();
        managedSingleDirty.value = false;
    } catch (e) {
        if (!mapServerErrors(e)) {
            actionError.value = (e as Error).message || 'Save failed';
        }
    } finally {
        saving.value = false;
    }
}

async function toggleManagedSinglePrimary() {
    if (!isManaged.value || !props.canMutate || !managedSingleId.value) return;
    actionError.value = null;
    try {
        if (managedSingle.value.is_primary) {
            await capability.unsetPrimary(managedSingleId.value);
        } else {
            await capability.setPrimary(managedSingleId.value);
        }
        await capability.list();
        syncManagedSingleFromCapability();
    } catch (e) {
        actionError.value = (e as Error).message || 'Primary update failed';
    }
}

function deleteManagedSingle() {
    if (!isManaged.value || !props.canMutate || !managedSingleId.value) return;
    const id = managedSingleId.value;
    confirm.require({
        message: 'Delete this address?',
        header: 'Confirm',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            actionError.value = null;
            try {
                await capability.remove(id);
                await capability.list();
                syncManagedSingleFromCapability();
            } catch (e) {
                actionError.value = (e as Error).message || 'Delete failed';
            }
        },
    });
}

function startEdit(index: number) {
    const item = displayList.value[index];
    if (!item) return;
    editingIndex.value = index;
    draft.value = addressToFormData(item);
    formErrors.value = {};
    serverError.value = null;
    showAddDialog.value = true;
}

function startAdd() {
    editingIndex.value = null;
    draft.value = emptyAddressFormData();
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
    serverError.value = null;
    formErrors.value = {};
    actionError.value = null;
    try {
        if (isManaged.value) {
            if (editingIndex.value !== null) {
                const existing = capability.addresses.value[editingIndex.value];
                if (!existing) throw new Error('Address not found');
                const { is_primary: _omit, ...payload } = draft.value;
                await capability.update(String(existing.id), payload);
            } else {
                await capability.create(draft.value);
            }
            await capability.list();
            syncManagedSingleFromCapability();
        } else {
            const list = [...(model.value ?? [])];
            if (editingIndex.value !== null) {
                list[editingIndex.value] = {
                    ...draft.value,
                    id: list[editingIndex.value]?.id,
                };
            } else {
                list.push({ ...draft.value });
            }
            model.value = list;
        }
        showAddDialog.value = false;
        editingIndex.value = null;
    } catch (e) {
        if (!mapServerErrors(e)) {
            serverError.value = (e as Error).message || 'Save failed';
        }
    } finally {
        saving.value = false;
    }
}

function removeAt(index: number) {
    if (isManaged.value) {
        const existing = capability.addresses.value[index];
        if (!existing) return;
        confirm.require({
            message: 'Delete this address?',
            header: 'Confirm',
            icon: 'pi pi-exclamation-triangle',
            acceptClass: 'p-button-danger',
            accept: async () => {
                actionError.value = null;
                try {
                    await capability.remove(String(existing.id));
                    await capability.list();
                    syncManagedSingleFromCapability();
                } catch (e) {
                    actionError.value = (e as Error).message || 'Delete failed';
                }
            },
        });
        return;
    }
    const list = [...(model.value ?? [])];
    list.splice(index, 1);
    model.value = list.length ? list : [emptyAddressFormData()];
}

async function togglePrimary(index: number) {
    if (!isManaged.value || !props.canMutate) return;
    const existing = capability.addresses.value[index];
    if (!existing) return;
    actionError.value = null;
    try {
        if (existing.is_primary) {
            await capability.unsetPrimary(String(existing.id));
        } else {
            await capability.setPrimary(String(existing.id));
        }
        await capability.list();
    } catch (e) {
        actionError.value = (e as Error).message || 'Primary update failed';
    }
}

const showAddAnother = computed(() => {
    if (disabledOrViewOnly.value) return false;
    if (useInlineSingle.value) return count.value === 1;
    return true;
});

const disabledOrViewOnly = computed(() => props.disabled || !props.canMutate);

const canMutate = computed(() => props.canMutate && !props.disabled);
const disabled = computed(() => props.disabled);
</script>

<template>
    <div class="space-y-4">
        <Message v-if="capability.error.value || actionError" severity="error" class="mb-2">
            {{ capability.error.value || actionError }}
        </Message>

        <template v-if="useInlineSingle">
            <!-- Embedded: live v-model into parent addresses[0] -->
            <template v-if="!isManaged">
                <AddressForm
                    v-model="singleEmbedded"
                    :disabled="disabled"
                    :show-primary="true"
                />
            </template>
            <!-- Managed: local draft + explicit Save / primary / delete -->
            <template v-else>
                <AddressForm
                    v-model="managedSingle"
                    :errors="formErrors"
                    :disabled="saving || disabled"
                    :show-primary="false"
                />
                <div v-if="canMutate" class="flex flex-wrap gap-2 mt-3">
                    <Button
                        :label="managedSingleId ? 'Save changes' : 'Save address'"
                        size="small"
                        :loading="saving"
                        :disabled="disabled || !managedSingle.address_line_1 || !canMutate"
                        @click="saveManagedSingle"
                    />
                    <Button
                        v-if="managedSingleId && canMutate && !managedSingle.is_primary"
                        label="Set primary"
                        size="small"
                        severity="secondary"
                        outlined
                        :disabled="disabled"
                        @click="toggleManagedSinglePrimary"
                    />
                    <Button
                        v-if="managedSingleId && canMutate && managedSingle.is_primary"
                        label="Unset primary"
                        size="small"
                        severity="secondary"
                        outlined
                        :disabled="disabled"
                        @click="toggleManagedSinglePrimary"
                    />
                    <Button
                        v-if="managedSingleId && canMutate"
                        label="Delete"
                        size="small"
                        severity="danger"
                        outlined
                        :disabled="disabled"
                        @click="deleteManagedSingle"
                    />
                    <Tag
                        v-if="managedSingleId && managedSingle.is_primary"
                        value="Primary"
                        severity="success"
                        class="self-center"
                    />
                </div>
            </template>
        </template>

        <template v-else>
            <div
                v-for="(item, index) in displayList"
                :key="item.id ?? index"
                class="flex flex-wrap items-start justify-between gap-3 p-3 border border-surface-200 dark:border-surface-700 rounded-lg"
            >
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium truncate">{{ item.address_line_1 || 'Address' }}</span>
                        <Tag v-if="item.is_primary" value="Primary" severity="success" />
                        <Tag v-if="item.type" :value="String(item.type)" severity="secondary" />
                    </div>
                    <p v-if="item.address_line_2" class="text-sm text-surface-600 dark:text-surface-400">
                        {{ item.address_line_2 }}
                    </p>
                    <p v-if="item.landmark || item.postal_code" class="text-sm text-surface-500">
                        <span v-if="item.landmark">{{ item.landmark }}</span>
                        <span v-if="item.landmark && item.postal_code"> · </span>
                        <span v-if="item.postal_code">{{ item.postal_code }}</span>
                    </p>
                </div>
                <div v-if="canMutate" class="flex flex-wrap gap-2 shrink-0">
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

        <ConfirmDialog />
    </div>
</template>
