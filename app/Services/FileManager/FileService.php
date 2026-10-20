<?php

namespace App\Services\FileManager;

use App\Enums\FileManager\Capability;
use App\Enums\FileManager\ManagedBy;
use App\Enums\FileManager\Ownership;
use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use App\Models\StorageProvider;
use App\Services\Storage\StorageProviderManager;
use App\Support\FileManager\FolderCapabilityResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * File identity lifecycle: upload, list, retrieve, delete (lifecycle-aware).
 *
 * Physical write and DB persistence are coordinated; compensating cleanup on
 * reverse failure; no silent provider fallback.
 */
final class FileService
{
    public function __construct(
        private readonly StorageProviderManager $storageProviders,
        private readonly FileObjectStorage $objectStorage,
        private readonly FolderService $folders,
        private readonly ModuleConfigurationService $modules,
    ) {}

    /**
     * List files in the active ownership context.
     *
     * - folderId null: standalone/root files (no folder capability check).
     * - folderId set: resolve folder in context (fail closed if missing) and
     *   require the folder's effective Browse capability.
     * - Optional search is applied only within the already-authorized ownership
     *   and folder scope. Results are bounded.
     *
     * @return Collection<int, File>
     */
    public function listForContext(?string $schoolId, ?string $folderId = null, ?string $search = null, int $limit = 100): Collection
    {
        if ($folderId !== null) {
            $folder = $this->folders->findForContext($folderId, $schoolId);
            if ($folder === null) {
                throw new InvalidArgumentException('Folder not found in the current ownership context.');
            }
            FolderCapabilityResolver::assertAllows(
                $folder,
                Capability::Browse
            );
        }

        $query = File::query()->orderBy('original_filename');

        if ($schoolId !== null) {
            $query->forSchool($schoolId);
        } else {
            $query->system();
        }

        if ($folderId === null) {
            $query->whereNull('folder_id');
        } else {
            $query->where('folder_id', $folderId);
        }

        // Phase 3: ordinary browsing never surfaces trashed files.
        $query->active();

        $search = is_string($search) ? trim($search) : '';
        if ($search !== '') {
            $term = mb_substr($search, 0, 100);
            $query->where(function ($q) use ($term) {
                $q->where('original_filename', 'like', '%'.$term.'%')
                    ->orWhere('mime_type', 'like', '%'.$term.'%');
            });
        }

        $limit = max(1, min($limit, 200));

        return $query->limit($limit)->get();
    }

    public function findForContext(string $id, ?string $schoolId): ?File
    {
        $file = File::query()->active()->find($id);
        if ($file === null) {
            return null;
        }

        if ($schoolId !== null) {
            if ($file->isSchoolOwned() && $file->school_id === $schoolId) {
                return $file;
            }

            return null;
        }

        return $file->isSystemOwned() ? $file : null;
    }

    /**
     * Locate a file in context including trashed rows (trash admin / restore).
     */
    public function findIncludingTrashedForContext(string $id, ?string $schoolId): ?File
    {
        $file = File::query()->find($id);
        if ($file === null) {
            return null;
        }

        if ($schoolId !== null) {
            if ($file->isSchoolOwned() && $file->school_id === $schoolId) {
                return $file;
            }

            return null;
        }

        return $file->isSystemOwned() ? $file : null;
    }

    /**
     * Ordinary File Manager upload. Always creates a File Manager-managed file.
     * Clients cannot set managed_by; use registerModuleManaged() for module-owned content.
     *
     * @param  array{
     *     folder_id?: string|null,
     *     storage_provider_id?: string|null,
     *     module_code?: string|null,
     *     ownership?: string
     * }  $meta
     */
    public function upload(UploadedFile $upload, array $meta, ?string $actingSchoolId): File
    {
        // Strip any client-supplied lifecycle ownership — ordinary uploads are always FM-managed.
        unset($meta['managed_by']);

        return $this->performUpload($upload, $meta, $actingSchoolId, ManagedBy::FileManager);
    }

    /**
     * Trusted path for module-owned files (server-side / internal callers only).
     * Not exposed on the ordinary File Manager upload HTTP endpoint.
     *
     * @param  array{
     *     folder_id?: string|null,
     *     storage_provider_id?: string|null,
     *     module_code?: string|null,
     *     ownership?: string
     * }  $meta
     */
    public function registerModuleManaged(UploadedFile $upload, array $meta, ?string $actingSchoolId): File
    {
        unset($meta['managed_by']);

        return $this->performUpload($upload, $meta, $actingSchoolId, ManagedBy::Module);
    }

    /**
     * @param  array{
     *     folder_id?: string|null,
     *     storage_provider_id?: string|null,
     *     module_code?: string|null,
     *     ownership?: string
     * }  $meta
     */
    private function performUpload(
        UploadedFile $upload,
        array $meta,
        ?string $actingSchoolId,
        ManagedBy $managedBy
    ): File {
        $ownership = $this->resolveOwnership($meta['ownership'] ?? null, $actingSchoolId);
        $this->assertCanMutateOwnership($actingSchoolId, $ownership);

        $folder = null;
        if (! empty($meta['folder_id'])) {
            $folder = $this->folders->findForContext((string) $meta['folder_id'], $actingSchoolId);
            if ($folder === null) {
                throw new InvalidArgumentException('Folder not found in the current ownership context.');
            }
            if ($folder->ownership !== $ownership
                || ($ownership === Ownership::School && $folder->school_id !== $actingSchoolId)) {
                throw new InvalidArgumentException('File and folder ownership boundaries must match.');
            }
            FolderCapabilityResolver::assertAllows(
                $folder,
                Capability::Upload
            );
        }

        $moduleCode = $meta['module_code'] ?? $folder?->module_code;
        if ($moduleCode !== null && $moduleCode !== '' && $actingSchoolId !== null) {
            if (! $this->modules->isEnabled($actingSchoolId, $moduleCode)) {
                throw new InvalidArgumentException("Module '{$moduleCode}' is disabled for this school.");
            }
        }

        $provider = $this->resolveProvider(
            $meta['storage_provider_id'] ?? null,
            $folder,
            $actingSchoolId
        );

        $extension = $upload->getClientOriginalExtension() ?: pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        $storageKey = $this->objectStorage->generateStorageKey(is_string($extension) ? $extension : null);

        $writeFingerprint = $this->storageProviders->locationFingerprint($provider);

        try {
            $written = $this->objectStorage->write($provider, $storageKey, $upload);
        } catch (Throwable $e) {
            throw $e instanceof RuntimeException
                ? $e
                : new RuntimeException('Physical write to storage provider failed.', 0, $e);
        }

        try {
            $file = DB::transaction(function () use (
                $ownership,
                $actingSchoolId,
                $folder,
                $provider,
                $storageKey,
                $upload,
                $written,
                $managedBy,
                $moduleCode,
                $writeFingerprint
            ) {
                /** @var StorageProvider|null $lockedProvider */
                $lockedProvider = StorageProvider::query()
                    ->whereKey($provider->id)
                    ->lockForUpdate()
                    ->first();
                if ($lockedProvider === null) {
                    throw new InvalidArgumentException(
                        'Storage provider is no longer available for new uploads.'
                    );
                }
                if (! $lockedProvider->enabled) {
                    throw new InvalidArgumentException('Storage provider is disabled.');
                }

                $currentFingerprint = $this->storageProviders->locationFingerprint($lockedProvider);
                if (! hash_equals($writeFingerprint, $currentFingerprint)) {
                    throw new InvalidArgumentException(
                        'Storage provider configuration changed during upload. The object was not registered; please retry.'
                    );
                }

                $file = new File([
                    'ownership' => $ownership,
                    'school_id' => $ownership === Ownership::School ? $actingSchoolId : null,
                    'folder_id' => $folder?->id,
                    'storage_provider_id' => $lockedProvider->id,
                    'storage_key' => $storageKey,
                    'original_filename' => $upload->getClientOriginalName() ?: 'upload',
                    'mime_type' => $written['mime_type'],
                    'size_bytes' => $written['size_bytes'],
                    'checksum' => $written['checksum'],
                    'managed_by' => $managedBy,
                    'module_code' => $moduleCode ?: null,
                ]);
                $file->save();

                return $file;
            });
        } catch (Throwable $e) {
            $cleaned = $this->objectStorage->attemptCompensatingDelete($provider, $storageKey);
            if (! $cleaned) {
                Log::error('file_manager.upload_orphaned_object', [
                    'provider_id' => $provider->id,
                    'storage_key_prefix' => substr($storageKey, 0, 8).'…',
                    'recovery' => 'Manual inspection of provider objects may be required; File row was not created.',
                ]);
                throw new RuntimeException(
                    'File metadata could not be saved and compensating storage cleanup failed. '
                    .'The upload is not reported as successful; operators should inspect provider logs.',
                    0,
                    $e
                );
            }

            throw new RuntimeException(
                'File metadata could not be saved after a successful storage write. '
                .'The physical object was removed. Please retry the upload.',
                0,
                $e
            );
        }

        return $file->fresh();
    }

    /**
     * Stream contents for an authorized file.
     *
     * @return array{file: File, stream: resource, filename: string, mime: string|null}
     */
    public function retrieve(File $file, ?string $actingSchoolId): array
    {
        $this->assertFileInContext($file, $actingSchoolId);

        if ($file->isTrashed()) {
            throw new InvalidArgumentException('Trashed files are not available for ordinary download.');
        }

        $this->assertFileFolderCapability($file, Capability::Download);

        $provider = StorageProvider::query()->find($file->storage_provider_id);
        if ($provider === null) {
            throw new RuntimeException('Storage provider for this file no longer exists.');
        }

        $visible = $this->storageProviders->findForContext($provider->id, $actingSchoolId);
        if ($visible === null && $actingSchoolId !== null) {
            $this->assertProviderOwnershipCompatible($file, $provider);
        } elseif ($visible === null && $actingSchoolId === null) {
            $this->assertProviderOwnershipCompatible($file, $provider);
        }

        $stream = $this->objectStorage->readStream($provider, $file->storage_key);

        return [
            'file' => $file,
            'stream' => $stream,
            'filename' => $file->original_filename,
            'mime' => $file->mime_type,
        ];
    }

    /**
     * Ordinary File Manager delete (Phase 3): moves FM-managed files to Trash.
     * Refuses module-managed files. Physical object and attachments are preserved.
     */
    public function delete(File $file, ?string $actingSchoolId): void
    {
        app(FileLifecycleService::class)->trash($file, $actingSchoolId);
    }

    public function move(File $file, ?string $newFolderId, ?string $actingSchoolId): File
    {
        $this->assertFileInContext($file, $actingSchoolId);
        $this->assertFileFolderCapability($file, Capability::Move);

        if ($newFolderId === null) {
            $file->folder_id = null;
            $file->save();

            return $file->fresh();
        }

        $folder = $this->folders->findForContext($newFolderId, $actingSchoolId);
        if ($folder === null) {
            throw new InvalidArgumentException('Target folder not found in the current ownership context.');
        }

        if ($folder->ownership !== $file->ownership
            || ($file->isSchoolOwned() && $folder->school_id !== $file->school_id)) {
            throw new InvalidArgumentException('File and folder ownership boundaries must match.');
        }

        FolderCapabilityResolver::assertAllows(
            $folder,
            Capability::Move
        );

        $file->folder_id = $folder->id;
        $file->save();

        return $file->fresh();
    }

    private function assertFileFolderCapability(File $file, Capability $capability): void
    {
        if ($file->folder_id === null) {
            return;
        }

        $folder = Folder::query()->find($file->folder_id);
        if ($folder === null) {
            return;
        }

        FolderCapabilityResolver::assertAllows($folder, $capability);
    }

    private function resolveProvider(
        ?string $providerId,
        ?Folder $folder,
        ?string $actingSchoolId
    ): StorageProvider {
        $id = $providerId ?? $folder?->default_storage_provider_id;
        if ($id === null) {
            throw new InvalidArgumentException(
                'A storage provider must be specified (or a folder default provider must be set).'
            );
        }

        $provider = $this->storageProviders->findForContext((string) $id, $actingSchoolId);
        if ($provider === null) {
            throw new InvalidArgumentException('Storage provider not found in the current ownership context.');
        }
        if (! $provider->enabled) {
            throw new InvalidArgumentException('Storage provider is disabled.');
        }

        return $provider;
    }

    private function resolveOwnership(?string $requested, ?string $actingSchoolId): Ownership
    {
        if ($requested !== null && $requested !== '') {
            $ownership = Ownership::from($requested);
            if ($ownership === Ownership::System && $actingSchoolId !== null) {
                throw new InvalidArgumentException('Cannot create system-owned files from a school context.');
            }
            if ($ownership === Ownership::School && $actingSchoolId === null) {
                throw new InvalidArgumentException('Cannot create school-owned files without a school context.');
            }

            return $ownership;
        }

        return $actingSchoolId !== null ? Ownership::School : Ownership::System;
    }

    private function assertCanMutateOwnership(?string $actingSchoolId, Ownership $ownership): void
    {
        if ($ownership === Ownership::System && $actingSchoolId !== null) {
            throw new InvalidArgumentException('System-owned files cannot be managed from a school context.');
        }
        if ($ownership === Ownership::School && $actingSchoolId === null) {
            throw new InvalidArgumentException('School-owned files cannot be managed outside their school context.');
        }
    }

    private function assertFileInContext(File $file, ?string $actingSchoolId): void
    {
        if ($actingSchoolId !== null) {
            if (! $file->isSchoolOwned() || $file->school_id !== $actingSchoolId) {
                throw new InvalidArgumentException('File is not accessible in the current school context.');
            }

            return;
        }

        if (! $file->isSystemOwned()) {
            throw new InvalidArgumentException('File is not accessible in the platform context.');
        }
    }

    private function assertProviderOwnershipCompatible(File $file, StorageProvider $provider): void
    {
        if ($file->isSystemOwned() && ! $provider->isSystemOwned()) {
            throw new InvalidArgumentException('File and storage provider ownership boundaries are incompatible.');
        }
        if ($file->isSchoolOwned()) {
            if ($provider->isSchoolOwned() && $provider->school_id !== $file->school_id) {
                throw new InvalidArgumentException('File and storage provider belong to different schools.');
            }
        }
    }
}
