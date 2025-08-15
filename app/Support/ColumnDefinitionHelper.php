<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ColumnDefinitionHelper
{
    /**
     * Generate column definitions from model schema + extra fields.
     *
     * @param  Model  $model
     * @param  array  $extraFields
     * @return array
     */
    public static function fromModel(Model $model, array $extraFields = []): array
    {
        $table = $model->getTable();
        $fillable = $model->getFillable();
        $columns = array_merge($fillable, $extraFields);

        return collect($columns)->map(function ($column) use ($table) {
            $typeInfo = self::getColumnTypeInfo($table, $column);

            return [
                'field'       => $column,
                'header'      => ucwords(str_replace('_', ' ', $column)),
                'sortable'    => true,
                'filter'      => true,
                'filterType'  => self::mapTypeToFilter($typeInfo['type']),
                'options'     => $typeInfo['options'] ?? null, // for dropdowns
            ];
        })->values()->toArray();
    }

    /**
     * Get column type info from the database schema.
     */
    protected static function getColumnTypeInfo(string $table, string $column): array
    {
        $type = Schema::getColumnType($table, $column); // Laravel's abstract type
        $options = null;

        // Get native DB type to check for enums
        $columnData = DB::selectOne("SHOW COLUMNS FROM {$table} WHERE Field = ?", [$column]);

        if ($columnData && str_starts_with(strtolower($columnData->Type), 'enum')) {
            preg_match("/^enum\\('(.*)'\\)$/", $columnData->Type, $matches);
            if (isset($matches[1])) {
                $options = explode("','", $matches[1]);
            }
        }

        return [
            'type'    => $type,
            'options' => $options,
        ];
    }

    /**
     * Map Laravel column type to filter type.
     */
    protected static function mapTypeToFilter(string $type): string
    {
        return match ($type) {
            'date', 'datetime', 'timestamp' => 'date',
            'integer', 'bigint', 'decimal', 'float' => 'number',
            'boolean' => 'boolean',
            default => 'text',
        };
    }
}
