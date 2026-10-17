<?php

namespace App\Services\FileManager;

use App\Enums\StorageProviderDriver;
use App\Models\StorageProvider;
use App\Services\Storage\StorageProviderConnectionTester;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Thin integration between File Manager identities and Phase 1 storage providers.
 *
 * Does not reimplement provider CRUD or credential resolution.
 * No silent fallback to another provider.
 */
final class FileObjectStorage
{
    public function __construct(
        private readonly StorageProviderConnectionTester $connectionTester,
    ) {}

    /**
     * Generate an opaque storage key independent of display names and folder paths.
     */
    public function generateStorageKey(?string $extension = null): string
    {
        $key = 'fm/'.Str::uuid()->toString();
        if ($extension !== null && $extension !== '') {
            $ext = strtolower(ltrim($extension, '.'));
            if (preg_match('/^[a-z0-9]{1,16}$/', $ext)) {
                $key .= '.'.$ext;
            }
        }

        return $key;
    }

    /**
     * Build a Laravel filesystem disk for the given provider.
     *
     * @throws RuntimeException when the provider is disabled or configuration is unsafe
     */
    public function diskFor(StorageProvider $provider): Filesystem
    {
        if (! $provider->enabled) {
            throw new RuntimeException('Storage provider is disabled.');
        }

        $driver = $provider->driver instanceof StorageProviderDriver
            ? $provider->driver
            : StorageProviderDriver::from((string) $provider->driver);

        $config = $provider->connectionConfig();

        return match ($driver) {
            StorageProviderDriver::Local => $this->buildLocalDisk($config),
            StorageProviderDriver::S3 => $this->buildS3Disk($config),
        };
    }

    /**
     * Write uploaded content to the provider under the given storage key.
     *
     * @return array{size_bytes: int, mime_type: string|null, checksum: string|null}
     *
     * @throws RuntimeException on write failure
     */
    public function write(StorageProvider $provider, string $storageKey, UploadedFile $upload): array
    {
        $disk = $this->diskFor($provider);

        try {
            $stream = fopen($upload->getRealPath(), 'r');
            if ($stream === false) {
                throw new RuntimeException('Unable to open uploaded file for reading.');
            }
            try {
                $written = $disk->writeStream($storageKey, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if ($written === false) {
                throw new RuntimeException('Storage provider rejected the write operation.');
            }
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Physical write to storage provider failed.', 0, $e);
        }

        $mime = $upload->getMimeType() ?: $upload->getClientMimeType();
        $size = $upload->getSize() ?: 0;
        $checksum = null;
        try {
            $path = $upload->getRealPath();
            if (is_string($path) && is_file($path)) {
                $checksum = hash_file('sha256', $path) ?: null;
            }
        } catch (Throwable) {
            $checksum = null;
        }

        return [
            'size_bytes' => (int) $size,
            'mime_type' => is_string($mime) ? $mime : null,
            'checksum' => $checksum,
        ];
    }

    /**
     * Attempt compensating deletion after a successful physical write when DB persistence fails.
     *
     * @return bool true when the object was removed (or already absent)
     */
    public function attemptCompensatingDelete(StorageProvider $provider, string $storageKey): bool
    {
        try {
            $disk = $this->diskFor($provider);
            if ($disk->exists($storageKey)) {
                $disk->delete($storageKey);
            }

            return true;
        } catch (Throwable $e) {
            Log::error('file_manager.compensating_delete_failed', [
                'provider_id' => $provider->id,
                'storage_key_prefix' => substr($storageKey, 0, 8).'…',
                'driver' => $provider->driver instanceof StorageProviderDriver
                    ? $provider->driver->value
                    : (string) $provider->driver,
                // No secrets, no full key, no exception message that might leak paths.
            ]);

            return false;
        }
    }

    /**
     * Read a stream for authorized download.
     *
     * @return resource
     *
     * @throws RuntimeException when the object is missing or unreadable
     */
    public function readStream(StorageProvider $provider, string $storageKey)
    {
        $disk = $this->diskFor($provider);

        if (! $disk->exists($storageKey)) {
            throw new RuntimeException('Stored object not found on the storage provider.');
        }

        $stream = $disk->readStream($storageKey);
        if ($stream === false || ! is_resource($stream)) {
            throw new RuntimeException('Unable to open stored object for reading.');
        }

        return $stream;
    }

    /**
     * Delete the physical object. Used for File Manager-managed deletes only.
     */
    public function deleteObject(StorageProvider $provider, string $storageKey): void
    {
        try {
            $disk = $this->diskFor($provider);
            if ($disk->exists($storageKey)) {
                $disk->delete($storageKey);
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to delete physical object from storage provider.', 0, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildLocalDisk(array $config): Filesystem
    {
        $rootInput = $config['root'] ?? storage_path('app/private');
        if (! is_string($rootInput)) {
            throw new RuntimeException('Local storage root path is invalid.');
        }

        $root = $this->connectionTester->assertSafeLocalRoot($rootInput);

        return Storage::build([
            'driver' => 'local',
            'root' => $root,
            'throw' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildS3Disk(array $config): Filesystem
    {
        [$diskConfig, $error] = $this->connectionTester->buildS3DiskConfig($config);
        if ($diskConfig === null) {
            throw new RuntimeException($error ?? 'S3 storage configuration is not permitted.');
        }
        $diskConfig['throw'] = true;

        return Storage::build($diskConfig);
    }
}
