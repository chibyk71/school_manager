<?php

namespace App\Services\Storage;

use App\Enums\StorageProviderDriver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Tests connectivity using a submitted (possibly unsaved) configuration.
 *
 * Never persists configuration. Never overwrites an existing provider.
 * Never returns credentials or raw exception messages that may contain secrets.
 *
 * Safety:
 * - Local roots must resolve under an allowlisted base (storage_path).
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
        } catch (\Throwable) {
            // Static diagnostic only — no exception message, previous, or config.
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
     * Ensure a candidate local root is under an allowlisted base path.
     */
    public function assertSafeLocalRoot(string $root): string
    {
        if ($root === '' || str_contains($root, "\0")) {
            throw new \InvalidArgumentException('Local storage root path is invalid.');
        }

        if (preg_match('#(^|/|\\\\)\.\.(/|\\\\|$)#', $root)) {
            throw new \InvalidArgumentException('Local storage root path is not permitted.');
        }

        $candidate = $root;
        if (! $this->isAbsolutePath($candidate)) {
            $candidate = storage_path(ltrim($candidate, '/\\'));
        }

        if (! is_dir($candidate)) {
            @mkdir($candidate, 0755, true);
        }

        $real = realpath($candidate);
        if ($real === false) {
            throw new \InvalidArgumentException('Local storage root path could not be resolved.');
        }

        foreach (self::allowedLocalRootBases() as $base) {
            if ($real === $base || str_starts_with($real, $base.DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }

        throw new \InvalidArgumentException('Local storage root path is outside the permitted storage area.');
    }

    /**
     * Public entry for endpoint policy checks (used by tests).
     */
    public function endpointIsAllowed(string $url): bool
    {
        return $this->isAllowedEndpoint($url);
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
                // Best-effort cleanup.
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
                    'message' => 'S3 endpoint is not permitted for connection testing.',
                ];
            }
        }

        if (! empty($config['url'])) {
            $url = (string) $config['url'];
            if (! $this->isAllowedEndpoint($url)) {
                return [
                    'success' => false,
                    'message' => 'S3 base URL is not permitted for connection testing.',
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
            // Bound + no redirects so redirect targets cannot bypass endpoint policy.
            'http' => [
                'timeout' => 10,
                'connect_timeout' => 5,
                'allow_redirects' => false,
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

        return str_starts_with($path, '/')
            || (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':');
    }

    /**
     * Defensible endpoint policy for connection-test probes.
     *
     * - http/https only, no userinfo in URL
     * - hostname not in blocked names (localhost, metadata, etc.)
     * - literal IP not private/loopback/link-local/unique-local
     * - DNS A/AAAA records (when resolvable) must not be private; failure to
     *   resolve is treated as not allowed for custom endpoints
     * - optional exact-hostname allowlist from config for private deployments
     *
     * Limitation: the AWS SDK / Flysystem stack may still perform its own DNS
     * at request time (TOCTOU/rebinding). Disabling redirects reduces one bypass
     * class; full request-time IP pinning is not available without a custom
     * HTTP handler. Operators should prefer public endpoints or explicit allowlists.
     */
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

        // Explicit allowlist for private / internal S3-compatible deployments.
        $allowlist = config('filesystems.connection_test_allowed_hosts', []);
        if (is_array($allowlist) && $allowlist !== []) {
            $normalized = array_map(
                static fn ($h) => strtolower(rtrim((string) $h, '.')),
                $allowlist
            );
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

        // Resolve DNS; reject if any record is prohibited or resolution fails.
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if ($records === false || $records === []) {
            // Also try gethostbynamel as a fallback for environments without dns_get_record.
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
            'localhost',
            'localhost.localdomain',
            'metadata.google.internal',
            'metadata',
            'kubernetes.default',
            'kubernetes.default.svc',
        ];

        if (in_array($host, $blockedExact, true)) {
            return true;
        }

        // Link-local / metadata hostnames commonly used across clouds.
        if (str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return true;
        }

        if ($host === '169.254.169.254' || $host === 'metadata.google.internal') {
            return true;
        }

        return false;
    }

    /**
     * True when the IP must not be contacted by connection-test probes.
     */
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
        // FILTER_FLAG_NO_PRIV_RANGE | NO_RES_RANGE rejects private + reserved,
        // but not all link-local / carrier-grade NAT; add explicit checks.
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        $long = ip2long($ip);
        if ($long === false) {
            return true;
        }

        // 169.254.0.0/16 link-local (includes cloud metadata 169.254.169.254)
        if (($long & 0xFFFF0000) === 0xA9FE0000) {
            return true;
        }

        // 100.64.0.0/10 carrier-grade NAT
        if (($long & 0xFFC00000) === 0x64400000) {
            return true;
        }

        // 0.0.0.0/8
        if (($long & 0xFF000000) === 0) {
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

        // ::1 loopback already excluded by NO_RES_RANGE in most builds; be explicit.
        if ($ip === '::1') {
            return true;
        }

        // fe80::/10 link-local
        $b0 = ord($packed[0]);
        $b1 = ord($packed[1]);
        if ($b0 === 0xfe && ($b1 & 0xc0) === 0x80) {
            return true;
        }

        // fc00::/7 unique local
        if (($b0 & 0xfe) === 0xfc) {
            return true;
        }

        return false;
    }
}
