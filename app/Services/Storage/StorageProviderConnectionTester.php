<?php

namespace App\Services\Storage;

use App\Enums\StorageProviderDriver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Tests connectivity using a submitted (possibly unsaved) configuration.
 *
 * Never persists configuration. Never overwrites an existing provider.
 * Never returns credentials or raw exception messages that may contain secrets.
 *
 * Safety (Phase 1 review):
 * - Local roots must resolve under an allowlisted base (storage_path).
 * - S3 endpoints must be http(s) URLs when provided.
 * - Probe objects are always cleaned up on success and failure.
 * - Network/filesystem operations are bounded where the adapter supports it.
 */
final class StorageProviderConnectionTester
{
    /**
     * Absolute realpath prefixes permitted as local storage roots for probes.
     *
     * @return list<string>
     */
    public static function allowedLocalRootBases(): array
    {
        $bases = [
            storage_path(),
        ];

        $resolved = [];
        foreach ($bases as $base) {
            $real = realpath($base);
            if ($real !== false) {
                $resolved[] = rtrim($real, DIRECTORY_SEPARATOR);
            }
        }

        return $resolved;
    }

    public function __construct(
        private readonly StorageProviderValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  driver, config, secrets, …
     * @return array{success: bool, message: string, capabilities?: array<string, mixed>}
     */
    public function test(array $payload): array
    {
        try {
            $validated = $this->validator->validate($payload, isUpdate: false, forConnectionTest: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return [
                'success' => false,
                'message' => 'Configuration is invalid: '.collect($e->errors())->flatten()->first(),
            ];
        }

        $driver = StorageProviderDriver::from(
            $validated['driver'] instanceof StorageProviderDriver
                ? $validated['driver']->value
                : (string) $validated['driver']
        );

        $config = array_merge($validated['config'] ?? [], $validated['secrets'] ?? []);

        try {
            $result = match ($driver) {
                StorageProviderDriver::Local => $this->testLocal($config),
                StorageProviderDriver::S3 => $this->testS3($config),
            };
        } catch (\Throwable $e) {
            // Do not log full config (may contain secrets). Message only.
            report(new \RuntimeException(
                'Storage provider connection test failed: '.$e->getMessage(),
                previous: $e
            ));

            return [
                'success' => false,
                'message' => 'Connection test failed. Verify the configuration and try again.',
            ];
        }

        if ($result['success']) {
            $reporter = new StorageProviderCapabilityReporter;
            // Capabilities from non-secret config only in the report path used here.
            $safeConfig = $validated['config'] ?? [];
            $result['capabilities'] = $reporter->report($driver, $safeConfig);
        }

        return $result;
    }

    /**
     * Ensure a candidate local root is under an allowlisted base path.
     */
    public function assertSafeLocalRoot(string $root): string
    {
        if ($root === '' || str_contains($root, "\0")) {
            throw new \InvalidArgumentException('Local storage root path is invalid.');
        }

        // Reject path traversal tokens before resolution.
        if (preg_match('#(^|/|\\\\)\.\.(/|\\\\|$)#', $root)) {
            throw new \InvalidArgumentException('Local storage root path is not permitted.');
        }

        $default = storage_path('app/private');
        $candidate = $root === '' ? $default : $root;

        // Resolve relative paths against storage_path for consistency.
        if (! $this->isAbsolutePath($candidate)) {
            $candidate = storage_path(ltrim($candidate, '/\\'));
        }

        // Create the directory if missing so realpath can succeed under storage.
        if (! is_dir($candidate)) {
            @mkdir($candidate, 0755, true);
        }

        $real = realpath($candidate);
        if ($real === false) {
            throw new \InvalidArgumentException('Local storage root path could not be resolved.');
        }

        $allowed = self::allowedLocalRootBases();
        foreach ($allowed as $base) {
            if ($real === $base || str_starts_with($real, $base.DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }

        throw new \InvalidArgumentException('Local storage root path is outside the permitted storage area.');
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string}
     */
    private function testLocal(array $config): array
    {
        $rootInput = $config['root'] ?? storage_path('app/private');
        if (! is_string($rootInput)) {
            return [
                'success' => false,
                'message' => 'Local storage root path is invalid.',
            ];
        }

        try {
            $root = $this->assertSafeLocalRoot($rootInput);
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        $disk = Storage::build([
            'driver' => 'local',
            'root' => $root,
            'throw' => true,
        ]);

        $probe = '.storage-provider-probe-'.Str::random(12);

        try {
            $disk->put($probe, 'ok');
            $exists = $disk->exists($probe);

            if (! $exists) {
                return [
                    'success' => false,
                    'message' => 'Local storage probe write/read did not succeed.',
                ];
            }

            return [
                'success' => true,
                'message' => 'Local storage connection test succeeded.',
            ];
        } finally {
            try {
                if ($disk->exists($probe)) {
                    $disk->delete($probe);
                }
            } catch (\Throwable) {
                // Best-effort cleanup; do not leak secondary errors.
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string}
     */
    private function testS3(array $config): array
    {
        if (! empty($config['endpoint'])) {
            $endpoint = (string) $config['endpoint'];
            if (! $this->isAllowedEndpoint($endpoint)) {
                return [
                    'success' => false,
                    'message' => 'S3 endpoint must be a valid http or https URL.',
                ];
            }
        }

        if (! empty($config['url'])) {
            $url = (string) $config['url'];
            if (! $this->isAllowedEndpoint($url)) {
                return [
                    'success' => false,
                    'message' => 'S3 base URL must be a valid http or https URL.',
                ];
            }
        }

        $diskConfig = [
            'driver' => 's3',
            'key' => $config['key'] ?? null,
            'secret' => $config['secret'] ?? null,
            'region' => $config['region'] ?? 'us-east-1',
            'bucket' => $config['bucket'] ?? null,
            'url' => $config['url'] ?? null,
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => filter_var(
                $config['use_path_style_endpoint'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            ),
            'throw' => true,
            // Bound HTTP client where Laravel/Flysystem passes options through.
            'http' => [
                'timeout' => 10,
                'connect_timeout' => 5,
            ],
        ];

        if (empty($diskConfig['bucket'])) {
            return [
                'success' => false,
                'message' => 'S3 connection test requires a bucket.',
            ];
        }

        $disk = Storage::build($diskConfig);
        $probe = '.storage-provider-probe-'.Str::random(12);

        try {
            $disk->put($probe, 'ok');
            $exists = $disk->exists($probe);

            if (! $exists) {
                return [
                    'success' => false,
                    'message' => 'S3-compatible storage probe write/read did not succeed.',
                ];
            }

            return [
                'success' => true,
                'message' => 'S3-compatible storage connection test succeeded.',
            ];
        } finally {
            try {
                if ($disk->exists($probe)) {
                    $disk->delete($probe);
                }
            } catch (\Throwable) {
                // Best-effort cleanup.
            }
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        // Unix absolute or Windows drive letter.
        return str_starts_with($path, '/')
            || (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':' );
    }

    private function isAllowedEndpoint(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        // Reject obviously internal-only schemes already filtered; block credentials in URL.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        return true;
    }
}
