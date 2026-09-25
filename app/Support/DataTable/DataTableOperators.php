<?php

namespace App\Support\DataTable;

final class DataTableOperators
{
    public const EQUALS = 'equals';
    public const NOT_EQUALS = 'notEquals';
    public const CONTAINS = 'contains';
    public const NOT_CONTAINS = 'notContains';
    public const STARTS_WITH = 'startsWith';
    public const ENDS_WITH = 'endsWith';
    public const LESS_THAN = 'lessThan';
    public const LESS_THAN_OR_EQUAL = 'lessThanOrEqual';
    public const GREATER_THAN = 'greaterThan';
    public const GREATER_THAN_OR_EQUAL = 'greaterThanOrEqual';
    public const IN = 'in';
    public const NOT_IN = 'notIn';
    public const BETWEEN = 'between';
    public const NOT_BETWEEN = 'notBetween';
    public const IS_NULL = 'isNull';
    public const IS_NOT_NULL = 'isNotNull';

    public static function all(): array
    {
        return [
            self::EQUALS, self::NOT_EQUALS, self::CONTAINS, self::NOT_CONTAINS,
            self::STARTS_WITH, self::ENDS_WITH, self::LESS_THAN, self::LESS_THAN_OR_EQUAL,
            self::GREATER_THAN, self::GREATER_THAN_OR_EQUAL, self::IN, self::NOT_IN,
            self::BETWEEN, self::NOT_BETWEEN, self::IS_NULL, self::IS_NOT_NULL,
        ];
    }

    public static function toPurity(): array
    {
        return [
            self::EQUALS => '$eq', self::NOT_EQUALS => '$ne',
            self::CONTAINS => '$contains', self::NOT_CONTAINS => '$notContains',
            self::STARTS_WITH => '$startsWith', self::ENDS_WITH => '$endsWith',
            self::LESS_THAN => '$lt', self::LESS_THAN_OR_EQUAL => '$lte',
            self::GREATER_THAN => '$gt', self::GREATER_THAN_OR_EQUAL => '$gte',
            self::IN => '$in', self::NOT_IN => '$notIn',
            self::BETWEEN => '$between', self::NOT_BETWEEN => '$notBetween',
            self::IS_NULL => '$null', self::IS_NOT_NULL => '$notNull',
        ];
    }

    public static function fromMatchMode(): array
    {
        return [
            'equals' => self::EQUALS, 'notEquals' => self::NOT_EQUALS,
            'contains' => self::CONTAINS, 'notContains' => self::NOT_CONTAINS,
            'startsWith' => self::STARTS_WITH, 'endsWith' => self::ENDS_WITH,
            'lt' => self::LESS_THAN, 'lte' => self::LESS_THAN_OR_EQUAL,
            'gt' => self::GREATER_THAN, 'gte' => self::GREATER_THAN_OR_EQUAL,
            'in' => self::IN, 'notIn' => self::NOT_IN,
            'between' => self::BETWEEN, 'notBetween' => self::NOT_BETWEEN,
            'is' => self::IS_NULL, 'isNot' => self::IS_NOT_NULL,
            'dateIs' => self::EQUALS, 'dateIsNot' => self::NOT_EQUALS,
            'dateBefore' => self::LESS_THAN, 'dateAfter' => self::GREATER_THAN,
            '$eq' => self::EQUALS, '$ne' => self::NOT_EQUALS,
            '$contains' => self::CONTAINS, '$notContains' => self::NOT_CONTAINS,
            '$startsWith' => self::STARTS_WITH, '$endsWith' => self::ENDS_WITH,
            '$lt' => self::LESS_THAN, '$lte' => self::LESS_THAN_OR_EQUAL,
            '$gt' => self::GREATER_THAN, '$gte' => self::GREATER_THAN_OR_EQUAL,
            '$in' => self::IN, '$notIn' => self::NOT_IN,
            '$between' => self::BETWEEN, '$notBetween' => self::NOT_BETWEEN,
            '$null' => self::IS_NULL, '$notNull' => self::IS_NOT_NULL,
        ];
    }

    public static function isValid(string $operator): bool
    {
        return in_array($operator, self::all(), true);
    }

    public static function isNullary(string $operator): bool
    {
        return in_array($operator, [self::IS_NULL, self::IS_NOT_NULL], true);
    }

    public static function expectsArray(string $operator): bool
    {
        return in_array($operator, [self::IN, self::NOT_IN, self::BETWEEN, self::NOT_BETWEEN], true);
    }
}
