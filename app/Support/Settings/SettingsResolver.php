<?php

namespace App\Support\Settings;

use App\Support\Settings\Exceptions\InvalidSettingValueException;
use App\Support\Settings\Exceptions\InvalidStoredSettingValueException;
use App\Support\Settings\Exceptions\UnknownSettingException;
use OiLab\OiLaravelSettings\Facades\Settings as OiSettings;
use OiLab\OiLaravelSettings\Models\Setting as OiSetting;
use OiLab\OiLaravelSettings\OiLaravelSettings;

/**
 * School Manager boundary around OI Laravel Settings.
 *
 * Reads use exact-scope isolation so global/null-scope values cannot override
 * code-owned registry defaults for school-scoped pilot keys.
 * Writes validate against the definition before persistence.
 *
 * Corrupt stored payloads are detected from the raw DB string before OI casting
 * can coerce them into apparently valid PHP types.
 */
final class SettingsResolver
{
    /**
     * Boolean representations accepted by the approved Phase 1 contract.
     *
     * @var list<mixed>
     */
    private const BOOLEAN_TRUE = [true, 1, '1', 'true', 'on'];

    /**
     * @var list<mixed>
     */
    private const BOOLEAN_FALSE = [false, 0, '0', 'false', 'off'];

    public function __construct(
        private SettingsRegistry $registry,
    ) {}

    /**
     * Resolve a setting for the exact requested scope.
     *
     * 1. Registry definition (throws for unknown key)
     * 2. Exact-scope row only (no global fallback)
     * 3. Registry default when no exact-scope row exists
     * 4. Invalid raw stored values throw rather than silently coercing
     *
     * @throws UnknownSettingException
     * @throws InvalidStoredSettingValueException
     */
    public function get(string $key, string $scope): mixed
    {
        $definition = $this->registry->get($key);

        $row = $this->exactScopeRow($key, $scope);

        if ($row === null) {
            return $definition->default;
        }

        $raw = $row->getRawOriginal('value');

        return $this->decodeValidatedRaw($key, $scope, $raw, $definition);
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
     * Load the settings row for the exact scope only (no global inheritance).
     */
    private function exactScopeRow(string $key, string $scope): ?OiSetting
    {
        $model = OiLaravelSettings::settingModel();

        /** @var OiSetting|null $row */
        $row = $model::query()
            ->where('scope', $scope)
            ->where('key', $key)
            ->first();

        return $row;
    }

    /**
     * Validate the raw DB payload against the definition type, then return the
     * correctly typed runtime value. Detects corruption that OI's (int)/(bool)
     * casts would otherwise hide.
     *
     * @throws InvalidStoredSettingValueException
     */
    private function decodeValidatedRaw(
        string $key,
        string $scope,
        mixed $raw,
        SettingDefinition $definition,
    ): mixed {
        if ($raw === null) {
            throw new InvalidStoredSettingValueException(
                $key,
                $scope,
                'Stored value is null.',
                $raw
            );
        }

        if (! is_string($raw) && ! is_numeric($raw)) {
            throw new InvalidStoredSettingValueException(
                $key,
                $scope,
                'Stored value must be a scalar string payload.',
                $raw
            );
        }

        $rawString = is_string($raw) ? $raw : (string) $raw;

        return match ($definition->type) {
            'integer' => $this->decodeStoredInteger($key, $scope, $rawString),
            'boolean' => $this->decodeStoredBoolean($key, $scope, $rawString),
            'string' => $rawString,
            default => throw new InvalidStoredSettingValueException(
                $key,
                $scope,
                "Unsupported type [{$definition->type}].",
                $raw
            ),
        };
    }

    /**
     * @throws InvalidStoredSettingValueException
     */
    private function decodeStoredInteger(string $key, string $scope, string $raw): int
    {
        // OI encodes integers as decimal digit strings. Reject anything else
        // (including "60.0", "1e2", "", "abc") before PHP's (int) cast can coerce.
        if (! preg_match('/^-?\d+$/', $raw)) {
            throw new InvalidStoredSettingValueException(
                $key,
                $scope,
                'Stored integer payload is malformed.',
                $raw
            );
        }

        return (int) $raw;
    }

    /**
     * @throws InvalidStoredSettingValueException
     */
    private function decodeStoredBoolean(string $key, string $scope, string $raw): bool
    {
        // OI encodes booleans as "1" / "0". Accept those and common string forms
        // that may appear from older writers; reject everything else.
        $normalized = strtolower($raw);

        if (in_array($normalized, ['1', 'true', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'off'], true)) {
            return false;
        }

        throw new InvalidStoredSettingValueException(
            $key,
            $scope,
            'Stored boolean payload is malformed.',
            $raw
        );
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
     * Accept only PHP int or a pure digit string (Laravel integer rule subset
     * without float coercion). Rejects 60.0, "60.0", arrays, objects.
     *
     * @throws InvalidSettingValueException
     */
    private function validateInteger(string $key, mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        throw new InvalidSettingValueException(
            $key,
            'Must be an integer (PHP int or pure digit string).',
            $value
        );
    }

    /**
     * Accept only the approved Phase 1 boolean whitelist and normalize to bool.
     *
     * @throws InvalidSettingValueException
     */
    private function validateBoolean(string $key, mixed $value): bool
    {
        // Strict membership: use === so "1" !== 1 is handled by listing both forms.
        foreach (self::BOOLEAN_TRUE as $accepted) {
            if ($value === $accepted) {
                return true;
            }
        }

        foreach (self::BOOLEAN_FALSE as $accepted) {
            if ($value === $accepted) {
                return false;
            }
        }

        throw new InvalidSettingValueException(
            $key,
            'Must be a boolean (true/false, 1/0, "1"/"0", "true"/"false", "on"/"off").',
            $value
        );
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
}
