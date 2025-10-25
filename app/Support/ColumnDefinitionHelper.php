<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Class ColumnDefinitionHelper
 *
 * Generates column definitions for Eloquent models to support dynamic table querying.
 */
class ColumnDefinitionHelper
{
    /**
     * Generate column definitions from a model's attributes and extra fields.
     *
     * @param Model $model The Eloquent model instance.
     * @param array $extraFields Additional fields (e.g., relational or computed fields).
     * @param bool $includeNonFillable Whether to include non-fillable fields from the database schema.
     * @return array Array of column definitions with keys: field, header, sortable, filterable, filterType, relation, relatedField, customFilter, hidden.
     * @throws \Exception If no active school is found or column generation fails.
     */
    public static function fromModel(Model $model, array $extraFields = [], bool $includeNonFillable = false): array
    {
        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            $columns = [];
            // Get hidden columns from model (from HasTableQuery trait)
            $hiddenColumns = method_exists($model, 'getHiddenTableColumns') ? $model->getHiddenTableColumns() : [];

            // Cache schema for performance
            $cacheKey = 'schema_' . $model->getTable() . '_' . $school->id;
            $schemaFields = Cache::remember($cacheKey, now()->addDays(1), fn() => Schema::getColumnListing($model->getTable()));

            // Use fillable fields or schema fields
            $defaultFields = $includeNonFillable
                ? array_map(fn($field) => ['field' => $field], $schemaFields)
                : array_map(fn($field) => ['field' => $field], $model->getFillable());
            $fields = array_merge($defaultFields, $extraFields);

            foreach ($fields as $fieldConfig) {
                $field = is_array($fieldConfig) ? $fieldConfig['field'] : $fieldConfig;
                $parts = explode('.', $field);
                $isRelation = count($parts) > 1;
                $relation = $isRelation ? implode('.', array_slice($parts, 0, -1)) : null;
                $relatedField = $isRelation ? end($parts) : null;

                // Validate relation existence and school scoping
                if ($isRelation && !self::validateRelation($model, $relation, $school->id)) {
                    Log::warning("Invalid or unscoped relation '{$relation}' for field '{$field}' in model " . get_class($model));
                    continue;
                }

                $columns[] = [
                    'field'        => $field,
                    'header'       => $fieldConfig['header'] ?? ucfirst(str_replace(['_', '.'], ' ', $field)),
                    'sortable'     => $fieldConfig['sortable'] ?? true,
                    'filterable'   => $fieldConfig['filterable'] ?? true,
                    'filterType'   => self::guessFilterType($model, $field),
                    'relation'     => $relation,
                    'relatedField' => $relatedField,
                    'customFilter' => $fieldConfig['customFilter'] ?? null,
                    'hidden'       => in_array($field, $hiddenColumns) || ($fieldConfig['hidden'] ?? false),
                ];
            }

            return $columns;
        } catch (\Exception $e) {
            Log::error('Failed to generate column definitions for model ' . get_class($model) . ': ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Guess the filter type for a given field based on model casts or database schema.
     *
     * @param Model $model The Eloquent model instance.
     * @param string $field The field name (e.g., 'name', 'role.name').
     * @return string The inferred filter type (e.g., 'text', 'boolean', 'numeric', 'date', 'dropdown').
     */
    protected static function guessFilterType(Model $model, string $field): string
    {
        if (str_contains($field, '.')) {
            return 'text'; // Default for relational fields
        }

        // Check model casts
        $casts = $model->getCasts();
        if (isset($casts[$field])) {
            $type = $casts[$field];
            if (str_starts_with($type, 'enum:')) {
                return 'dropdown';
            }
        } else {
            // Fallback to database schema
            try {
                $type = Schema::getColumnType($model->getTable(), $field) ?? 'string';
            } catch (\Exception $e) {
                Log::warning("Unable to determine schema type for field '{$field}' in model " . get_class($model));
                $type = 'string';
            }
        }

        return match ($type) {
            'boolean' => 'boolean',
            'datetime', 'date', 'timestamp' => 'date',
            'integer', 'float', 'decimal', 'double' => 'numeric',
            'enum' => 'dropdown',
            default => 'text',
        };
    }

    /**
     * Validate that a relation exists on the model and is scoped to the school.
     *
     * @param Model $model The Eloquent model instance.
     * @param string $relation The relation name (e.g., 'role' or 'role.company').
     * @param int $schoolId The active school ID.
     * @return bool True if the relation is valid and scoped, false otherwise.
     */
    protected static function validateRelation(Model $model, string $relation, int $schoolId): bool
    {
        try {
            $parts = explode('.', $relation);
            $currentModel = $model;

            foreach ($parts as $rel) {
                if (!method_exists($currentModel, $rel)) {
                    return false;
                }
                $relationInstance = $currentModel->$rel();
                $currentModel = $relationInstance->getRelated();

                // Check if related model has school_id and is scoped
                if (method_exists($currentModel, 'school') && $relationInstance->getQuery()->where('school_id', $schoolId)->doesntExist()) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::warning("Failed to validate relation '{$relation}' for model " . get_class($model) . ': ' . $e->getMessage());
            return false;
        }
    }
}
