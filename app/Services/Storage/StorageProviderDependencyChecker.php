<?php

namespace App\Services\Storage;

use App\Models\FileManager\File;
use App\Models\StorageProvider;

/**
 * Authoritative dependency checks for storage provider lifecycle (Phase 3).
 *
 * A provider is in use if any File record (active or trashed) still depends on it.
 */
final class StorageProviderDependencyChecker
{
    /**
     * @return array{
     *     provider_id: string,
     *     known_dependencies: list<array{type: string, count: int, description: string}>,
     *     tracking_implemented: bool,
     *     summary: string,
     *     safe_to_remove: bool,
     *     file_count: int
     * }
     */
    public function check(StorageProvider $provider): array
    {
        $fileCount = File::query()
            ->where('storage_provider_id', $provider->id)
            ->count();

        $activeCount = File::query()
            ->where('storage_provider_id', $provider->id)
            ->whereNull('trashed_at')
            ->count();

        $trashedCount = $fileCount - $activeCount;

        $deps = [];
        if ($fileCount > 0) {
            $deps[] = [
                'type' => 'fm_files',
                'count' => $fileCount,
                'description' => sprintf(
                    '%d File Manager file(s) depend on this provider (%d active, %d in trash).',
                    $fileCount,
                    $activeCount,
                    $trashedCount
                ),
            ];
        }

        $safe = $fileCount === 0;

        return [
            'provider_id' => $provider->id,
            'known_dependencies' => $deps,
            'tracking_implemented' => true,
            'summary' => $safe
                ? 'No File Manager files depend on this provider.'
                : sprintf('Provider has %d dependent file(s); deletion and location-affecting changes are refused.', $fileCount),
            'safe_to_remove' => $safe,
            'file_count' => $fileCount,
        ];
    }

    public function hasDependencies(StorageProvider $provider): bool
    {
        return File::query()->where('storage_provider_id', $provider->id)->exists();
    }
}
