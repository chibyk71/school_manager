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
    /**
     * Test-only seam: invoked after a successful physical write so tests can
     * interleave provider reconfiguration before metadata registration.
     *
     * @var null|callable(StorageProvider, string): void
     */
    private static $afterWriteForTesting = null;

    public function __construct(
        private readonly StorageProviderConnectionTester $connectionTester,
    ) {}

    /**
     * @param  null|callable(StorageProvider, string): void  $callback
     */
    public static function setAfterWriteForTesting(?callable $callback): void
    {
        if ($callback !== null && ! app()->runningUnitTests()) {
            throw new RuntimeException('afterWriteForTesting is only available during unit tests.');
        }
        self::$afterWriteForTesting = $callback;
    }

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
    /**
     * Build a Laravel filesystem disk for write/upload paths (requires enabled provider).
     */
    public function diskFor(StorageProvider $provider): Filesystem
    {
        return $this->buildDisk($provider, requireEnabled: true);
    }

    /**
     * Build disk for retrieval or purge of existing objects (disabled providers remain readable).
     */
    public function diskForExisting(StorageProvider $provider): Filesystem
    {
        return $this->buildDisk($provider, requireEnabled: false);
    }

    private function buildDisk(StorageProvider $provider, bool $requireEnabled): Filesystem
    {
        if ($requireEnabled && ! $provider->enabled) {
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

        if (self::$afterWriteForTesting !== null && app()->runningUnitTests()) {
            (self::$afterWriteForTesting)($provider, $storageKey);
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
        $disk = $this->diskForExisting($provider);

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
     * Delete the physical object (write-path semantics; requires enabled provider).
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
     * Authoritative existence check for an existing object (read path; allows disabled providers).
     */
    public function objectExists(StorageProvider $provider, string $storageKey): bool
    {
        try {
            $disk = $this->diskForExisting($provider);

            return $disk->exists($storageKey);
        } catch (Throwable $e) {
            if ($this->isMissingObjectError($e)) {
                return false;
            }
            throw new RuntimeException('Unable to verify whether the stored object exists.', 0, $e);
        }
    }

    /**
     * Delete existing object for purge even when the provider is disabled.
     */
    public function deleteObjectAllowingDisabled(StorageProvider $provider, string $storageKey): void
    {
        try {
            $disk = $this->diskForExisting($provider);
            if ($disk->exists($storageKey)) {
                $disk->delete($storageKey);
            }
        } catch (Throwable $e) {
            if ($this->isMissingObjectError($e)) {
                return;
            }
            throw new RuntimeException('Failed to delete physical object from storage provider.', 0, $e);
        }
    }

    /**
     * Whether the throwable indicates the object is already absent (idempotent purge).
     */
    public function isMissingObjectError(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        foreach (['not found', 'no such file', 'nosuchkey', '404', 'does not exist', 'file does not exist'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }
        $prev = $e->getPrevious();
        if ($prev instanceof Throwable) {
            return $this->isMissingObjectError($prev);
        }

        return false;
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
