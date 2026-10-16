<?php

namespace App\Services\Storage;

use App\Models\StorageProvider;

/**
 * Minimal dependency-check contract for future provider lifecycle operations.
 *
 * Phase 1 has no authoritative file-operation or media tracking tied to
 * storage provider records. This implementation reports that fact honestly
 * and must not claim a provider is safe to remove.
 */
final class StorageProviderDependencyChecker
{
    /**
     * @return array{
     *     provider_id: string,
     *     known_dependencies: list<array{type: string, count: int, description: string}>,
     *     tracking_implemented: bool,
     *     summary: string,
     *     safe_to_remove: null
     * }
     */
    public function check(StorageProvider $provider): array
    {
        // No inspectable dependency tables exist yet that reference storage_providers.id.
        // Media Library uses disk names from config, not provider UUIDs.
        // Legacy advanced.storage is a settings blob, not a provider FK.

        return [
            'provider_id' => $provider->id,
            'known_dependencies' => [],
            'tracking_implemented' => false,
            'summary' => 'No known dependencies yet; dependency tracking is not implemented.',
            'safe_to_remove' => null, // never certify safety when tracking is incomplete
        ];
    }
}
