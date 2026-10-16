<?php

namespace App\Services\Storage;

use App\Enums\StorageProviderDriver;

/**
 * Honest capability reporting based on the selected driver and configuration.
 *
 * Does not imply that Phase 1 implements the corresponding file operations.
 */
final class StorageProviderCapabilityReporter
{
    /**
     * @param  array<string, mixed>  $config  Non-secret + secret merged connection config
     * @return array<string, bool|string>
     */
    public function report(StorageProviderDriver $driver, array $config = []): array
    {
        return match ($driver) {
            StorageProviderDriver::Local => [
                'read' => true,
                'write' => true,
                'streaming' => true,
                'file_size' => true,
                'checksum' => false,
                'temporary_urls' => false,
                'signed_urls' => false,
                'driver' => $driver->value,
                'notes' => 'Local adapter capabilities depend on the host filesystem permissions.',
            ],
            StorageProviderDriver::S3 => [
                'read' => true,
                'write' => true,
                'streaming' => true,
                'file_size' => true,
                'checksum' => ! empty($config['bucket']),
                'temporary_urls' => ! empty($config['bucket']) && (! empty($config['key']) || ! empty($config['endpoint'])),
                'signed_urls' => ! empty($config['bucket']) && (! empty($config['key']) || ! empty($config['endpoint'])),
                'driver' => $driver->value,
                'notes' => 'Temporary/signed URLs require a usable key and bucket on an S3-compatible endpoint.',
            ],
        };
    }
}
