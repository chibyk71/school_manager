<?php

namespace App\Services\FileManager;

use App\Enums\FileManager\Capability;
use App\Enums\FileManager\ManagedBy;
use App\Enums\FileManager\Ownership;
use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use App\Models\FileManager\StorageReservation;
use App\Models\StorageProvider;
use App\Services\Storage\StorageProviderManager;
use App\Support\Entitlements\Exceptions\InsufficientCapacityException;
use App\Support\FileManager\FolderCapabilityResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * File identity lifecycle: upload, list, retrieve, delete (lifecycle-aware).
 *
 * Physical write and DB persistence are coordinated; compensating cleanup on
 * reverse failure; no silent provider fallback.
 *
 * Phase 1 entitlement integration: storage capacity is reserved before the
 * physical write and settled against the authoritative recorded size_bytes.
 * Direct Spatie Media Library uploads are not covered by this quota.
 */
final class FileService
{
    public function __construct(
        private readonly StorageProviderManager $storageProviders,
        private readonly FileObjectStorage $objectStorage,
        private readonly FolderService $folders,
        private readonly ModuleConfigurationService $modules,
        private readonly StorageQuotaService $storageQuota,
    ) {}

    /**
     * List files in the active ownership context.
     *
     * - folderId null: standalone/root files (no folder capability check).
     * - folderId set: resolve folder in context (fail closed if missing) and
     *   require the folder's effective Browse capability.
     * - Optional search is applied only within the already-authorized ownership
     *   and folder scope. Results are bounded.
     * - Optional managed_by filter is allow-listed and applied in-query before limit.
     *
     * @return Collection<int, File>
     */
    public function listForContext(
        ?string $schoolId,
        ?string $folderId = null,
        ?string $search = null,
        int $limit = 100,
        ?string $managedBy = null,
    ): Collection {
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

        // Phase 4: allow-listed managed_by filter applied in-query before limit.
        if ($managedBy !== null && $managedBy !== '') {
            if (! in_array($managedBy, ['file_manager', 'module'], true)) {
                throw new InvalidArgumentException('Invalid managed_by filter.');
            }
            $query->where('managed_by', $managedBy);
        }

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
            // Operation-time capability: upload into this folder
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

        // Snapshot location-affecting config used for the physical write.
        $writeFingerprint = $this->storageProviders->locationFingerprint($provider);

        // Entitlement gate: atomic capacity reservation before physical write.
        // Requested size is the client-reported upload size (upper bound for reservation).
        // Settlement uses the authoritative recorded size_bytes from the provider write.
        $requestedBytes = max(0, (int) $upload->getSize());
        $operationId = (string) Str::uuid();
        $reservation = null;

        try {
            $reservation = $this->storageQuota->reserve($requestedBytes, $operationId);
        } catch (InsufficientCapacityException $e) {
            throw new InvalidArgumentException(
                'Insufficient File Manager storage capacity for this upload. '
                .'Requested '.$e->requested.' bytes; available '.$e->available.' of '.$e->limit.'.',
                0,
                $e
            );
        }

        // 1–3: authorize + resolve already done; write physical object.
        try {
            $written = $this->objectStorage->write($provider, $storageKey, $upload);
        } catch (Throwable $e) {
            // Physical write failed — no object should exist; release reservation.
            $this->safeReleaseReservation($reservation, 'Physical write failed before object creation.');
            throw $e instanceof RuntimeException
                ? $e
                : new RuntimeException('Physical write to storage provider failed.', 0, $e);
        }

        // 4: persist metadata + settle reservation in one transaction path.
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
                $writeFingerprint,
                $reservation
            ) {
                // Serialize registration against provider delete/disable/reconfigure.
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

                // Fail closed if location-affecting configuration changed after the physical write.
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

                // Settle reservation against authoritative size (may differ from requested).
                $this->storageQuota->settle(
                    $reservation,
                    $file->id,
                    (int) $written['size_bytes']
                );

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
                    'reservation_id' => $reservation->id,
                ]);
                // Uncertain cleanup: retain reservation accounting.
                $this->storageQuota->markUncertain(
                    $reservation,
                    'Metadata persistence failed and compensating delete was not confirmed.'
                );
                throw new RuntimeException(
                    'File metadata could not be saved and compensating storage cleanup failed. '
                    .'The upload is not reported as successful; operators should inspect provider logs.',
                    0,
                    $e
                );
            }

            $this->safeReleaseReservation(
                $reservation,
                'Metadata persistence failed; physical object removed by compensating delete.'
            );

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
     * Best-effort reservation release; never swallows the primary failure.
     */
    private function safeReleaseReservation(StorageReservation $reservation, string $notes): void
    {
        try {
            $this->storageQuota->release($reservation, $notes);
        } catch (Throwable $releaseError) {
            Log::error('file_manager.reservation_release_failed', [
                'reservation_id' => $reservation->id,
                'operation_id' => $reservation->operation_id,
                'notes' => $notes,
                'error' => $releaseError->getMessage(),
            ]);
            // Retain capacity rather than free it on an uncertain release path.
            try {
                $this->storageQuota->markUncertain($reservation, 'Release failed: '.$releaseError->getMessage());
            } catch (Throwable) {
                // Last resort: leave row as-is for reconciliation.
            }
        }
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

        // Domain boundary: module-managed files are not moved via ordinary FM operations.
        if ($file->isModuleManaged()) {
            throw new InvalidArgumentException(
                'Module-managed files cannot be moved through the File Manager. Use the owning module workflow.'
            );
        }

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
