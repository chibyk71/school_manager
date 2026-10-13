<?php

namespace App\Support\Settings;

use App\Support\Settings\Exceptions\InvalidSettingDefinitionException;
use App\Support\Settings\Exceptions\UnknownSettingException;

/**
 * Code-owned registry of setting definitions and defaults.
 *
 * Does not read from or write to the database.
 */
final class SettingsRegistry
{
    /** @var array<string, SettingGroupDefinition> */
    private array $groups = [];

    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    private bool $sealed = false;

    public function registerGroup(SettingGroupDefinition $group): void
    {
        if ($this->sealed) {
            throw new InvalidSettingDefinitionException(
                null,
                "Cannot register group [{$group->key}] after the registry has been sealed."
            );
        }

        if (isset($this->groups[$group->key])) {
            throw new InvalidSettingDefinitionException(
                null,
                "Duplicate group key [{$group->key}]."
            );
        }

        if ($group->key === '') {
            throw new InvalidSettingDefinitionException(null, 'Group key must not be empty.');
        }

        if ($group->label === '') {
            throw new InvalidSettingDefinitionException(null, "Group [{$group->key}] label must not be empty.");
        }

        foreach ($group->definitions as $definition) {
            $this->assertValidDefinition($definition);

            if (isset($this->definitions[$definition->key])) {
                throw new InvalidSettingDefinitionException(
                    $definition->key,
                    'Duplicate setting key.'
                );
            }

            if ($definition->groupKey !== $group->key) {
                throw new InvalidSettingDefinitionException(
                    $definition->key,
                    "Group key mismatch: definition has [{$definition->groupKey}], group is [{$group->key}]."
                );
            }
        }

        $this->groups[$group->key] = $group;

        foreach ($group->definitions as $definition) {
            $this->definitions[$definition->key] = $definition;
        }
    }

    /**
     * Fluent group registration used by definition registrars.
     *
     * @param  callable(SettingGroupRegistrar $group): void  $callback
     */
    public function group(string $key, string $label, callable $callback): void
    {
        $registrar = new SettingGroupRegistrar($key, $label);
        $callback($registrar);
        $this->registerGroup($registrar->toGroupDefinition());
    }

    public function get(string $key): SettingDefinition
    {
        if (! isset($this->definitions[$key])) {
            throw new UnknownSettingException($key);
        }

        return $this->definitions[$key];
    }

    public function find(string $key): ?SettingDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function forGroup(string $group): array
    {
        if (! isset($this->groups[$group])) {
            return [];
        }

        return $this->groups[$group]->definitions;
    }

    /**
     * @return array<string, array<string, SettingDefinition>>
     */
    public function allGrouped(): array
    {
        $result = [];

        foreach ($this->groups as $key => $group) {
            $result[$key] = $group->definitions;
        }

        return $result;
    }

    /**
     * Prevent further group registration after boot.
     */
    public function seal(): void
    {
        $this->sealed = true;
    }

    public function isSealed(): bool
    {
        return $this->sealed;
    }

    private function assertValidDefinition(SettingDefinition $definition): void
    {
        if ($definition->key === '') {
            throw new InvalidSettingDefinitionException(null, 'Setting key must not be empty.');
        }

        if ($definition->label === '') {
            throw new InvalidSettingDefinitionException($definition->key, 'Label must not be empty.');
        }

        $allowedTypes = ['integer', 'boolean', 'string'];
        if (! in_array($definition->type, $allowedTypes, true)) {
            throw new InvalidSettingDefinitionException(
                $definition->key,
                "Unsupported type [{$definition->type}]."
            );
        }

        if (! $this->defaultMatchesType($definition->default, $definition->type)) {
            throw new InvalidSettingDefinitionException(
                $definition->key,
                "Default value is incompatible with type [{$definition->type}]."
            );
        }
    }

    private function defaultMatchesType(mixed $default, string $type): bool
    {
        return match ($type) {
            'integer' => is_int($default),
            'boolean' => is_bool($default),
            'string' => is_string($default),
            default => false,
        };
    }
}

/**
 * Collects definitions for a single group during registration.
 */
final class SettingGroupRegistrar
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    public function __construct(
        private string $groupKey,
        private string $groupLabel,
    ) {}

    public function define(string $key): SettingDefinitionBuilder
    {
        return new SettingDefinitionBuilder(
            $key,
            $this->groupKey,
            $this->groupLabel,
            function (SettingDefinition $definition): void {
                if (isset($this->definitions[$definition->key])) {
                    throw new InvalidSettingDefinitionException(
                        $definition->key,
                        'Duplicate setting key within group.'
                    );
                }
                $this->definitions[$definition->key] = $definition;
            }
        );
    }

    public function toGroupDefinition(): SettingGroupDefinition
    {
        return new SettingGroupDefinition(
            $this->groupKey,
            $this->groupLabel,
            $this->definitions,
        );
    }
}

/**
 * Fluent builder for one setting definition.
 */
final class SettingDefinitionBuilder
{
    private ?string $type = null;

    private mixed $default = null;

    private bool $hasDefault = false;

    private ?string $label = null;

    private ?string $description = null;

    private ?string $intendedScope = null;

    private bool $committed = false;

    /**
     * @param  callable(SettingDefinition): void  $onComplete
     */
    public function __construct(
        private string $key,
        private string $groupKey,
        private string $groupLabel,
        private $onComplete,
    ) {}

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        $this->hasDefault = true;

        return $this;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function intendedScope(string $scope): self
    {
        $this->intendedScope = $scope;
        $this->commit();

        return $this;
    }

    /**
     * Explicitly finish the definition when intendedScope is not used.
     */
    public function register(): void
    {
        $this->commit();
    }

    private function commit(): void
    {
        if ($this->committed) {
            return;
        }

        if ($this->type === null) {
            throw new InvalidSettingDefinitionException($this->key, 'Type is required.');
        }

        if (! $this->hasDefault) {
            throw new InvalidSettingDefinitionException($this->key, 'Default is required.');
        }

        if ($this->label === null || $this->label === '') {
            throw new InvalidSettingDefinitionException($this->key, 'Label is required.');
        }

        $definition = new SettingDefinition(
            key: $this->key,
            groupKey: $this->groupKey,
            groupLabel: $this->groupLabel,
            type: $this->type,
            default: $this->default,
            label: $this->label,
            description: $this->description,
            intendedScope: $this->intendedScope,
        );

        ($this->onComplete)($definition);
        $this->committed = true;
    }

    public function __destruct()
    {
        if (! $this->committed && ($this->type !== null || $this->hasDefault || $this->label !== null)) {
            // Auto-commit if the chain completed without intendedScope()/register().
            // Incomplete chains still throw from commit().
            try {
                $this->commit();
            } catch (InvalidSettingDefinitionException) {
                // Swallow in destructor to avoid fatal errors during stack unwind;
                // incomplete definitions simply will not be registered.
            }
        }
    }
}
