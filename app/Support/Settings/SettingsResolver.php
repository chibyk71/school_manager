<?php

namespace App\Support\Settings;

use App\Support\Settings\Exceptions\InvalidSettingValueException;
use App\Support\Settings\Exceptions\InvalidStoredSettingValueException;
use App\Support\Settings\Exceptions\UnknownSettingException;
use Illuminate\Support\Facades\Validator;
use OiLab\OiLaravelSettings\Facades\Settings as OiSettings;
use OiLab\OiLaravelSettings\SettingsManager;

/**
 * School Manager boundary around OI Laravel Settings.
 *
 * Reads use exact-scope isolation (OI own()) so global/null-scope values cannot
 * override code-owned registry defaults for school-scoped pilot keys.
 * Writes validate against the definition before persistence.
 */
final class SettingsResolver
{
    public function __construct(
        private SettingsRegistry $registry,
    ) {}

    /**
     * Resolve a setting for the exact requested scope.
     *
     * 1. Registry definition (throws for unknown key)
     * 2. Exact-scope override via OI own()
     * 3. Registry default when no exact-scope row exists
     *
     * @throws UnknownSettingException
     * @throws InvalidStoredSettingValueException
     */
    public function get(string $key, string $scope): mixed
    {
        $definition = $this->registry->get($key);

        $sentinel = new \stdClass;
        $stored = OiSettings::own($key, $sentinel, $scope);

        if ($stored === $sentinel) {
            return $definition->default;
        }

        return $this->assertStoredValueMatchesType($key, $scope, $stored, $definition);
    }

    /**
     * Persist a validated value at the exact requested scope.
     *
     * @throws UnknownSettingException
     * @throws InvalidSettingValueException
     */
    public function set(string $key, mixed $value, string $scope): void
    {
        $definition = $this->registry->get($key);
        $normalized = $this->validateAndNormalize($key, $value, $definition);

        OiSettings::set(
            $key,
            $normalized,
            type: $definition->type,
            label: $definition->label,
            scope: $scope,
        );
    }

    /**
     * Remove the override for the exact supplied scope.
     * Subsequent get() returns the registry default unless a new override exists.
     *
     * @throws UnknownSettingException
     */
    public function forget(string $key, string $scope): void
    {
        $this->registry->get($key);

        OiSettings::delete($key, $scope);
    }

    /**
     * @throws InvalidSettingValueException
     */
    private function validateAndNormalize(string $key, mixed $value, SettingDefinition $definition): mixed
    {
        return match ($definition->type) {
            'integer' => $this->validateInteger($key, $value),
            'boolean' => $this->validateBoolean($key, $value),
            'string' => $this->validateString($key, $value),
            default => throw new InvalidSettingValueException(
                $key,
                "Unsupported type [{$definition->type}]."
            ),
        };
    }

    /**
     * @throws InvalidSettingValueException
     */
    private function validateInteger(string $key, mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $validator = Validator::make(
                ['value' => $value],
                ['value' => ['required', 'integer']]
            );

            if ($validator->fails()) {
                throw new InvalidSettingValueException(
                    $key,
                    'Must be an integer (PHP int or string accepted by Laravel integer rule).',
                    $value
                );
            }

            // Reject floats disguised as strings like "60.0"
            if (str_contains($value, '.') || str_contains(strtolower($value), 'e')) {
                throw new InvalidSettingValueException(
                    $key,
                    'Must be an integer; fractional or scientific notation is not allowed.',
                    $value
                );
            }

            return (int) $value;
        }

        throw new InvalidSettingValueException(
            $key,
            'Must be an integer (PHP int or string accepted by Laravel integer rule).',
            $value
        );
    }

    /**
     * @throws InvalidSettingValueException
     */
    private function validateBoolean(string $key, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        // Laravel boolean rule accepts: true, false, 1, 0, "1", "0", "true", "false", "on", "off"
        $validator = Validator::make(
            ['value' => $value],
            ['value' => ['required', 'boolean']]
        );

        if ($validator->fails()) {
            throw new InvalidSettingValueException(
                $key,
                'Must be a boolean (true/false, 1/0, "true"/"false", "on"/"off").',
                $value
            );
        }

        // Normalize using the same mapping Laravel uses after validation
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? (bool) $value;
    }

    /**
     * @throws InvalidSettingValueException
     */
    private function validateString(string $key, mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidSettingValueException(
                $key,
                'Must be a string.',
                $value
            );
        }

        return $value;
    }

    /**
     * @throws InvalidStoredSettingValueException
     */
    private function assertStoredValueMatchesType(
        string $key,
        string $scope,
        mixed $stored,
        SettingDefinition $definition,
    ): mixed {
        $ok = match ($definition->type) {
            'integer' => is_int($stored),
            'boolean' => is_bool($stored),
            'string' => is_string($stored),
            default => false,
        };

        if (! $ok) {
            throw new InvalidStoredSettingValueException(
                $key,
                $scope,
                "Stored value does not match declared type [{$definition->type}].",
                $stored
            );
        }

        return $stored;
    }
}
