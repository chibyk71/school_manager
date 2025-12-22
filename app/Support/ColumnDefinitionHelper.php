<?php

namespace App\Support;

use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use UnitEnum;
use BackedEnum;

/**
 * ColumnDefinitionHelper v4.1 – Enhanced with merged extraFields override support
 *
 * This class automatically generates PrimeVue-compatible column definitions from Eloquent models.
 * It supports:
 * - Base fields from fillable or all table columns
 * - Visibility controls: fully hidden and default hidden columns
 * - Relation fields with dot notation
 * - Configurable options via HasConfig trait
 * - Cast-based filter type detection (booleans, dates, numbers, enums)
 * - DB enum column detection for dropdowns
 * - PHP enum class support for options
 * - Caching for performance in multi-tenant setups
 *
 * Key improvement in v4.1: extraFields now merge with base field configs instead of fully replacing them.
 * This allows selective overrides (e.g., change header or sortable status) while retaining auto-detected properties.
 */
final class ColumnDefinitionHelper
{
    /**
     * Generate column definitions from a model instance.
     *
     * @param Model $model The Eloquent model to derive columns from
     * @param array $extraFields Additional/override fields (now supports merging with base)
     * @param bool $includeNonFillable Whether to include all DB columns (not just fillable)
     * @return array Array of column definitions
     */
    public static function fromModel(Model $model, array $extraFields = [], bool $includeNonFillable = false): array
    {
        // Fetch current school for multi-tenant caching (assumes GetSchoolModel() exists)
        $school = getSchoolModel();

        // Retrieve fully hidden columns from model (via trait method if available)
        $fullyHidden = method_exists($model, 'getHiddenTableColumns')
            ? $model->getHiddenTableColumns()
            : [];

        // Retrieve default hidden columns (sent to frontend but initially hidden)
        $defaultHidden = method_exists($model, 'getDefaultHiddenColumns')
            ? $model->getDefaultHiddenColumns()
            : [];

        // Cache key unique per table and school (prevents cache pollution in multi-tenant apps)
        $cacheKey = 'datatable_schema_' . $model->getTable() . '_' . ($school?->id ?? 'global');

        // Cache database schema to avoid repeated queries (24 hours for stability)
        $tableColumns = Cache::remember($cacheKey, now()->addHours(24), function () use ($model) {
            return Schema::getColumnListing($model->getTable());
        });

        // Determine base fields: fillable (safe defaults) or all columns if including non-fillable
        $baseFields = $includeNonFillable
            ? $tableColumns
            : $model->getFillable();

        // Normalize extraFields input for consistent handling
        // Handles shorthand strings ('field') or full configs ('field' => [...])
        $normalizedExtra = [];
        foreach ($extraFields as $key => $value) {
            if (is_numeric($key) && is_string($value)) {
                // Shorthand: 'field_name' → ['field' => 'field_name']
                $normalizedExtra[$value] = ['field' => $value];
            } elseif (is_string($key)) {
                // Full config: 'field' => [options] – ensure 'field' is set
                $config = is_array($value) ? $value : [];
                $config['field'] = $key;
                $normalizedExtra[$key] = $config;
            }
        }

        // Build final field list with proper merging and full exclusion support
        $fields = [];

        // First: Process base fields
        foreach ($baseFields as $baseField) {
            $fieldName = is_string($baseField) ? $baseField : ($baseField['field'] ?? null);
            if (!$fieldName)
                continue;

            // FULL EXCLUSION: Skip if in hiddenTableColumns
            if (in_array($fieldName, $fullyHidden, true)) {
                continue;
            }

            $baseConfig = is_array($baseField) ? $baseField : ['field' => $fieldName];

            // If extraFields has an entry for this field → merge (extra wins)
            if (isset($normalizedExtra[$fieldName])) {
                $baseConfig = array_merge($baseConfig, $normalizedExtra[$fieldName]);
                unset($normalizedExtra[$fieldName]); // Prevent duplicate
            }

            $fields[] = $baseConfig;
        }

        // Add any remaining extraFields (virtual/new fields)
        foreach ($normalizedExtra as $extraConfig) {
            $extraFieldName = $extraConfig['field'] ?? null;
            if ($extraFieldName && in_array($extraFieldName, $fullyHidden, true)) {
                continue; // Respect hidden even on pure extra fields
            }
            $fields[] = $extraConfig;
        }

        // Load configurable options if model uses HasConfig trait
        // These provide per-school dropdowns (e.g., gender, religion)
        $configurableOptions = [];
        if (in_array(HasConfig::class, class_uses_recursive($model))) {
            try {
                // Fetch visible configs for this model and school
                $configs = $model->getVisibleConfigs();
                foreach ($configs as $config) {
                    // Only include if property is configurable
                    if (in_array($config->name, $model->getConfigurableProperties(), true)) {
                        // Normalize options to label/value pairs
                        $configurableOptions[$config->name] = collect($config->options)->map(function ($option) {
                            return [
                                'label' => is_array($option) ? ($option['label'] ?? $option['value'] ?? '') : Str::title(str_replace('_', ' ', $option)),
                                'value' => is_array($option) ? ($option['value'] ?? $option['label'] ?? '') : $option,
                            ];
                        })->values()->toArray();
                    }
                }
            } catch (\Throwable $e) {
                // Log failure but continue (non-critical)
                \Log::warning('[ColumnDefinitionHelper] HasConfig load failed', ['error' => $e->getMessage()]);
            }
        }

        // Final columns array to build
        $columns = [];

        // Process each merged field
        foreach ($fields as $config) {
            // Extract field name (skip invalid)
            $field = is_array($config) ? ($config['field'] ?? null) : $config;
            if (!$field)
                continue;

            // User-provided config (overrides)
            $userConfig = is_array($config) ? $config : [];

            // Skip if fully hidden (never sent to frontend)
            if (in_array($field, $fullyHidden, true)) {
                continue;
            }

            // Detect relation fields (dot notation)
            $isRelation = str_contains($field, '.');
            $relationPath = $isRelation ? implode('.', explode('.', $field, -1)) : null;
            $relatedField = $isRelation ? substr(strrchr($field, '.'), 1) : null;

            // Check if field is configurable (HasConfig)
            $isConfigurable = isset($configurableOptions[$field]);

            // Resolve filter type (user override > configurable > cast/DB detection)
            $filterType = $userConfig['filterType'] ?? ($isConfigurable ? 'dropdown' : self::resolveFilterType($model, $field));

            // Resolve filter options (user override > configurable > enum/cast)
            $filterOptions = $userConfig['filterOptions'] ?? null;
            if ($isConfigurable) {
                $filterType = 'dropdown';
                $filterOptions = $configurableOptions[$field];
            } else {
                $filterOptions = self::resolveFilterOptions($model, $field, $filterOptions);
            }

            // Build and add column definition
            $columns[] = [
                'field' => $field,
                'header' => $userConfig['header'] ?? self::makeHeader($field),

                'sortable' => $userConfig['sortable'] ?? !$isRelation,
                'filterable' => $userConfig['filterable'] ?? true,

                'filterType' => $filterType,
                'filterOptions' => $filterOptions,
                'filterMatchMode' => self::resolveFilterMatchMode($filterType),
                'filterPlaceholder' => $userConfig['filterPlaceholder'] ?? 'Search ' . self::makeHeader($field),

                // Visibility: 'hidden' for initial state, 'defaultHidden' flag for frontend logic
                'hidden' => $userConfig['hidden'] ?? in_array($field, $defaultHidden, true),
                'defaultHidden' => in_array($field, $defaultHidden, true), // for frontend to know

                'headerClass' => $userConfig['headerClass'] ?? 'font-medium text-left',
                'bodyClass' => $userConfig['bodyClass'] ?? 'text-sm',
                'width' => $userConfig['width'] ?? null,

                'relation' => $relationPath,
                'relatedField' => $relatedField,
            ];
        }

        return $columns;
    }

    // =================================================================
    // Helper Methods – Unchanged but Verbose Comments Added
    // =================================================================

    /**
     * Convert field name to human-readable header (snake_case/dot → Title Case)
     */
    private static function makeHeader(string $field): string
    {
        return Str::title(str_replace(['_', '.'], ' ', $field));
    }

    /**
     * Detect filter type based on casts or DB type
     * Supports Laravel casts, enums, and relations
     */
    private static function resolveFilterType(Model $model, string $field): string
    {
        if (str_contains($field, '.'))
            return 'text'; // Relations default to text

        $casts = $model->getCasts();

        if (isset($casts[$field])) {
            return match ($casts[$field]) {
                'boolean', 'bool' => 'boolean',
                'date', 'datetime', 'immutable_date', 'immutable_datetime' => 'date',
                'decimal', 'integer', 'int', 'float', 'double' => 'number',
                default => str_starts_with($casts[$field], 'enum:') ? 'dropdown' : 'text',
            };
        }

        try {
            // Fallback to DB column type for enums
            if (Schema::getColumnType($model->getTable(), $field) === 'enum') {
                return 'dropdown';
            }
        } catch (\Throwable) {
            // Ignore if column doesn't exist (e.g., virtual accessor)
        }

        return 'text';
    }

    /**
     * Generate dropdown options from casts or DB enums
     */
    private static function resolveFilterOptions(Model $model, string $field, ?array $override): ?array
    {
        if ($override !== null)
            return $override;
        if (str_contains($field, '.'))
            return null; // No options for relations

        $casts = $model->getCasts();
        if (isset($casts[$field]) && str_starts_with($casts[$field], 'enum:')) {
            return self::enumToOptions(substr($casts[$field], 5));
        }

        try {
            $type = Schema::getColumnType($model->getTable(), $field);
            if ($type === 'enum') {
                // Extract enum values from DB schema
                $values = Schema::getConnection()
                    ->getDoctrineColumn($model->getTable(), $field)
                    ->getType()
                    ->getValues();

                // Normalize to label/value pairs
                return array_map(fn($v) => [
                    'label' => Str::title(str_replace('_', ' ', $v)),
                    'value' => $v,
                ], $values);
            }
        } catch (\Throwable) {
            // Ignore errors (e.g., non-DB field)
        }

        return null;
    }

    /**
     * Convert PHP enum class to dropdown options
     * Supports backed and unit enums
     */
    private static function enumToOptions(string $enumClass): ?array
    {
        if (!class_exists($enumClass) || !is_subclass_of($enumClass, UnitEnum::class))
            return null;

        return collect($enumClass::cases())
            ->map(fn($case) => [
                'label' => Str::title(str_replace('_', ' ', $case instanceof BackedEnum ? $case->value : $case->name)),
                'value' => $case instanceof BackedEnum ? $case->value : $case->name,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Default PrimeVue match mode per filter type
     */
    private static function resolveFilterMatchMode(string $filterType): string
    {
        return match ($filterType) {
            'date', 'boolean', 'dropdown', 'number' => 'equals',
            default => 'contains',
        };
    }
}