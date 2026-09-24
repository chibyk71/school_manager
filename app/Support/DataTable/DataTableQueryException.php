<?php

namespace App\Support\DataTable;

use InvalidArgumentException;

final class DataTableQueryException extends InvalidArgumentException
{
    public static function unknownField(string $field, string $capability): self
    {
        return new self("Field [{$field}] is not available for {$capability}.");
    }

    public static function notCapable(string $field, string $capability): self
    {
        return new self("Field [{$field}] is not {$capability}.");
    }

    public static function unsupportedOperator(string $field, string $operator): self
    {
        return new self("Operator [{$operator}] is not supported for field [{$field}].");
    }

    public static function invalidValue(string $field, string $operator): self
    {
        return new self("Invalid value for field [{$field}] with operator [{$operator}].");
    }

    public static function malformed(string $message): self
    {
        return new self($message);
    }
}
