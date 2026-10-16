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
 */
final class StorageProviderConnectionTester
{
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
            report($e);

            return [
                'success' => false,
                'message' => 'Connection test failed. Verify the configuration and try again.',
            ];
        }

        if ($result['success']) {
            $reporter = new StorageProviderCapabilityReporter;
            $result['capabilities'] = $reporter->report($driver, $config);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string}
     */
    private function testLocal(array $config): array
    {
        $root = $config['root'] ?? storage_path('app/private');
        if (! is_string($root) || $root === '') {
            $root = storage_path('app/private');
        }

        $disk = Storage::build([
            'driver' => 'local',
            'root' => $root,
            'throw' => true,
        ]);

        $probe = '.storage-provider-probe-'.Str::random(12);
        $disk->put($probe, 'ok');
        $exists = $disk->exists($probe);
        $disk->delete($probe);

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
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string}
     */
    private function testS3(array $config): array
    {
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
        ];

        if (empty($diskConfig['bucket'])) {
            return [
                'success' => false,
                'message' => 'S3 connection test requires a bucket.',
            ];
        }

        $disk = Storage::build($diskConfig);
        $probe = '.storage-provider-probe-'.Str::random(12);

        $disk->put($probe, 'ok');
        $exists = $disk->exists($probe);
        $disk->delete($probe);

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
    }
}
