<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Message from 'primevue/message';

interface FolderRow {
    id: string;
    name: string;
    parent_id: string | null;
    module_code: string | null;
    default_storage_provider_id: string | null;
    capabilities: string[];
}

interface FileRow {
    id: string;
    original_filename: string;
    mime_type: string | null;
    size_bytes: number;
    managed_by: string;
    folder_id: string | null;
    storage_provider_id: string;
    trashed_at?: string | null;
    purge_eligible_at?: string | null;
    is_trashed?: boolean;
    has_attachments?: boolean;
    original_folder_id?: string | null;
}

interface ProviderRow {
    id: string;
    name: string;
    driver: string;
    enabled: boolean;
}

const props = defineProps<{
    currentFolder: FolderRow | null;
    folders: FolderRow[];
    files: FileRow[];
    providers: ProviderRow[];
    modules: { code: string; label: string }[];
    canManage: boolean;
    canAttach: boolean;
    canConfigure: boolean;
    activeSchoolId: string | null;
    /** Optional server-provided trail; mapped to AuthenticatedLayout's `crumb` contract. */
    crumbs?: { label: string; href?: string }[];
}>();

const showCreateFolder = ref(false);
const showUpload = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const showTrash = ref(false);
const trashFiles = ref<FileRow[]>([]);
const trashLoading = ref(false);
const showRestoreDialog = ref(false);
const restoreTargetId = ref<string | null>(null);
const restoreTargetName = ref('');
const restoreFolderId = ref<string | null>(null);
const restoreNeedsAlternative = ref(false);
const restoreSubmitting = ref(false);



/**
 * AuthenticatedLayout requires `crumb: { label?, url?, icon? }[]`.
 * Prefer server crumbs (href → url); otherwise a minimal File Manager trail.
 */
const layoutCrumb = computed(() => {
    if (props.crumbs && props.crumbs.length > 0) {
        return props.crumbs.map((c) => ({
            label: c.label,
            url: c.href,
        }));
    }

    const items: { label: string; url?: string }[] = [
        { label: 'File Manager', url: route('file-manager.browser') },
    ];
    if (props.currentFolder) {
        items.push({ label: props.currentFolder.name });
    }

    return items;
});

const folderForm = useForm({
    name: '',
    parent_id: props.currentFolder?.id ?? null as string | null,
    module_code: null as string | null,
    default_storage_provider_id: null as string | null,
});

const uploadForm = useForm({
    file: null as File | null,
    folder_id: props.currentFolder?.id ?? null as string | null,
    storage_provider_id: null as string | null,
});

const providerOptions = computed(() =>
    props.providers.filter((p) => p.enabled).map((p) => ({ label: `${p.name} (${p.driver})`, value: p.id })),
);

function openFolder(id: string) {
    router.get(route('file-manager.browser'), { folder: id }, { preserveState: true });
}

function goRoot() {
    router.get(route('file-manager.browser'));
}

function createFolder() {
    errorMessage.value = null;
    folderForm.post(route('file-manager.folders.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateFolder.value = false;
            folderForm.reset();
            successMessage.value = 'Folder created.';
            router.reload({ only: ['folders', 'files', 'currentFolder'] });
        },
        onError: (errors) => {
            errorMessage.value = Object.values(errors).flat().join(' ') || 'Unable to create folder.';
        },
    });
}

function onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    uploadForm.file = input.files?.[0] ?? null;
}

function uploadFile() {
    errorMessage.value = null;
    uploadForm.post(route('file-manager.files.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showUpload.value = false;
            uploadForm.reset();
            successMessage.value = 'File uploaded.';
            router.reload({ only: ['folders', 'files'] });
        },
        onError: (errors) => {
            errorMessage.value = Object.values(errors).flat().join(' ') || 'Upload failed.';
        },
    });
}

async function deleteFolder(id: string) {
    errorMessage.value = null;
    try {
        const res = await fetch(route('file-manager.folders.destroy', id), {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
        });
        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            errorMessage.value = body.message || 'Unable to delete folder.';
            return;
        }
        successMessage.value = 'Folder deleted.';
        router.reload({ only: ['folders', 'files'] });
    } catch {
        errorMessage.value = 'Unable to delete folder.';
    }
}

async function deleteFile(id: string) {
    errorMessage.value = null;
    try {
        const res = await fetch(route('file-manager.files.destroy', id), {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
        });
        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            errorMessage.value = body.message || 'Unable to delete file.';
            return;
        }
        successMessage.value = 'File deleted.';
        router.reload({ only: ['folders', 'files'] });
    } catch {
        errorMessage.value = 'Unable to delete file.';
    }
}

function downloadFile(id: string) {
    window.location.href = route('file-manager.files.download', id);
}


async function loadTrash() {
    trashLoading.value = true;
    errorMessage.value = null;
    try {
        const res = await fetch(route('file-manager.files.trash'), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const body = await res.json();
        if (!res.ok) {
            throw new Error(body.message || 'Failed to load trash');
        }
        trashFiles.value = body.data || [];
    } catch (e: any) {
        errorMessage.value = e?.message || 'Failed to load trash';
    } finally {
        trashLoading.value = false;
    }
}

async function openTrash() {
    showTrash.value = true;
    await loadTrash();
}

function closeTrash() {
    showTrash.value = false;
}

function openRestoreDialog(file: FileRow) {
    restoreTargetId.value = file.id;
    restoreTargetName.value = file.original_filename;
    restoreFolderId.value = null;
    restoreNeedsAlternative.value = false;
    errorMessage.value = null;
    successMessage.value = null;
    showRestoreDialog.value = true;
}

async function submitRestore(folderId: string | null = null) {
    if (!restoreTargetId.value) {
        return;
    }
    restoreSubmitting.value = true;
    errorMessage.value = null;
    successMessage.value = null;
    try {
        const payload: Record<string, string> = {};
        // Only send folder_id when a real folder is selected. Null/absent means
        // "no alternative selection" — never an implicit root destination.
        if (typeof folderId === 'string' && folderId !== '') {
            payload.folder_id = folderId;
        }
        const res = await fetch(route('file-manager.files.restore', restoreTargetId.value), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        const body = await res.json().catch(() => ({}));
        if (!res.ok) {
            const msg = body.message || 'Restore failed';
            // Server requires an authorized alternative when original folder is gone.
            if (typeof msg === 'string' && msg.toLowerCase().includes('alternative')) {
                restoreNeedsAlternative.value = true;
                errorMessage.value = msg;
                return;
            }
            throw new Error(msg);
        }
        successMessage.value = 'File restored.';
        showRestoreDialog.value = false;
        restoreTargetId.value = null;
        await loadTrash();
        router.reload({ only: ['files', 'folders'] });
    } catch (e: any) {
        errorMessage.value = e?.message || 'Restore failed';
    } finally {
        restoreSubmitting.value = false;
    }
}

async function restoreFile(id: string) {
    const file = trashFiles.value.find((f) => f.id === id);
    if (file) {
        openRestoreDialog(file);
        // Try original location first (no folder_id).
        await submitRestore(null);
        return;
    }
    openRestoreDialog({
        id,
        original_filename: 'File',
        mime_type: null,
        size_bytes: 0,
        managed_by: 'file_manager',
        folder_id: null,
        storage_provider_id: '',
    });
    await submitRestore(null);
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
</script>

<template>
    <AuthenticatedLayout :title="'File Manager'" :crumb="layoutCrumb">
        <div class="p-4 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-semibold">File Manager</h1>
                    <Button
                        v-if="currentFolder"
                        label="Up"
                        size="small"
                        severity="secondary"
                        outlined
                        @click="currentFolder.parent_id ? openFolder(currentFolder.parent_id) : goRoot()"
                    />
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="canManage"
                        label="New folder"
                        size="small"
                        @click="showCreateFolder = true"
                    />
                    <Button
                        v-if="canManage"
                        label="Upload"
                        size="small"
                        severity="success"
                        @click="showUpload = true"
                    />
                </div>
            </div>

            <Message v-if="errorMessage" severity="error" :closable="true" @close="errorMessage = null">
                {{ errorMessage }}
            </Message>
            <Message v-if="successMessage" severity="success" :closable="true" @close="successMessage = null">
                {{ successMessage }}
            </Message>

            <div class="grid gap-4 md:grid-cols-2">
                <div v-if="canManage" class="mb-3 flex gap-2">
                <Button
                    v-if="!showTrash"
                    label="Trash"
                    icon="pi pi-trash"
                    severity="secondary"
                    outlined
                    size="small"
                    @click="openTrash"
                />
                <Button
                    v-else
                    label="Back to files"
                    icon="pi pi-arrow-left"
                    severity="secondary"
                    outlined
                    size="small"
                    @click="closeTrash"
                />
            </div>

            <div v-if="showTrash" class="rounded border border-surface-200 dark:border-surface-700 p-3 mb-4">
                <h2 class="font-medium mb-2">Trash</h2>
                <p class="text-sm text-surface-500 mb-2">
                    File Manager-managed files only. Retention applies from the time each file was trashed.
                </p>
                <p v-if="trashLoading" class="text-sm">Loading…</p>
                <ul v-else-if="trashFiles.length" class="space-y-1">
                    <li
                        v-for="file in trashFiles"
                        :key="file.id"
                        class="flex items-center justify-between gap-2 rounded px-2 py-1 hover:bg-surface-100 dark:hover:bg-surface-800"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="truncate">📄 {{ file.original_filename }}</div>
                            <div class="text-xs text-surface-500">
                                Eligible {{ file.purge_eligible_at || '—' }}
                                <span v-if="file.has_attachments"> · has attachments (purge blocked)</span>
                            </div>
                        </div>
                        <Button
                            v-if="canManage"
                            label="Restore"
                            size="small"
                            @click="restoreFile(file.id)"
                        />
                    </li>
                </ul>
                <p v-else class="text-sm text-surface-500">Trash is empty.</p>
            </div>

            <div v-show="!showTrash" class="rounded border border-surface-200 dark:border-surface-700 p-3">
                    <h2 class="font-medium mb-2">Folders</h2>
                    <ul v-if="folders.length" class="space-y-1">
                        <li
                            v-for="folder in folders"
                            :key="folder.id"
                            class="flex items-center justify-between gap-2 rounded px-2 py-1 hover:bg-surface-100 dark:hover:bg-surface-800"
                        >
                            <button type="button" class="text-left flex-1 truncate" @click="openFolder(folder.id)">
                                📁 {{ folder.name }}
                            </button>
                            <Button
                                v-if="canManage"
                                icon="pi pi-trash"
                                size="small"
                                severity="danger"
                                text
                                @click="deleteFolder(folder.id)"
                            />
                        </li>
                    </ul>
                    <p v-else class="text-sm text-surface-500">No folders here.</p>
                </div>

                <div v-show="!showTrash" class="rounded border border-surface-200 dark:border-surface-700 p-3">
                    <h2 class="font-medium mb-2">Files</h2>
                    <ul v-if="files.length" class="space-y-1">
                        <li
                            v-for="file in files"
                            :key="file.id"
                            class="flex items-center justify-between gap-2 rounded px-2 py-1 hover:bg-surface-100 dark:hover:bg-surface-800"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="truncate">📄 {{ file.original_filename }}</div>
                                <div class="text-xs text-surface-500">
                                    {{ formatSize(file.size_bytes) }}
                                    · {{ file.managed_by }}
                                </div>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <Button icon="pi pi-download" size="small" text @click="downloadFile(file.id)" />
                                <Button
                                    v-if="canManage"
                                    icon="pi pi-trash"
                                    size="small"
                                    severity="danger"
                                    text
                                    @click="deleteFile(file.id)"
                                />
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-surface-500">No files here.</p>
                </div>
            </div>
        </div>

        <Dialog v-model:visible="showCreateFolder" header="New folder" modal :style="{ width: '28rem' }">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm mb-1">Name</label>
                    <InputText v-model="folderForm.name" class="w-full" />
                </div>
                <div v-if="providerOptions.length">
                    <label class="block text-sm mb-1">Default storage provider (optional)</label>
                    <Select
                        v-model="folderForm.default_storage_provider_id"
                        :options="providerOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Inherit / none"
                        class="w-full"
                        show-clear
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" text @click="showCreateFolder = false" />
                    <Button label="Create" :loading="folderForm.processing" @click="createFolder" />
                </div>
            </div>
        </Dialog>

        <Dialog v-model:visible="showUpload" header="Upload file" modal :style="{ width: '28rem' }">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm mb-1">File</label>
                    <input type="file" class="w-full" @change="onFileSelected" />
                </div>
                <div v-if="providerOptions.length">
                    <label class="block text-sm mb-1">Storage provider</label>
                    <Select
                        v-model="uploadForm.storage_provider_id"
                        :options="providerOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Folder default or select"
                        class="w-full"
                        show-clear
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" text @click="showUpload = false" />
                    <Button label="Upload" :loading="uploadForm.processing" @click="uploadFile" />
                </div>
            </div>
        </Dialog>

        <Dialog v-model:visible="showRestoreDialog" header="Restore file" modal :style="{ width: '28rem' }">
            <div class="space-y-3">
                <p class="text-sm">
                    Restore <strong>{{ restoreTargetName }}</strong>
                    <span v-if="!restoreNeedsAlternative"> to its original folder when available.</span>
                    <span v-else> requires an authorized destination folder because the original location is missing or inaccessible.</span>
                </p>
                <div v-if="restoreNeedsAlternative">
                    <label class="block text-sm mb-1">Destination folder</label>
                    <Select
                        v-model="restoreFolderId"
                        :options="folders.map((f) => ({ label: f.name, value: f.id }))"
                        option-label="label"
                        option-value="value"
                        placeholder="Select an authorized folder"
                        class="w-full"
                    />
                    <p class="text-xs text-surface-500 mt-1">
                        Original location is unavailable. Choose an authorized folder in the current context.
                        Root is not offered here because an omitted destination is treated as “no selection.”
                    </p>
                </div>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" text @click="showRestoreDialog = false" />
                    <Button
                        v-if="restoreNeedsAlternative"
                        label="Restore here"
                        :loading="restoreSubmitting"
                        :disabled="!restoreFolderId"
                        @click="submitRestore(restoreFolderId)"
                    />
                    <Button
                        v-else
                        label="Retry"
                        :loading="restoreSubmitting"
                        @click="submitRestore(null)"
                    />
                </div>
            </div>
        </Dialog>
    </AuthenticatedLayout>
</template>
