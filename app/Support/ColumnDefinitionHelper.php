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
 * ColumnDefinitionHelper v3.0 – The Ultimate SaaS-Ready DataTable Column Generator
 *
 * This class is the heart of your fully backend-driven, zero-config AdvancedDataTable system.
 *
 * What it does:
 *  • Automatically generates perfect PrimeVue column definitions from ANY Eloquent model
 *  • Supports simple fields, nested relations, casted enums, DB enums, dates, booleans
 *  • FULL integration with HasConfig trait → dynamic per-school dropdowns (e.g. gender, religion)
 *  • Respects hidden columns, fillable/guarded, custom headers, sortable/filterable flags
 *  • Caches schema aggressively for performance
 *  • Returns clean, typed array ready to send to Vue via Inertia
 *
 * Usage:
 *   $columns = ColumnDefinitionHelper::fromModel(new User());
 *   return Inertia::render('Users/Index', ['columns' => $columns, ...]);
 *
 * @author  Your Name
 * @version 3.0 – Fully Commented Enterprise Edition
 */
final class ColumnDefinitionHelper
{
    /**
     * Generate PrimeVue-ready column definitions for a model
     *
     * @param Model $model                Instance of your Eloquent model (e.g. new User())
     * @param array  $extraFields         Optional virtual/computed fields: ['full_name' => ['header' => 'Name']]
     * @param bool   $includeNonFillable  Include guarded columns like id, created_at? (usually false)
     *
     * @return array<int, array>          Array of column definitions ready for Vue
     */
    public static function fromModel(Model $model, array $extraFields = [], bool $includeNonFillable = false): array
    {
        $school = getSchoolModel();

        /**
         * Step 1: Get hidden columns defined in model via HasTableQuery trait
         * Example: public function getHiddenTableColumns() { return ['password', 'remember_token']; }
         */
        $hiddenColumns = method_exists($model, 'getHiddenTableColumns')
            ? $model->getHiddenTableColumns()
            : [];

        /**
         * Step 2: Cache database schema per table + school (critical for multi-tenant performance)
         * Cache key includes school ID so each tenant gets correct column list
         */
        $cacheKey = 'datatable_schema_' . $model->getTable() . '_' . ($school?->id ?? 'global');

        $tableColumns = Cache::remember($cacheKey, now()->addHours(6), function () use ($model) {
            return Schema::getColumnListing($model->getTable());
        });

        /**
         * Step 3: Determine base fields to process
         * - Use fillable by default (safe)
         * - Or all table columns if $includeNonFillable = true (e.g. for system tables)
         */
        $baseFields = $includeNonFillable
            ? array_map(fn($col) => ['field' => $col], $tableColumns)
            : array_map(fn($col) => ['field' => $col], $model->getFillable());

        /**
         * KEY FIX: Normalize extraFields so we can detect overrides
         * Convert:
         *   'is_active' => ['header' => 'Status']
         * or
         *   'is_active'
         * into:
         *   ['field' => 'is_active', 'header' => 'Status', ...]
         */
        $normalizedExtra = [];
        foreach ($extraFields as $key => $value) {
            if (is_numeric($key) && is_string($value)) {
                // Shorthand: 'is_active'
                $normalizedExtra[$value] = ['field' => $value];
            } elseif (is_string($key) && is_array($value)) {
                // Full: 'is_active' => ['header' => 'Status', 'filterType' => 'boolean']
                $value['field'] = $key;
                $normalizedExtra[$key] = $value;
            } elseif (is_array($value) && isset($value['field'])) {
                // Already proper format
                $normalizedExtra[$value['field']] = $value;
            }
        }

        /**
         * Build final field list:
         * 1. Start with base fields
         * 2. Remove any that are overridden by extraFields
         * 3. Merge extraFields (they win)
         */
        $finalFields = [];

        foreach ($baseFields as $base) {
            $field = is_array($base) ? $base['field'] : $base;
            // If user explicitly overrides this field → skip base version
            if (!array_key_exists($field, $normalizedExtra)) {
                $finalFields[] = $base;
            }
        }

        // Now add all extraFields (they override everything)
        foreach ($normalizedExtra as $config) {
            $finalFields[] = $config;
        }

        // Merge with any manually defined virtual fields
        $allFields = $finalFields;

        /**
         * Step 4: Pre-load HasConfig options if model uses the trait
         * This allows per-school customizable dropdowns (e.g. gender, religion, blood type)
         */
        $configurableOptions = [];

        if (in_array(HasConfig::class, class_uses_recursive($model))) {
            try {
                // Fetch only configs that apply to this model + current school
                $configs = $model->getVisibleConfigs();

                foreach ($configs as $config) {
                    // Only include if field is declared in getConfigurableProperties()
                    if (in_array($config->name, $model->getConfigurableProperties(), true)) {
                        $configurableOptions[$config->name] = collect($config->options)
                            ->map(fn($option) => [
                                // Support both string and array format: 'Male' or ['label' => 'Male', 'value' => 'male']
                                'label' => is_array($option)
                                    ? ($option['label'] ?? $option['value'] ?? $option)
                                    : Str::title(str_replace('_', ' ', $option)),
                                'value' => is_array($option)
                                    ? ($option['value'] ?? $option['label'] ?? $option)
                                    : $option,
                            ])
                            ->values()
                            ->toArray();
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('[ColumnDefinitionHelper] Failed to load HasConfig options for ' . get_class($model), [
                    'error' => $e->getMessage()
                ]);
            }
        }

        $columns = [];

        /**
         * Step 5: Process each field and build column definition
         */
        foreach ($allFields as $config) {
            // Support both string shorthand ('name') and full array config
            $field = is_array($config) ? ($config['field'] ?? null) : $config;

            if (!$field) {
                continue; // Invalid field
            }

            $userConfig = is_array($config) ? $config : [];

            // Skip if explicitly hidden via config or trait
            if ($userConfig['hidden'] ?? in_array($field, $hiddenColumns, true)) {
                continue;
            }

            /**
             * Detect if this is a relation field (e.g. 'user.profile.city')
             */
            $isRelation = str_contains($field, '.');
            $relationPath = $isRelation ? implode('.', explode('.', $field, -1)) : null;
            $relatedField = $isRelation ? substr($field, strrpos($field, '.') + 1) : null;

            /**
             * Check if this field is configurable via HasConfig trait
             */
            $isConfigurableField = isset($configurableOptions[$field]);

            /**
             * Resolve filter type (dropdown, text, date, etc.)
             */
            $filterType = $userConfig['filterType']
                ?? ($isConfigurableField ? 'dropdown' : self::resolveFilterType($model, $field));

            /**
             * Resolve filter options (for dropdowns)
             * Priority: user override > HasConfig > enum cast > DB enum
             */
            $filterOptions = $userConfig['filterOptions'] ?? null;

            if ($isConfigurableField) {
                $filterType = 'dropdown';
                $filterOptions = $configurableOptions[$field];
            } else {
                $filterOptions = self::resolveFilterOptions($model, $field, $filterOptions);
            }

            /**
             * Build final column definition
             */
            $columns[] = [
                // Core identifiers
                'field' => $field,
                'header' => $userConfig['header'] ?? self::makeHeader($field),

                // Behavior flags
                'sortable' => $userConfig['sortable'] ?? !$isRelation, // Relations rarely sortable server-side
                'filterable' => $userConfig['filterable'] ?? true,

                // Filter configuration
                'filterType' => $filterType,
                'filterOptions' => $filterOptions,
                'filterMatchMode' => self::resolveFilterMatchMode($filterType),
                'filterPlaceholder' => $userConfig['filterPlaceholder'] ?? 'Select ' . self::makeHeader($field),

                // Visibility & styling
                'hidden' => false,
                'headerClass' => $userConfig['headerClass'] ?? 'font-medium text-left',
                'bodyClass' => $userConfig['bodyClass'] ?? 'text-sm',
                'width' => $userConfig['width'] ?? null,

                // Relation metadata (used by HasTableQuery trait for filtering)
                'relation' => $relationPath,
                'relatedField' => $relatedField,
            ];
        }

        return $columns;
    }

    /**
     * Convert snake_case or dot.path → "First Name"
     */
    private static function makeHeader(string $field): string
    {
        return Str::title(str_replace(['_', '.'], ' ', $field));
    }

    /**
     * Smart detection of filter type with full support for:
     * - HasConfig fields
     * - Casted enums
     * - DB enum columns
     * - Standard Laravel casts
     */
    private static function resolveFilterType(Model $model, string $field): string
    {
        // Relations → always text search
        if (str_contains($field, '.')) {
            return 'text';
        }

        $casts = $model->getCasts();

        // Laravel casted enum: protected $casts = ['status' => StatusEnum::class];
        if (isset($casts[$field]) && str_starts_with($casts[$field], 'enum:')) {
            return 'dropdown';
        }

        // Standard Laravel casts
        if (isset($casts[$field])) {
            return match ($casts[$field]) {
                'boolean', 'bool' => 'boolean',
                'date', 'datetime', 'immutable_date', 'immutable_datetime' => 'date',
                'decimal', 'integer', 'int', 'float', 'double' => 'number',
                default => 'text',
            };
        }

        // Fallback: check raw DB column type
        try {
            $dbType = Schema::getColumnType($model->getTable(), $field);
            if ($dbType === 'enum') {
                return 'dropdown';
            }
        } catch (\Throwable) {
            // Column doesn't exist locally (likely accessor) → default to text
        }

        return 'text';
    }

    /**
     * Generate dropdown options from enums (casted or DB-level)
     */
    private static function resolveFilterOptions(Model $model, string $field, ?array $override): ?array
    {
        if ($override !== null) {
            return $override;
        }

        if (str_contains($field, '.')) {
            return null; // No auto-options for relations
        }

        $casts = $model->getCasts();

        // Casted enum: "enum:App\Enums\Status"
        if (isset($casts[$field]) && str_starts_with($casts[$field], 'enum:')) {
            $enumClass = substr($casts[$field], 5);
            return self::enumToOptions($enumClass);
        }

        // Raw MySQL enum column
        try {
            $type = Schema::getColumnType($model->getTable(), $field);
            if ($type === 'enum') {
                $values = Schema::getConnection()
                    ->getDoctrineColumn($model->getTable(), $field)
                    ->getType()
                    ->getValues();

                return array_map(fn($v) => [
                    'label' => Str::title(str_replace('_', ' ', $v)),
                    'value' => $v,
                ], $values);
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * Convert any PHP 8.1+ enum (backed or pure) to PrimeVue dropdown options
     */
    private static function enumToOptions(string $enumClass): ?array
    {
        if (!class_exists($enumClass) || !is_subclass_of($enumClass, UnitEnum::class)) {
            return null;
        }

        return collect($enumClass::cases())
            ->map(fn($case) => [
                'label' => Str::title(str_replace('_', ' ', $case instanceof BackedEnum ? $case->value : $case->name)),
                'value' => $case instanceof BackedEnum ? $case->value : $case->name,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Best PrimeVue filterMatchMode per filter type
     */
    private static function resolveFilterMatchMode(string $filterType): string
    {
        return match ($filterType) {
            'date', 'boolean', 'dropdown', 'number' => 'equals',
            default => 'contains',
        };
    }
}