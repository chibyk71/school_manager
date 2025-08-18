<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Class ColumnDefinitionHelper
 *
 * Generates column definitions for Eloquent models to support table querying.
 */
class ColumnDefinitionHelper
{
    /**
     * Generate column definitions from a model's fillable fields and extra fields.
     *
     * @param Model $model The Eloquent model instance.
     * @param array $extraFields Additional fields (e.g., relational fields or computed fields).
     * @param bool $includeNonFillable Whether to include non-fillable fields from the database schema.
     * @param array $customHeaders Custom header names for fields (keyed by field name).
     * @return array Array of column definitions.
     */
    public static function fromModel(Model $model, array $extraFields = [], bool $includeNonFillable = false, array $customHeaders = []): array
    {
        $columns = [];
        // Include non-fillable fields if requested, otherwise use fillable fields
        $defaultFields = $includeNonFillable
            ? array_map(fn($field) => ['field' => $field], Schema::getColumnListing($model->getTable()))
            : array_map(fn($field) => ['field' => $field], $model->getFillable());
        $fields = array_merge($defaultFields, $extraFields);

        foreach ($fields as $fieldConfig) {
            $field = is_array($fieldConfig) ? $fieldConfig['field'] : $fieldConfig;
            $parts = explode('.', $field);
            $isRelation = count($parts) > 1;
            $relation = $isRelation ? implode('.', array_slice($parts, 0, -1)) : null;
            $relatedField = $isRelation ? end($parts) : null;

            // Validate relation existence
            if ($isRelation && !self::validateRelation($model, $relation)) {
                Log::warning("Invalid relation '{$relation}' for field '{$field}' in model " . get_class($model));
                continue;
            }

            $columns[] = [
                'field'        => $field,
                'header'       => $customHeaders[$field] ?? ($fieldConfig['header'] ?? ucfirst(str_replace(['_', '.'], ' ', $field))),
                'sortable'     => $fieldConfig['sortable'] ?? true,
                'filterable'   => $fieldConfig['filterable'] ?? true,
                'filterType'   => self::guessFilterType($model, $field),
                'relation'     => $relation,
                'relatedField' => $relatedField,
                'customFilter' => $fieldConfig['customFilter'] ?? null, // Support custom filter logic
            ];
        }

        return $columns;
    }

    /**
     * Guess the filter type for a given field based on model casts or database schema.
     *
     * @param Model $model The Eloquent model instance.
     * @param string $field The field name (e.g., 'name', 'role.name').
     * @return string The inferred filter type (e.g., 'text', 'boolean', 'numeric', 'date', 'enum').
     */
    protected static function guessFilterType(Model $model, string $field): string
    {
        if (str_contains($field, '.')) {
            return 'text'; // Default for relational fields
        }

        // Check model casts first
        if (isset($model->getCasts()[$field])) {
            $type = $model->getCasts()[$field];
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
     * Validate that a relation exists on the model.
     *
     * @param Model $model The Eloquent model instance.
     * @param string $relation The relation name (e.g., 'role' or 'role.company').
     * @return bool True if the relation is valid, false otherwise.
     */
    protected static function validateRelation(Model $model, string $relation): bool
    {
        $parts = explode('.', $relation);
        $currentModel = $model;

        foreach ($parts as $rel) {
            if (!method_exists($currentModel, $rel)) {
                return false;
            }
            $relationInstance = $currentModel->$rel();
            $currentModel = $relationInstance->getRelated();
        }

        return true;
    }
}
