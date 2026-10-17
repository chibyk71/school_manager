<?php

namespace App\Services\Storage;

use App\Enums\StorageProviderDriver;
use App\Enums\StorageProviderOwnership;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates storage provider configuration for create/update and connection tests.
 */
final class StorageProviderValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $data, bool $isUpdate = false, bool $forConnectionTest = false): array
    {
        $driverRule = ['required', Rule::enum(StorageProviderDriver::class)];
        $ownershipRule = ['required', Rule::enum(StorageProviderOwnership::class)];

        if ($isUpdate && ! $forConnectionTest) {
            // Partial updates allowed for name/enabled/config; driver/ownership immutable after create.
            $rules = [
                'name' => ['sometimes', 'string', 'max:255'],
                'enabled' => ['sometimes', 'boolean'],
                'config' => ['sometimes', 'array'],
                'secrets' => ['sometimes', 'array'],
                'clear_secrets' => ['sometimes', 'boolean'],
            ];
        } else {
            $rules = [
                'name' => [$forConnectionTest ? 'nullable' : 'required', 'string', 'max:255'],
                'driver' => $driverRule,
                'ownership' => $ownershipRule,
                'school_id' => ['nullable', 'uuid'],
                'config' => ['nullable', 'array'],
                'secrets' => ['nullable', 'array'],
                'enabled' => ['sometimes', 'boolean'],
                'clear_secrets' => ['sometimes', 'boolean'],
            ];
        }

        $validator = Validator::make($data, $rules);
        $validated = $validator->validate();

        $driver = isset($validated['driver'])
            ? StorageProviderDriver::from($validated['driver'] instanceof StorageProviderDriver
                ? $validated['driver']->value
                : (string) $validated['driver'])
            : null;

        $ownership = isset($validated['ownership'])
            ? StorageProviderOwnership::from($validated['ownership'] instanceof StorageProviderOwnership
                ? $validated['ownership']->value
                : (string) $validated['ownership'])
            : null;

        if ($ownership !== null) {
            if ($ownership->requiresSchool() && empty($validated['school_id'] ?? null)) {
                throw ValidationException::withMessages([
                    'school_id' => 'School-owned providers require a school_id.',
                ]);
            }
            if ($ownership->forbidsSchool() && ! empty($validated['school_id'] ?? null)) {
                throw ValidationException::withMessages([
                    'school_id' => 'System-owned providers must not include a school_id.',
                ]);
            }
        }

        if ($driver !== null) {
            $this->validateDriverConfig($driver, $validated['config'] ?? [], $validated['secrets'] ?? [], $forConnectionTest);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $secrets
     */
    private function validateDriverConfig(
        StorageProviderDriver $driver,
        array $config,
        array $secrets,
        bool $forConnectionTest
    ): void {
        $messages = [];

        if ($driver === StorageProviderDriver::S3) {
            if (empty($config['bucket'] ?? null) && empty($config['key'] ?? null) === false) {
                // key without bucket is incomplete
            }
            if (empty($config['bucket'] ?? null)) {
                $messages['config.bucket'] = 'S3 providers require a bucket.';
            }
            if (empty($config['region'] ?? null) && empty($config['endpoint'] ?? null)) {
                $messages['config.region'] = 'S3 providers require a region (or a custom endpoint).';
            }
            // Secret required on create/test when not already stored; update may omit.
            if ($forConnectionTest && empty($secrets['secret'] ?? null)) {
                // For pure connection tests the caller may supply secret; if missing we still allow
                // validation to pass so the connection tester can report a clear failure.
            }
        }

        if ($driver === StorageProviderDriver::Local) {
            // root is optional; defaults to a safe application path when resolved.
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }
}
