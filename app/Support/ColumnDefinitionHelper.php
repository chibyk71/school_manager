<?php
// app/Support/ColumnDefinitionHelper.php
namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ColumnDefinitionHelper
{
    public static function fromModel(Model $model, array $extraFields = []): array
    {
        $columns = [];
        $fields = array_merge($model->getFillable(), $extraFields);

        foreach ($fields as $field) {
            $isRelation = str_contains($field, '.');

            $columns[] = [
                'field'        => $field,
                'header'       => ucfirst(str_replace(['_', '.'], ' ', $field)),
                'sortable'     => true,
                'filterable'   => true,
                'filterType'   => self::guessFilterType($model, $field),
                'relation'     => $isRelation ? Str::beforeLast($field, '.') : null,
                'relatedField' => $isRelation ? Str::afterLast($field, '.') : null,
            ];
        }

        return $columns;
    }

    protected static function guessFilterType(Model $model, string $field): string
    {
        // Handle relation.field
        if (str_contains($field, '.')) {
            return 'text'; // default for related fields
        }

        $type = $model->getCasts()[$field] ?? 'string';

        return match ($type) {
            'boolean'   => 'boolean',
            'datetime', 'date' => 'date',
            'integer', 'float', 'decimal' => 'numeric',
            default     => 'text',
        };
    }
}
