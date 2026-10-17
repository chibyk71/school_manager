<?php

namespace App\Services\Storage;

use App\Enums\StorageProviderDriver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Tests connectivity using a submitted (possibly unsaved) configuration.
 *
 * Never persists configuration. Never overwrites an existing provider.
 * Never returns credentials or raw exception messages that may contain secrets.
 *
 * Safety:
 * - Local roots must exist and resolve under an allowlisted base (storage_path).
 * - Nonexistent local roots are rejected; directories are never created; ancestors
 *   are never substituted for the configured path.
 * - S3 endpoints/URLs: http(s) only, no URL credentials, no private/loopback/
 *   link-local/metadata targets (hostname + resolved DNS). Redirects disabled
 *   on the probe HTTP client so they cannot bypass the policy.
 * - Probe objects are always cleaned up on success and failure.
 * - Failures log a static diagnostic only (no exception message, no config).
 *
 * Private / internal S3-compatible deployments can be explicitly allowlisted
 * via config('filesystems.connection_test_allowed_hosts') (exact hostnames).
 */
final class StorageProviderConnectionTester
{
    public static function allowedLocalRootBases(): array
    {
        $bases = [storage_path()];
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

    public function test(array $payload): array
    {
        try {
            $validated = $this->validator->validate($payload, isUpdate: false, forConnectionTest: true);
        } catch (ValidationException $e) {
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
        } catch (Throwable) {
            Log::warning('Storage provider connection test failed.');

            return [
                'success' => false,
                'message' => 'Connection test failed. Verify the configuration and try again.',
            ];
        }

        if ($result['success']) {
            $reporter = new StorageProviderCapabilityReporter;
            $safeConfig = $validated['config'] ?? [];
            $result['capabilities'] = $reporter->report($driver, $safeConfig);
        }

        return $result;
    }

    /**
     * Canonicalize an existing local root and ensure it lies under an allowed base.
     *
     * Nonexistent paths are always rejected. This method never creates directories
     * and never substitutes an ancestor for the configured root (so a connection
     * test cannot report success against a different directory).
     */
    public function assertSafeLocalRoot(string $root): string
    {
        if ($root === '' || str_contains($root, "\0")) {
            throw new InvalidArgumentException('Local storage root path is invalid.');
        }

        if (preg_match('#(^|/|\\\\)\.\.(/|\\\\|$)#', $root)) {
            throw new InvalidArgumentException('Local storage root path is not permitted.');
        }

        $bases = self::allowedLocalRootBases();
        if ($bases === []) {
            throw new InvalidArgumentException('No permitted local storage base is available.');
        }

        $candidate = $root;
        if (! $this->isAbsolutePath($candidate)) {
            $candidate = storage_path(ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate), '/\\'));
        }

        $candidate = $this->normalizePathSeparators($candidate);

        if (! is_dir($candidate) && ! is_link($candidate)) {
            throw new InvalidArgumentException('Local storage root path does not exist.');
        }

        $real = realpath($candidate);
        if ($real === false || ! is_dir($real)) {
            throw new InvalidArgumentException('Local storage root path could not be resolved.');
        }

        if (! $this->isPathUnderAllowedBase($real, $bases)) {
            throw new InvalidArgumentException('Local storage root path is outside the permitted storage area.');
        }

        return $real;
    }

    private function isPathUnderAllowedBase(string $realPath, array $bases): bool
    {
        foreach ($bases as $base) {
            if ($realPath === $base || str_starts_with($realPath, $base.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePathSeparators(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $path = preg_replace('#'.preg_quote(DIRECTORY_SEPARATOR, '#').'{2,}#', DIRECTORY_SEPARATOR, $path) ?? $path;

        return rtrim($path, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
    }

    public function endpointIsAllowed(string $url): bool
    {
        return $this->isAllowedEndpoint($url);
    }

    private function testLocal(array $config): array
    {
        $rootInput = $config['root'] ?? storage_path('app/private');
        if (! is_string($rootInput)) {
            return ['success' => false, 'message' => 'Local storage root path is invalid.'];
        }

        try {
            $root = $this->assertSafeLocalRoot($rootInput);
        } catch (InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $disk = Storage::build(['driver' => 'local', 'root' => $root, 'throw' => true]);
        $probe = '.storage-provider-probe-'.Str::random(12);

        try {
            $disk->put($probe, 'ok');
            $exists = $disk->exists($probe);
            if (! $exists) {
                return ['success' => false, 'message' => 'Local storage probe write/read did not succeed.'];
            }

            return ['success' => true, 'message' => 'Local storage connection test succeeded.'];
        } finally {
            try {
                if ($disk->exists($probe)) {
                    $disk->delete($probe);
                }
            } catch (Throwable) {
            }
        }
    }

    private function testS3(array $config): array
    {
        if (! empty($config['endpoint']) && ! $this->isAllowedEndpoint((string) $config['endpoint'])) {
            return ['success' => false, 'message' => 'S3 endpoint is not permitted for connection testing.'];
        }
        if (! empty($config['url']) && ! $this->isAllowedEndpoint((string) $config['url'])) {
            return ['success' => false, 'message' => 'S3 base URL is not permitted for connection testing.'];
        }

        $diskConfig = [
            'driver' => 's3',
            'key' => $config['key'] ?? null,
            'secret' => $config['secret'] ?? null,
            'region' => $config['region'] ?? 'us-east-1',
            'bucket' => $config['bucket'] ?? null,
            'url' => $config['url'] ?? null,
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => filter_var($config['use_path_style_endpoint'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'throw' => true,
            'http' => ['timeout' => 10, 'connect_timeout' => 5, 'allow_redirects' => false],
        ];

        if (empty($diskConfig['bucket'])) {
            return ['success' => false, 'message' => 'S3 connection test requires a bucket.'];
        }

        $disk = Storage::build($diskConfig);
        $probe = '.storage-provider-probe-'.Str::random(12);

        try {
            $disk->put($probe, 'ok');
            $exists = $disk->exists($probe);
            if (! $exists) {
                return ['success' => false, 'message' => 'S3-compatible storage probe write/read did not succeed.'];
            }

            return ['success' => true, 'message' => 'S3-compatible storage connection test succeeded.'];
        } finally {
            try {
                if ($disk->exists($probe)) {
                    $disk->delete($probe);
                }
            } catch (Throwable) {
            }
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return str_starts_with($path, '/')
            || (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':');
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
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host === '') {
            return false;
        }
        $allowlist = config('filesystems.connection_test_allowed_hosts', []);
        if (is_array($allowlist) && $allowlist !== []) {
            $normalized = array_map(static fn ($h) => strtolower(rtrim((string) $h, '.')), $allowlist);
            if (in_array($host, $normalized, true)) {
                return true;
            }
        }
        if ($this->isBlockedHostname($host)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! $this->isProhibitedIp($host);
        }
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if ($records === false || $records === []) {
            $ipv4 = @gethostbynamel($host);
            if ($ipv4 === false || $ipv4 === []) {
                return false;
            }
            foreach ($ipv4 as $ip) {
                if ($this->isProhibitedIp($ip)) {
                    return false;
                }
            }

            return true;
        }
        $sawAddress = false;
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($ip) || $ip === '') {
                continue;
            }
            $sawAddress = true;
            if ($this->isProhibitedIp($ip)) {
                return false;
            }
        }

        return $sawAddress;
    }

    private function isBlockedHostname(string $host): bool
    {
        $blockedExact = [
            'localhost', 'localhost.localdomain', 'metadata.google.internal',
            'metadata', 'kubernetes.default', 'kubernetes.default.svc',
        ];
        if (in_array($host, $blockedExact, true)) {
            return true;
        }
        if (str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return true;
        }

        return $host === '169.254.169.254';
    }

    public function isProhibitedIp(string $ip): bool
    {
        $ip = strtolower(trim($ip, '[]'));
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->isProhibitedIpv4($ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->isProhibitedIpv6($ip);
        }

        return true;
    }

    private function isProhibitedIpv4(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }
        $long = ip2long($ip);
        if ($long === false) {
            return true;
        }
        if (($long & 0xffff0000) === 0xa9fe0000) {
            return true;
        }
        if (($long & 0xffc00000) === 0x64400000) {
            return true;
        }
        if (($long & 0xff000000) === 0) {
            return true;
        }

        return false;
    }

    private function isProhibitedIpv6(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }
        $packed = inet_pton($ip);
        if ($packed === false) {
            return true;
        }
        if ($ip === '::1') {
            return true;
        }
        $b0 = ord($packed[0]);
        $b1 = ord($packed[1]);
        if ($b0 === 0xfe && ($b1 & 0xc0) === 0x80) {
            return true;
        }
        if (($b0 & 0xfe) === 0xfc) {
            return true;
        }

        return false;
    }
}
