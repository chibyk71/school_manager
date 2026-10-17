<?php

namespace App\Services\FileManager;

use App\Enums\FileManager\ManagedBy;
use App\Enums\FileManager\Ownership;
use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use App\Models\StorageProvider;
use App\Services\Storage\StorageProviderManager;
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
     * @return Collection<int, File>
     */
    public function listForContext(?string $schoolId, ?string $folderId = null): Collection
    {
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

        return $query->get();
    }

    public function findForContext(string $id, ?string $schoolId): ?File
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
     * @param  array{
     *     folder_id?: string|null,
     *     storage_provider_id?: string|null,
     *     managed_by?: string,
     *     module_code?: string|null,
     *     ownership?: string
     * }  $meta
     */
    public function upload(UploadedFile $upload, array $meta, ?string $actingSchoolId): File
    {
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

        $managedBy = ManagedBy::tryFrom((string) ($meta['managed_by'] ?? ManagedBy::FileManager->value))
            ?? ManagedBy::FileManager;

        $extension = $upload->getClientOriginalExtension() ?: pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        $storageKey = $this->objectStorage->generateStorageKey(is_string($extension) ? $extension : null);

        // 1–3: authorize + resolve already done; write physical object.
        try {
            $written = $this->objectStorage->write($provider, $storageKey, $upload);
        } catch (Throwable $e) {
            // Physical write failed — no File record.
            throw $e instanceof RuntimeException
                ? $e
                : new RuntimeException('Physical write to storage provider failed.', 0, $e);
        }

        // 4: persist metadata.
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
                $moduleCode
            ) {
                $file = new File([
                    'ownership' => $ownership,
                    'school_id' => $ownership === Ownership::School ? $actingSchoolId : null,
                    'folder_id' => $folder?->id,
                    'storage_provider_id' => $provider->id,
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
            // 5–6: compensating cleanup.
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

        $provider = StorageProvider::query()->find($file->storage_provider_id);
        if ($provider === null) {
            throw new RuntimeException('Storage provider for this file no longer exists.');
        }

        // Ensure provider is still within the same ownership boundary visibility rules.
        $visible = $this->storageProviders->findForContext($provider->id, $actingSchoolId);
        if ($visible === null && $actingSchoolId !== null) {
            // School context reading a school file whose provider is school-owned for same school
            // is already covered; system providers are not visible to school context for management
            // but the file's actual provider may still be used for retrieval if ownership matches.
            // Spec: File and its storage provider must have compatible ownership boundaries.
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
     * Ordinary File Manager delete. Refuses module-managed files.
     */
    public function delete(File $file, ?string $actingSchoolId): void
    {
        $this->assertFileInContext($file, $actingSchoolId);

        if ($file->isModuleManaged()) {
            throw new InvalidArgumentException(
                'Module-managed files cannot be deleted through ordinary File Manager operations.'
            );
        }

        $provider = StorageProvider::query()->find($file->storage_provider_id);
        $storageKey = $file->storage_key;

        DB::transaction(function () use ($file) {
            // Detach links first (DB cascade would also handle, but be explicit).
            $file->attachments()->delete();
            $file->delete();
        });

        if ($provider !== null) {
            try {
                $this->objectStorage->deleteObject($provider, $storageKey);
            } catch (Throwable $e) {
                Log::error('file_manager.delete_physical_failed', [
                    'file_id' => $file->id,
                    'provider_id' => $provider->id,
                    'storage_key_prefix' => substr($storageKey, 0, 8).'…',
                ]);
                // File row is gone; physical orphan is an operational concern.
            }
        }
    }

    public function move(File $file, ?string $newFolderId, ?string $actingSchoolId): File
    {
        $this->assertFileInContext($file, $actingSchoolId);

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

        $file->folder_id = $folder->id;
        $file->save();

        return $file->fresh();
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
            // School file may use a system provider only when that is an intentional design;
            // Phase 1 visibility hides system providers from school management context, but
            // historical/system default providers may still hold objects. Require same school
            // for school-owned providers; system providers are allowed for school files only
            // when explicitly present as the file's actual provider (already stored).
        }
    }
}
