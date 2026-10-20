<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Message from 'primevue/message';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';
import Tag from 'primevue/tag';

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
    module_code?: string | null;
    folder_id: string | null;
    storage_provider_id: string;
    trashed_at?: string | null;
    purge_eligible_at?: string | null;
    purge_state?: string | null;
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
    search?: string | null;
    managedByFilter?: string | null;
    crumbs?: { label: string; href?: string }[];
}>();

const confirm = useConfirm();

const showCreateFolder = ref(false);
const showUpload = ref(false);
const showRenameFolder = ref(false);
const renameTarget = ref<FolderRow | null>(null);
const renameName = ref('');
const showMoveFile = ref(false);
const moveTarget = ref<FileRow | null>(null);
const moveFolderId = ref<string | null>(null);
const moveSubmitting = ref(false);
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

const searchInput = ref(props.search ?? '');
const managedBySelect = ref(props.managedByFilter ?? '');

watch(
    () => props.search,
    (v) => {
        searchInput.value = v ?? '';
    },
);

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
    parent_id: null as string | null,
    default_storage_provider_id: null as string | null,
});

const uploadForm = useForm({
    file: null as File | null,
    folder_id: null as string | null,
    storage_provider_id: null as string | null,
});

const providerOptions = computed(() =>
    props.providers
        .filter((p) => p.enabled)
        .map((p) => ({ label: `${p.name} (${p.driver})`, value: p.id })),
);

const contextLabel = computed(() => {
    if (showTrash.value) return 'Trash';
    if (props.currentFolder) return props.currentFolder.name;
    return 'Root';
});

const isEmptyListing = computed(
    () => !showTrash.value && props.folders.length === 0 && props.files.length === 0,
);

const hasActiveFilters = computed(
    () => Boolean(props.search) || Boolean(props.managedByFilter),
);

function applySearch() {
    const params: Record<string, string> = {};
    if (props.currentFolder?.id) params.folder = props.currentFolder.id;
    const q = searchInput.value.trim();
    if (q) params.q = q;
    if (managedBySelect.value) params.managed_by = managedBySelect.value;
    router.get(route('file-manager.browser'), params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function clearFilters() {
    searchInput.value = '';
    managedBySelect.value = '';
    const params: Record<string, string> = {};
    if (props.currentFolder?.id) params.folder = props.currentFolder.id;
    router.get(route('file-manager.browser'), params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function openFolder(id: string) {
    router.get(route('file-manager.browser'), { folder: id }, { preserveState: true });
}

function goRoot() {
    router.get(route('file-manager.browser'));
}

function createFolder() {
    errorMessage.value = null;
    folderForm.parent_id = props.currentFolder?.id ?? null;
    folderForm.post(route('file-manager.folders.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateFolder.value = false;
            folderForm.reset();
            successMessage.value = 'Folder created.';
            router.reload({ only: ['folders', 'files', 'currentFolder'] });
        },
        onError: (errors) => {
            errorMessage.value =
                Object.values(errors).flat().join(' ') || 'Unable to create folder.';
        },
    });
}

function openRenameFolder(folder: FolderRow) {
    renameTarget.value = folder;
    renameName.value = folder.name;
    errorMessage.value = null;
    showRenameFolder.value = true;
}

async function submitRenameFolder() {
    if (!renameTarget.value) return;
    const name = renameName.value.trim();
    if (!name) {
        errorMessage.value = 'Folder name is required.';
        return;
    }
    errorMessage.value = null;
    try {
        const res = await fetch(route('file-manager.folders.update', renameTarget.value.id), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ??
                    '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name }),
        });
        const body = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(body.message || 'Unable to rename folder.');
        showRenameFolder.value = false;
        renameTarget.value = null;
        successMessage.value = 'Folder renamed.';
        router.reload({ only: ['folders', 'files', 'currentFolder', 'crumbs'] });
    } catch (e: any) {
        errorMessage.value = e?.message || 'Unable to rename folder.';
    }
}

function openMoveFile(file: FileRow) {
    if (file.managed_by !== 'file_manager') {
        errorMessage.value =
            'This file is module-managed. Ordinary File Manager move is not available.';
        return;
    }
    moveTarget.value = file;
    moveFolderId.value = null;
    errorMessage.value = null;
    showMoveFile.value = true;
}

async function submitMoveFile() {
    if (!moveTarget.value) return;
    moveSubmitting.value = true;
    errorMessage.value = null;
    try {
        const res = await fetch(route('file-manager.files.move', moveTarget.value.id), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ??
                    '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ folder_id: moveFolderId.value }),
        });
        const body = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(body.message || 'Unable to move file.');
        showMoveFile.value = false;
        moveTarget.value = null;
        successMessage.value = 'File moved.';
        router.reload({ only: ['folders', 'files'] });
    } catch (e: any) {
        errorMessage.value = e?.message || 'Unable to move file.';
    } finally {
        moveSubmitting.value = false;
    }
}

function onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    uploadForm.file = input.files?.[0] ?? null;
}

function uploadFile() {
    if (uploadForm.processing) return;
    errorMessage.value = null;
    uploadForm.folder_id = props.currentFolder?.id ?? null;
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

function confirmDeleteFolder(folder: FolderRow) {
    confirm.require({
        message: `Delete folder "${folder.name}"? This only works when the folder is empty and allowed by its capabilities.`,
        header: 'Delete folder',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Cancel',
        acceptLabel: 'Delete',
        acceptClass: 'p-button-danger',
        accept: () => deleteFolder(folder.id),
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
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ??
                    '',
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

function confirmDeleteFile(file: FileRow) {
    if (file.managed_by !== 'file_manager') {
        errorMessage.value =
            'This file is module-managed. Ordinary File Manager delete/trash is not available.';
        return;
    }
    confirm.require({
        message: `Move "${file.original_filename}" to Trash? You can restore it later while retention rules allow.`,
        header: 'Move to Trash',
        icon: 'pi pi-trash',
        rejectLabel: 'Cancel',
        acceptLabel: 'Move to Trash',
        acceptClass: 'p-button-danger',
        accept: () => deleteFile(file.id),
    });
}

async function deleteFile(id: string) {
    errorMessage.value = null;
    try {
        const res = await fetch(route('file-manager.files.destroy', id), {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ??
                    '',
            },
        });
        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            errorMessage.value = body.message || 'Unable to delete file.';
            return;
        }
        successMessage.value = 'File moved to Trash.';
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
        if (!res.ok) throw new Error(body.message || 'Failed to load trash');
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

async function clearPurgeFailure(id: string) {
    errorMessage.value = null;
    successMessage.value = null;
    try {
        const res = await fetch(route('file-manager.files.clear-purge-failure', id), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ||
                    '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
        });
        const body = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(body.message || 'Could not clear purge failure');
        successMessage.value =
            'Purge failure cleared. File can be restored or will be eligible for retry after retention rules.';
        await loadTrash();
    } catch (e: any) {
        errorMessage.value = e?.message || 'Could not clear purge failure';
    }
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
    if (!restoreTargetId.value) return;
    restoreSubmitting.value = true;
    errorMessage.value = null;
    try {
        const payload: Record<string, string> = {};
        if (folderId) payload.folder_id = folderId;
        const res = await fetch(route('file-manager.files.restore', restoreTargetId.value), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ||
                    '',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        const body = await res.json().catch(() => ({}));
        if (!res.ok) {
            if (
                res.status === 422 &&
                typeof body.message === 'string' &&
                (body.message.toLowerCase().includes('original') ||
                    body.message.toLowerCase().includes('alternative') ||
                    body.message.toLowerCase().includes('destination'))
            ) {
                restoreNeedsAlternative.value = true;
                errorMessage.value = body.message;
                return;
            }
            throw new Error(body.message || 'Restore failed');
        }
        showRestoreDialog.value = false;
        successMessage.value = 'File restored.';
        await loadTrash();
        router.reload({ only: ['folders', 'files'] });
    } catch (e: any) {
        errorMessage.value = e?.message || 'Restore failed';
    } finally {
        restoreSubmitting.value = false;
    }
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function managedByLabel(managedBy: string): string {
    return managedBy === 'module' ? 'Module-managed' : 'File Manager';
}

function managedBySeverity(managedBy: string): 'warn' | 'secondary' {
    return managedBy === 'module' ? 'warn' : 'secondary';
}

function folderCanRename(folder: FolderRow): boolean {
    return props.canManage && (folder.capabilities?.includes('rename') ?? true);
}

function folderCanDelete(folder: FolderRow): boolean {
    return props.canManage && (folder.capabilities?.includes('delete') ?? true);
}
</script>

<template>
    <AuthenticatedLayout :title="'File Manager'" :crumb="layoutCrumb">
        <ConfirmDialog />
        <div class="p-4 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2 min-w-0">
                    <h1 class="text-xl font-semibold">File Manager</h1>
                    <Tag :value="contextLabel" severity="info" class="text-xs" />
                    <Button
                        v-if="currentFolder && !showTrash"
                        label="Up"
                        icon="pi pi-arrow-up"
                        size="small"
                        severity="secondary"
                        outlined
                        aria-label="Go to parent folder"
                        @click="
                            currentFolder.parent_id
                                ? openFolder(currentFolder.parent_id)
                                : goRoot()
                        "
                    />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="canManage && !showTrash"
                        label="New folder"
                        icon="pi pi-folder-plus"
                        size="small"
                        @click="showCreateFolder = true"
                    />
                    <Button
                        v-if="canManage && !showTrash"
                        label="Upload"
                        icon="pi pi-upload"
                        size="small"
                        severity="success"
                        @click="showUpload = true"
                    />
                </div>
            </div>

            <Message v-if="errorMessage" severity="error" :closable="true" @close="errorMessage = null">
                {{ errorMessage }}
            </Message>
            <Message
                v-if="successMessage"
                severity="success"
                :closable="true"
                @close="successMessage = null"
            >
                {{ successMessage }}
            </Message>

            <div
                v-if="!showTrash"
                class="flex flex-wrap items-end gap-2 rounded border border-surface-200 dark:border-surface-700 p-3"
            >
                <div class="min-w-[12rem] flex-1">
                    <label for="fm-search" class="mb-1 block text-sm">Search in this folder</label>
                    <InputText
                        id="fm-search"
                        v-model="searchInput"
                        class="w-full"
                        placeholder="Filename or type…"
                        aria-label="Search files and folders in the current folder"
                        @keyup.enter="applySearch"
                    />
                </div>
                <div class="min-w-[10rem]">
                    <label for="fm-managed" class="mb-1 block text-sm">Managed by</label>
                    <Select
                        id="fm-managed"
                        v-model="managedBySelect"
                        :options="[
                            { label: 'Any', value: '' },
                            { label: 'File Manager', value: 'file_manager' },
                            { label: 'Module', value: 'module' },
                        ]"
                        option-label="label"
                        option-value="value"
                        placeholder="Any"
                        class="w-full"
                        aria-label="Filter by lifecycle ownership"
                    />
                </div>
                <Button label="Search" icon="pi pi-search" size="small" @click="applySearch" />
                <Button
                    v-if="hasActiveFilters"
                    label="Clear"
                    icon="pi pi-times"
                    size="small"
                    severity="secondary"
                    outlined
                    @click="clearFilters"
                />
                <p class="mt-1 w-full text-xs text-surface-500">
                    Search and filters apply only within the current folder and your authorized
                    school/system scope. Results are bounded.
                </p>
            </div>

            <div v-if="canManage" class="flex gap-2">
                <Button
                    v-if="!showTrash"
                    label="Trash"
                    icon="pi pi-trash"
                    severity="secondary"
                    outlined
                    size="small"
                    aria-label="Open Trash"
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

            <div
                v-if="showTrash"
                class="mb-4 rounded border border-surface-200 p-3 dark:border-surface-700"
            >
                <h2 class="mb-2 font-medium">Trash</h2>
                <p class="mb-2 text-sm text-surface-500">
                    File Manager-managed files only. Retention applies from the time each file was
                    trashed. Restore fails closed if the physical object is missing or cannot be
                    verified.
                </p>
                <p v-if="trashLoading" class="text-sm" role="status">Loading…</p>
                <ul v-else-if="trashFiles.length" class="space-y-1">
                    <li
                        v-for="file in trashFiles"
                        :key="file.id"
                        class="flex flex-wrap items-center justify-between gap-2 rounded px-2 py-1 hover:bg-surface-100 dark:hover:bg-surface-800"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="truncate">📄 {{ file.original_filename }}</div>
                            <div class="text-xs text-surface-500">
                                Eligible {{ file.purge_eligible_at || '—' }}
                                <span v-if="file.has_attachments">
                                    · has attachments (purge blocked)</span
                                >
                                <span
                                    v-if="file.purge_state === 'failed'"
                                    class="text-red-600 dark:text-red-400"
                                >
                                    · purge failed — restore unavailable until failure is cleared
                                    and the object is verified present</span
                                >
                                <span
                                    v-else-if="file.purge_state === 'deleting_physical'"
                                    class="text-amber-600 dark:text-amber-400"
                                >
                                    · permanent purge in progress — restore unavailable</span
                                >
                                <span v-else-if="file.purge_state"> · {{ file.purge_state }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <Button
                                v-if="canManage && file.purge_state === 'failed'"
                                label="Clear failure"
                                size="small"
                                severity="warn"
                                outlined
                                @click="clearPurgeFailure(file.id)"
                            />
                            <Button
                                v-if="
                                    canManage &&
                                    file.purge_state !== 'failed' &&
                                    file.purge_state !== 'deleting_physical'
                                "
                                label="Restore"
                                size="small"
                                @click="openRestoreDialog(file)"
                            />
                        </div>
                    </li>
                </ul>
                <p v-else class="text-sm text-surface-500">Trash is empty.</p>
            </div>

            <div v-if="!showTrash" class="grid gap-4 md:grid-cols-2">
                <section
                    class="rounded border border-surface-200 p-3 dark:border-surface-700"
                    aria-labelledby="fm-folders-heading"
                >
                    <h2 id="fm-folders-heading" class="mb-2 font-medium">Folders</h2>
                    <ul v-if="folders.length" class="space-y-1">
                        <li
                            v-for="folder in folders"
                            :key="folder.id"
                            class="flex flex-wrap items-center justify-between gap-2 rounded px-2 py-1 hover:bg-surface-100 dark:hover:bg-surface-800"
                        >
                            <button
                                type="button"
                                class="min-w-0 flex-1 truncate text-left hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded"
                                :aria-label="`Open folder ${folder.name}`"
                                @click="openFolder(folder.id)"
                            >
                                📁 {{ folder.name }}
                            </button>
                            <div class="flex gap-1">
                                <Button
                                    v-if="folderCanRename(folder)"
                                    icon="pi pi-pencil"
                                    size="small"
                                    text
                                    rounded
                                    severity="secondary"
                                    aria-label="Rename folder"
                                    @click="openRenameFolder(folder)"
                                />
                                <Button
                                    v-if="folderCanDelete(folder)"
                                    icon="pi pi-trash"
                                    size="small"
                                    text
                                    rounded
                                    severity="danger"
                                    aria-label="Delete folder"
                                    @click="confirmDeleteFolder(folder)"
                                />
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-surface-500">
                        {{
                            hasActiveFilters
                                ? 'No folders match the current search or filters.'
                                : 'No folders here.'
                        }}
                    </p>
                </section>

                <section
                    class="rounded border border-surface-200 p-3 dark:border-surface-700"
                    aria-labelledby="fm-files-heading"
                >
                    <h2 id="fm-files-heading" class="mb-2 font-medium">Files</h2>
                    <ul v-if="files.length" class="space-y-1">
                        <li
                            v-for="file in files"
                            :key="file.id"
                            class="flex flex-wrap items-start justify-between gap-2 rounded px-2 py-2 hover:bg-surface-100 dark:hover:bg-surface-800"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium" :title="file.original_filename">
                                    📄 {{ file.original_filename }}
                                </div>
                                <div
                                    class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-surface-500"
                                >
                                    <span>{{ formatSize(file.size_bytes) }}</span>
                                    <span v-if="file.mime_type">· {{ file.mime_type }}</span>
                                    <Tag
                                        :value="managedByLabel(file.managed_by)"
                                        :severity="managedBySeverity(file.managed_by)"
                                        class="text-xs"
                                    />
                                    <span
                                        v-if="file.managed_by === 'module'"
                                        class="text-amber-700 dark:text-amber-400"
                                    >
                                        restricted
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <Button
                                    icon="pi pi-download"
                                    size="small"
                                    text
                                    rounded
                                    severity="secondary"
                                    aria-label="Download file"
                                    @click="downloadFile(file.id)"
                                />
                                <Button
                                    v-if="canManage && file.managed_by === 'file_manager'"
                                    icon="pi pi-arrows-h"
                                    size="small"
                                    text
                                    rounded
                                    severity="secondary"
                                    aria-label="Move file"
                                    @click="openMoveFile(file)"
                                />
                                <Button
                                    v-if="canManage && file.managed_by === 'file_manager'"
                                    icon="pi pi-trash"
                                    size="small"
                                    text
                                    rounded
                                    severity="danger"
                                    aria-label="Move file to Trash"
                                    @click="confirmDeleteFile(file)"
                                />
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-surface-500">
                        {{
                            hasActiveFilters
                                ? 'No files match the current search or filters.'
                                : 'No files here.'
                        }}
                    </p>
                </section>
            </div>

            <p
                v-if="isEmptyListing && !hasActiveFilters"
                class="text-sm text-surface-500"
                role="status"
            >
                This location is empty. Create a folder or upload a file to get started.
            </p>

            <Dialog
                v-model:visible="showCreateFolder"
                header="New folder"
                modal
                :style="{ width: '28rem' }"
                :closable="!folderForm.processing"
            >
                <div class="space-y-3">
                    <div>
                        <label for="folder-name" class="mb-1 block text-sm">Name</label>
                        <InputText
                            id="folder-name"
                            v-model="folderForm.name"
                            class="w-full"
                            :disabled="folderForm.processing"
                            @keyup.enter="createFolder"
                        />
                    </div>
                    <div v-if="providerOptions.length">
                        <label for="folder-provider" class="mb-1 block text-sm"
                            >Default storage provider (optional)</label
                        >
                        <Select
                            id="folder-provider"
                            v-model="folderForm.default_storage_provider_id"
                            :options="providerOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Inherit / system default"
                            class="w-full"
                            show-clear
                            :disabled="folderForm.processing"
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            label="Cancel"
                            severity="secondary"
                            text
                            :disabled="folderForm.processing"
                            @click="showCreateFolder = false"
                        />
                        <Button
                            label="Create"
                            :loading="folderForm.processing"
                            :disabled="!folderForm.name.trim()"
                            @click="createFolder"
                        />
                    </div>
                </div>
            </Dialog>

            <Dialog
                v-model:visible="showRenameFolder"
                header="Rename folder"
                modal
                :style="{ width: '28rem' }"
            >
                <div class="space-y-3">
                    <div>
                        <label for="rename-folder-name" class="mb-1 block text-sm">Name</label>
                        <InputText
                            id="rename-folder-name"
                            v-model="renameName"
                            class="w-full"
                            @keyup.enter="submitRenameFolder"
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            label="Cancel"
                            severity="secondary"
                            text
                            @click="showRenameFolder = false"
                        />
                        <Button
                            label="Save"
                            :disabled="!renameName.trim()"
                            @click="submitRenameFolder"
                        />
                    </div>
                </div>
            </Dialog>

            <Dialog
                v-model:visible="showMoveFile"
                header="Move file"
                modal
                :style="{ width: '28rem' }"
            >
                <div class="space-y-3">
                    <p class="text-sm">
                        Move <strong>{{ moveTarget?.original_filename }}</strong> to another folder
                        in the same ownership context. Cross-boundary moves are rejected by the
                        server.
                    </p>
                    <div>
                        <label for="move-dest" class="mb-1 block text-sm">Destination folder</label>
                        <Select
                            id="move-dest"
                            v-model="moveFolderId"
                            :options="[
                                { label: 'Root (no folder)', value: null },
                                ...folders.map((f) => ({ label: f.name, value: f.id })),
                            ]"
                            option-label="label"
                            option-value="value"
                            placeholder="Select destination"
                            class="w-full"
                        />
                        <p class="mt-1 text-xs text-surface-500">
                            Only folders visible in the current listing are offered. Nested
                            navigation may be required for deeper destinations.
                        </p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            label="Cancel"
                            severity="secondary"
                            text
                            :disabled="moveSubmitting"
                            @click="showMoveFile = false"
                        />
                        <Button label="Move" :loading="moveSubmitting" @click="submitMoveFile" />
                    </div>
                </div>
            </Dialog>

            <Dialog
                v-model:visible="showUpload"
                header="Upload file"
                modal
                :style="{ width: '28rem' }"
                :closable="!uploadForm.processing"
            >
                <div class="space-y-3">
                    <div>
                        <label for="upload-file" class="mb-1 block text-sm">File</label>
                        <input
                            id="upload-file"
                            type="file"
                            class="w-full text-sm"
                            :disabled="uploadForm.processing"
                            @change="onFileSelected"
                        />
                    </div>
                    <div v-if="providerOptions.length">
                        <label for="upload-provider" class="mb-1 block text-sm"
                            >Storage provider (optional)</label
                        >
                        <Select
                            id="upload-provider"
                            v-model="uploadForm.storage_provider_id"
                            :options="providerOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Folder or system default"
                            class="w-full"
                            show-clear
                            :disabled="uploadForm.processing"
                        />
                    </div>
                    <p class="text-xs text-surface-500">
                        Uploads create File Manager-managed files. Module-managed content must be
                        registered by the owning module.
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button
                            label="Cancel"
                            severity="secondary"
                            text
                            :disabled="uploadForm.processing"
                            @click="showUpload = false"
                        />
                        <Button
                            label="Upload"
                            :loading="uploadForm.processing"
                            :disabled="!uploadForm.file || uploadForm.processing"
                            @click="uploadFile"
                        />
                    </div>
                </div>
            </Dialog>

            <Dialog
                v-model:visible="showRestoreDialog"
                header="Restore file"
                modal
                :style="{ width: '28rem' }"
            >
                <div class="space-y-3">
                    <p class="text-sm">
                        Restore <strong>{{ restoreTargetName }}</strong>
                        <span v-if="!restoreNeedsAlternative">
                            to its original folder when available.</span
                        >
                        <span v-else>
                            requires an authorized destination folder because the original location
                            is missing or inaccessible.</span
                        >
                    </p>
                    <div v-if="restoreNeedsAlternative">
                        <label class="mb-1 block text-sm">Destination folder</label>
                        <Select
                            v-model="restoreFolderId"
                            :options="folders.map((f) => ({ label: f.name, value: f.id }))"
                            option-label="label"
                            option-value="value"
                            placeholder="Select an authorized folder"
                            class="w-full"
                        />
                        <p class="mt-1 text-xs text-surface-500">
                            Original location is unavailable. Choose an authorized folder in the
                            current context.
                        </p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            label="Cancel"
                            severity="secondary"
                            text
                            @click="showRestoreDialog = false"
                        />
                        <Button
                            v-if="restoreNeedsAlternative"
                            label="Restore here"
                            :loading="restoreSubmitting"
                            :disabled="!restoreFolderId"
                            @click="submitRestore(restoreFolderId)"
                        />
                        <Button
                            v-else
                            label="Restore"
                            :loading="restoreSubmitting"
                            @click="submitRestore(null)"
                        />
                    </div>
                </div>
            </Dialog>
        </div>
    </AuthenticatedLayout>
</template>
