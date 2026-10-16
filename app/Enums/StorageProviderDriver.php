<?php

namespace App\Enums;

/**
 * Supported storage provider drivers for Phase 1.
 *
 * Mapped to Laravel Filesystem / Flysystem adapters already present
 * in config/filesystems.php (local, s3).
 */
enum StorageProviderDriver: string
{
    case Local = 'local';
    case S3 = 's3';

    public function label(): string
    {
        return match ($this) {
            self::Local => 'Local filesystem',
            self::S3 => 'S3-compatible',
        };
    }

    /**
     * Non-secret configuration keys expected for this driver.
     *
     * @return list<string>
     */
    public function configKeys(): array
    {
        return match ($this) {
            self::Local => ['root', 'visibility'],
            self::S3 => ['key', 'region', 'bucket', 'url', 'endpoint', 'use_path_style_endpoint'],
        };
    }

    /**
     * Secret configuration keys for this driver.
     *
     * @return list<string>
     */
    public function secretKeys(): array
    {
        return match ($this) {
            self::Local => [],
            self::S3 => ['secret'],
        };
    }
}
