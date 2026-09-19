<!--
  AddressManager — embedded + managed modes (Phase 4).

  Embedded: <AddressManager v-model="form.addresses" />
  Managed:  <AddressManager owner="school" :owner-id="school.id" />

  Progressive disclosure: 0 → inline form; 1 → inline + add; 2+ → list/cards.
-->
<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
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
        /** Controlled owner alias (managed mode). */
        owner?: string;
        ownerId?: string | number | null;
        /** Authorization hints for UI (backend still enforces). */
        canView?: boolean;
        canMutate?: boolean;
        /** Optional preloaded addresses (avoids duplicate fetch when parent supplies them). */
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

/** Embedded mode array contract. */
const model = defineModel<AddressInput[]>({ default: () => [] });

const isManaged = computed(() => Boolean(props.owner && props.ownerId));

const capability = isManaged.value
    ? useAddressCapability({
          owner: props.owner!,
          ownerId: computed(() => props.ownerId),
          canView: computed(() => props.canView),
          canMutate: computed(() => props.canMutate),
      })
    : null;

const localList = computed<AddressInput[]>({
    get() {
        if (isManaged.value && capability) {
            return capability.addresses.value.map((a) => ({
                ...addressToFormData(a),
                id: a.id,
            }));
        }
        return model.value ?? [];
    },
    set(v) {
        if (!isManaged.value) {
            model.value = v;
        }
    },
});

const displayList = computed(() => localList.value);
const count = computed(() => displayList.value.length);

const editingIndex = ref<number | null>(null);
const draft = ref<AddressFormData>(emptyAddressFormData());
const formErrors = ref<Partial<Record<keyof AddressFormData, string>>>({});
const saving = ref(false);
const serverError = ref<string | null>(null);
const showAddDialog = ref(false);
const confirm = useConfirm();

onMounted(async () => {
    if (isManaged.value && capability) {
        if (props.initialAddresses?.length) {
            capability.addresses.value = [...props.initialAddresses];
        } else if (props.canView) {
            try {
                await capability.list();
            } catch {
                /* error on capability.error */
            }
        }
    } else if (!model.value?.length) {
        // Zero addresses in embedded: seed one empty draft row for progressive UI
        model.value = [emptyAddressFormData()];
    }
});

watch(
    () => props.ownerId,
    async (id, prev) => {
        if (isManaged.value && capability && id && id !== prev && props.canView) {
            await capability.list();
        }
    }
);

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
        if (isManaged.value && capability) {
            if (editingIndex.value !== null) {
                const existing = capability.addresses.value[editingIndex.value];
                await capability.update(existing.id, { ...draft.value, is_primary: undefined as never });
            } else {
                await capability.create(draft.value);
            }
        } else {
            const next = [...(model.value ?? [])];
            if (editingIndex.value !== null) {
                next[editingIndex.value] = { ...draft.value, id: next[editingIndex.value]?.id };
            } else {
                // Replace sole empty seed or append
                if (next.length === 1 && !next[0].address_line_1) {
                    next[0] = { ...draft.value };
                } else {
                    next.push({ ...draft.value });
                }
            }
            model.value = next;
        }
        showAddDialog.value = false;
        editingIndex.value = null;
    } catch (e: unknown) {
        const ax = e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } };
        if (ax?.response?.data?.errors) {
            const mapped: Partial<Record<keyof AddressFormData, string>> = {};
            for (const [k, v] of Object.entries(ax.response.data.errors)) {
                mapped[k as keyof AddressFormData] = Array.isArray(v) ? v[0] : String(v);
            }
            formErrors.value = mapped;
        } else {
            serverError.value = ax?.response?.data?.message || (e as Error).message || 'Save failed';
        }
    } finally {
        saving.value = false;
    }
}

/** Embedded: save inline first address without dialog when count is 0/1. */
async function saveInline() {
    if (isManaged.value) return;
    const next = [...(model.value ?? [])];
    if (next.length === 0) {
        next.push({ ...draft.value });
    } else {
        next[0] = { ...draft.value, id: next[0]?.id };
    }
    model.value = next;
}

function removeAt(index: number) {
    const item = displayList.value[index];
    confirm.require({
        message: 'Delete this address? This cannot be undone.',
        header: 'Confirm delete',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            if (isManaged.value && capability && item.id) {
                try {
                    await capability.remove(String(item.id));
                } catch {
                    /* capability.error */
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
    if (!isManaged.value || !capability || !props.canMutate) return;
    const item = capability.addresses.value[index];
    if (!item) return;
    try {
        if (item.is_primary) {
            await capability.unsetPrimary(item.id);
        } else {
            await capability.setPrimary(item.id);
        }
    } catch {
        /* capability.error */
    }
}

const showAddAnother = computed(() => {
    if (isManaged.value) {
        return count.value >= 1 && props.canMutate;
    }
    // Embedded: after first non-empty address
    return count.value >= 1 && Boolean(displayList.value[0]?.address_line_1);
});

const useInlineZero = computed(() => !isManaged.value && count.value <= 1);
</script>

<template>
    <div class="address-manager space-y-4">
        <ConfirmDialog />

        <Message v-if="capability?.error?.value" severity="error" class="mb-2">
            {{ capability.error.value }}
        </Message>

        <div v-if="capability?.loading?.value" class="text-sm text-surface-500">Loading addresses…</div>

        <!-- Embedded: zero / one inline -->
        <template v-if="useInlineZero">
            <AddressForm
                v-model="draft"
                :errors="formErrors"
                :disabled="disabled"
                :show-primary="true"
            />
            <div class="flex gap-2">
                <Button
                    label="Apply address"
                    size="small"
                    :disabled="disabled || !draft.address_line_1"
                    @click="saveInline"
                />
            </div>
            <div v-if="displayList[0]?.address_line_1" class="text-sm text-surface-600">
                Current: {{ displayList[0].address_line_1 }}
                <Tag v-if="displayList[0].is_primary" value="Primary" severity="info" class="ml-2" />
            </div>
        </template>

        <!-- List / cards for 2+ or managed -->
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
                        v-if="canMutate || !isManaged"
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
                        v-if="canMutate || !isManaged"
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
