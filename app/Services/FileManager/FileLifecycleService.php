<?php

namespace App\Services\FileManager;

use App\Models\FileManager\File;
use App\Models\StorageProvider;
use App\Settings\Definitions\FileManagerSettings;
use App\Support\Settings\SettingsResolver;
use App\Support\Settings\SettingsScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Phase 3 trash / restore / purge transitions for File Manager-managed files.
 *
 * All lifecycle invariants are enforced here so HTTP, jobs, and direct callers share one path.
 */
final class FileLifecycleService
{
    public function __construct(
        private readonly FolderService $folders,
        private readonly FileObjectStorage $objectStorage,
        private readonly SettingsResolver $settings,
    ) {}

    /**
     * @return Collection<int, File>
     */
    public function listTrashed(?string $schoolId): Collection
    {
        $query = File::query()->trashed()->orderByDesc('trashed_at');

        if ($schoolId !== null) {
            $query->forSchool($schoolId);
        } else {
            $query->system();
        }

        return $query->get();
    }

    /**
     * Move an active FM-managed file into Trash.
     * Preserves physical object and attachments; computes immutable purge_eligible_at.
     */
    public function trash(File $file, ?string $actingSchoolId): File
    {
        $this->assertFileInContext($file, $actingSchoolId);

        if ($file->isModuleManaged()) {
            throw new InvalidArgumentException(
                'Module-managed files cannot be trashed through ordinary File Manager operations.'
            );
        }

        if (! $file->isManagedByFileManager()) {
            throw new InvalidArgumentException(
                'Only File Manager-managed files can be moved to Trash.'
            );
        }

        if ($file->isTrashed()) {
            return $file->fresh() ?? $file;
        }

        if ($file->folder_id !== null) {
            $folder = $file->folder;
            if ($folder !== null) {
                \App\Support\FileManager\FolderCapabilityResolver::assertAllows(
                    $folder,
                    \App\Enums\FileManager\Capability::Delete
                );
            }
        }

        $retentionDays = $this->resolveRetentionDays($file);
        $trashedAt = now();
        $purgeEligibleAt = $trashedAt->copy()->addDays($retentionDays);

        return DB::transaction(function () use ($file, $trashedAt, $purgeEligibleAt) {
            /** @var File|null $locked */
            $locked = File::query()->whereKey($file->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new InvalidArgumentException('File not found.');
            }

            if ($locked->isTrashed()) {
                return $locked;
            }

            if ($locked->isModuleManaged()) {
                throw new InvalidArgumentException(
                    'Module-managed files cannot be trashed through ordinary File Manager operations.'
                );
            }

            $locked->original_folder_id = $locked->folder_id;
            $locked->trashed_at = $trashedAt;
            $locked->purge_eligible_at = $purgeEligibleAt;
            $locked->purge_state = null;
            $locked->lifecycle_version = ((int) $locked->lifecycle_version) + 1;
            $locked->save();

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * Restore a trashed FM-managed file to its original folder or an authorized alternative.
     */
    public function restore(File $file, ?string $actingSchoolId, ?string $alternativeFolderId = null): File
    {
        $this->assertFileInContext($file, $actingSchoolId);

        if ($file->isModuleManaged()) {
            throw new InvalidArgumentException(
                'Module-managed files cannot be restored through ordinary File Manager operations.'
            );
        }

        if (! $file->isTrashed()) {
            return $file->fresh() ?? $file;
        }

        if ($file->purge_state === File::PURGE_STATE_DELETING_PHYSICAL) {
            throw new InvalidArgumentException(
                'This file is in the process of permanent purge and cannot be restored.'
            );
        }

        $destinationFolderId = $this->resolveRestoreDestination($file, $actingSchoolId, $alternativeFolderId);

        if ($destinationFolderId !== null) {
            $folder = $this->folders->findForContext($destinationFolderId, $actingSchoolId);
            if ($folder === null) {
                throw new InvalidArgumentException('Destination folder not found in the current ownership context.');
            }
            if ($folder->ownership !== $file->ownership
                || ($file->isSchoolOwned() && $folder->school_id !== $file->school_id)) {
                throw new InvalidArgumentException('File and folder ownership boundaries must match.');
            }
            \App\Support\FileManager\FolderCapabilityResolver::assertAllows(
                $folder,
                \App\Enums\FileManager\Capability::Upload
            );
        }

        return DB::transaction(function () use ($file, $destinationFolderId) {
            /** @var File|null $locked */
            $locked = File::query()->whereKey($file->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new InvalidArgumentException('File not found.');
            }

            if (! $locked->isTrashed()) {
                return $locked;
            }

            if ($locked->purge_state === File::PURGE_STATE_DELETING_PHYSICAL) {
                throw new InvalidArgumentException(
                    'This file is in the process of permanent purge and cannot be restored.'
                );
            }

            if ($locked->isModuleManaged()) {
                throw new InvalidArgumentException(
                    'Module-managed files cannot be restored through ordinary File Manager operations.'
                );
            }

            $locked->folder_id = $destinationFolderId;
            $locked->trashed_at = null;
            $locked->original_folder_id = null;
            $locked->purge_eligible_at = null;
            $locked->purge_state = null;
            $locked->lifecycle_version = ((int) $locked->lifecycle_version) + 1;
            $locked->save();

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * Crash-safe permanent purge for a single eligible trashed FM-managed file.
     *
     * 1. Lock + revalidate (including no attachments)
     * 2. Mark purge_state = deleting_physical
     * 3. Physical delete (allows disabled providers)
     * 4. Delete DB row only if state still matches claim
     */
    public function purge(File $file): void
    {
        $claim = DB::transaction(function () use ($file) {
            /** @var File|null $locked */
            $locked = File::query()->whereKey($file->id)->lockForUpdate()->first();
            if ($locked === null) {
                return null;
            }

            if (! $this->isEligibleForImmediatePurge($locked)) {
                return false;
            }

            $locked->purge_state = File::PURGE_STATE_DELETING_PHYSICAL;
            $locked->lifecycle_version = ((int) $locked->lifecycle_version) + 1;
            $locked->save();

            return [
                'id' => $locked->id,
                'storage_provider_id' => $locked->storage_provider_id,
                'storage_key' => $locked->storage_key,
                'lifecycle_version' => $locked->lifecycle_version,
            ];
        });

        if ($claim === null || $claim === false) {
            return;
        }

        $provider = StorageProvider::query()->find($claim['storage_provider_id']);
        $storageKey = $claim['storage_key'];

        if ($provider !== null && is_string($storageKey) && $storageKey !== '') {
            try {
                $this->objectStorage->deleteObjectAllowingDisabled($provider, $storageKey);
            } catch (Throwable $e) {
                if (! $this->objectStorage->isMissingObjectError($e)) {
                    Log::error('file_manager.purge_physical_failed', [
                        'file_id' => $claim['id'],
                        'provider_id' => $provider->id,
                        'storage_key_prefix' => substr($storageKey, 0, 8).'…',
                        'error_class' => $e::class,
                    ]);
                    throw $e;
                }
            }
        }

        DB::transaction(function () use ($claim) {
            /** @var File|null $locked */
            $locked = File::query()->whereKey($claim['id'])->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }

            if ($locked->lifecycle_version !== $claim['lifecycle_version']
                || $locked->purge_state !== File::PURGE_STATE_DELETING_PHYSICAL
                || ! $locked->isTrashed()
                || $locked->isModuleManaged()) {
                return;
            }

            if ($locked->attachments()->exists()) {
                $locked->purge_state = null;
                $locked->lifecycle_version = ((int) $locked->lifecycle_version) + 1;
                $locked->save();

                return;
            }

            $locked->delete();
        });
    }

    /**
     * @return list<string> claimed file ids
     */
    public function discoverEligibleForPurge(int $limit = 50): array
    {
        $ids = [];

        $candidates = File::query()
            ->eligibleForPurge()
            ->orderBy('purge_eligible_at')
            ->limit($limit)
            ->get();

        foreach ($candidates as $candidate) {
            $claimed = DB::transaction(function () use ($candidate) {
                /** @var File|null $locked */
                $locked = File::query()->whereKey($candidate->id)->lockForUpdate()->first();
                if ($locked === null || ! $this->isEligibleForImmediatePurge($locked)) {
                    return null;
                }

                if ($locked->purge_state !== File::PURGE_STATE_DELETING_PHYSICAL) {
                    $locked->purge_state = File::PURGE_STATE_PENDING;
                    $locked->lifecycle_version = ((int) $locked->lifecycle_version) + 1;
                    $locked->save();
                }

                return $locked->id;
            });

            if ($claimed !== null) {
                $ids[] = $claimed;
            }
        }

        return $ids;
    }

    public function isEligibleForImmediatePurge(File $file): bool
    {
        if (! $file->isTrashed()) {
            return false;
        }
        if (! $file->isManagedByFileManager()) {
            return false;
        }
        if ($file->purge_eligible_at === null || now()->lt($file->purge_eligible_at)) {
            return false;
        }
        if ($file->attachments()->exists()) {
            return false;
        }

        return true;
    }

    public function resolveRetentionDays(File $file): int
    {
        if ($file->isSchoolOwned() && $file->school_id !== null) {
            try {
                $value = $this->settings->get(
                    FileManagerSettings::RETENTION_DAYS_KEY,
                    SettingsScope::school($file->school_id)
                );
                $days = (int) $value;
                if ($days < 1) {
                    return FileManagerSettings::DEFAULT_RETENTION_DAYS;
                }
                if ($days > 365) {
                    return 365;
                }

                return $days;
            } catch (Throwable) {
                return FileManagerSettings::DEFAULT_RETENTION_DAYS;
            }
        }

        return FileManagerSettings::DEFAULT_RETENTION_DAYS;
    }

    private function resolveRestoreDestination(
        File $file,
        ?string $actingSchoolId,
        ?string $alternativeFolderId
    ): ?string {
        $originalId = $file->original_folder_id ?? $file->folder_id;

        if ($originalId !== null) {
            $original = $this->folders->findForContext($originalId, $actingSchoolId);
            if ($original !== null
                && $original->ownership === $file->ownership
                && (! $file->isSchoolOwned() || $original->school_id === $file->school_id)) {
                if ($alternativeFolderId === null || $alternativeFolderId === $originalId) {
                    return $originalId;
                }
            }
        }

        if ($originalId === null && $alternativeFolderId === null) {
            return null;
        }

        if ($alternativeFolderId === null) {
            throw new InvalidArgumentException(
                'Original folder is missing or inaccessible. Provide an authorized alternative destination folder_id.'
            );
        }

        return $alternativeFolderId;
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
}
